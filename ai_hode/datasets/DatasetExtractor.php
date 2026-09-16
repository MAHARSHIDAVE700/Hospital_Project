<?php
/**
 * AI-HODE Dataset Extractor Engine
 * Path: ai_hode/datasets/DatasetExtractor.php
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/FeatureEngineeringService.php';

class DatasetExtractor {

    private $conn;

    public function __construct($dbConnection = null) {
        global $conn;
        $this->conn = $dbConnection ? $dbConnection : $conn;
        $this->initDatasetTables();
    }

    /**
     * Create isolated dataset tables if they do not exist
     */
    public function initDatasetTables() {
        $queries = [
            "CREATE TABLE IF NOT EXISTS ai_dataset_patient_arrivals (
                dataset_id BIGSERIAL PRIMARY KEY,
                run_id VARCHAR(50) DEFAULT 'HISTORICAL',
                arrival_date DATE NOT NULL,
                arrival_hour INT NOT NULL,
                day_of_week INT NOT NULL,
                department_id INT DEFAULT 1,
                appointment_count INT DEFAULT 0,
                actual_arrival_count INT DEFAULT 0,
                historical_avg_arrivals NUMERIC(8,2) DEFAULT 0.0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_patient_arrivals UNIQUE (run_id, arrival_date, arrival_hour, department_id)
            )",

            "CREATE TABLE IF NOT EXISTS ai_dataset_waiting_time (
                dataset_id BIGSERIAL PRIMARY KEY,
                run_id VARCHAR(50) DEFAULT 'HISTORICAL',
                flow_id BIGINT UNIQUE,
                patient_id INT NOT NULL,
                appointment_id INT,
                doctor_id INT,
                department_id INT DEFAULT 1,
                queue_position INT DEFAULT 1,
                token_number VARCHAR(50),
                arrival_timestamp TIMESTAMP NOT NULL,
                arrival_hour INT NOT NULL,
                day_of_week INT NOT NULL,
                doctor_active_patients INT DEFAULT 0,
                estimated_wait_minutes NUMERIC(8,2) DEFAULT 0.0,
                actual_wait_minutes NUMERIC(8,2) DEFAULT 0.0,
                is_bottleneck BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            "CREATE TABLE IF NOT EXISTS ai_dataset_bed_occupancy (
                dataset_id BIGSERIAL PRIMARY KEY,
                run_id VARCHAR(50) DEFAULT 'HISTORICAL',
                snapshot_date DATE NOT NULL,
                bed_type VARCHAR(50) NOT NULL,
                total_beds INT NOT NULL,
                occupied_beds INT NOT NULL,
                available_beds INT NOT NULL,
                admissions_count INT DEFAULT 0,
                discharges_count INT DEFAULT 0,
                avg_stay_duration_days NUMERIC(8,2) DEFAULT 1.0,
                occupancy_rate_pct NUMERIC(8,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_bed_occupancy UNIQUE (run_id, snapshot_date, bed_type)
            )",

            "CREATE TABLE IF NOT EXISTS ai_dataset_doctor_workload (
                dataset_id BIGSERIAL PRIMARY KEY,
                run_id VARCHAR(50) DEFAULT 'HISTORICAL',
                doctor_id INT NOT NULL,
                snapshot_date DATE NOT NULL,
                department_id INT DEFAULT 1,
                opd_patient_count INT DEFAULT 0,
                ipd_patient_count INT DEFAULT 0,
                total_patient_count INT DEFAULT 0,
                avg_consult_duration_minutes NUMERIC(8,2) DEFAULT 15.0,
                queue_length INT DEFAULT 0,
                workload_score NUMERIC(8,2) NOT NULL,
                burnout_risk_level VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT uq_doctor_workload UNIQUE (run_id, doctor_id, snapshot_date)
            )"
        ];

        foreach ($queries as $sql) {
            $this->conn->query($sql);
        }
    }

    /**
     * Extract All Datasets
     */
    public function extractAll() {
        $results = [];
        $results['patient_arrivals'] = $this->extractPatientArrivalsDataset();
        $results['waiting_time'] = $this->extractWaitingTimeDataset();
        $results['bed_occupancy'] = $this->extractBedOccupancyDataset();
        $results['doctor_workload'] = $this->extractDoctorWorkloadDataset();
        return $results;
    }

    /**
     * 1. Extract Patient Arrival Prediction Dataset
     */
    public function extractPatientArrivalsDataset() {
        $extractedCount = 0;

        $sql = "SELECT 
                    DATE(COALESCE(pf.stage_entry_time, a.appointment_date::timestamp)) as arr_date,
                    EXTRACT(HOUR FROM COALESCE(pf.stage_entry_time, a.appointment_date::timestamp)) as arr_hour,
                    COALESCE(d.department_id, pf.department_id, 1) as dept_id,
                    COUNT(DISTINCT a.appointment_id) as appt_cnt,
                    COUNT(DISTINCT pf.patient_id) as arr_cnt
                FROM appointments a
                LEFT JOIN patient_flow pf ON a.appointment_id = pf.appointment_id
                LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
                WHERE a.appointment_date IS NOT NULL
                GROUP BY 1, 2, 3";

        $res = $this->conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $arrDate = (string)$row['arr_date'];
                $arrHour = (int)$row['arr_hour'];
                $deptId = (int)$row['dept_id'];
                $dow = FeatureEngineeringService::extractDayOfWeek($arrDate);
                $apptCnt = (int)$row['appt_cnt'];
                $arrCnt = max($apptCnt, (int)$row['arr_cnt']);
                $histAvg = round($arrCnt * 0.9, 2);

                $runId = $this->getRunIdForEntity('appointment', null) ?: 'SIM-20260817-001';

                $insertSql = "INSERT INTO ai_dataset_patient_arrivals 
                                (run_id, arrival_date, arrival_hour, day_of_week, department_id, appointment_count, actual_arrival_count, historical_avg_arrivals)
                              VALUES 
                                (?, ?, ?, ?, ?, ?, ?, ?)
                              ON CONFLICT (run_id, arrival_date, arrival_hour, department_id) 
                              DO UPDATE SET 
                                appointment_count = EXCLUDED.appointment_count,
                                actual_arrival_count = EXCLUDED.actual_arrival_count";

                $stmt = $this->conn->prepare($insertSql);
                if ($stmt) {
                    $stmt->bind_param("ssiiiiid", $runId, $arrDate, $arrHour, $dow, $deptId, $apptCnt, $arrCnt, $histAvg);
                    if ($stmt->execute()) {
                        $extractedCount++;
                    }
                }
            }
        }

        return ['status' => 'SUCCESS', 'records_extracted' => $extractedCount];
    }

    /**
     * 2. Extract OPD Queue / Waiting Time Dataset
     */
    public function extractWaitingTimeDataset() {
        $extractedCount = 0;

        $sql = "SELECT 
                    pf.flow_id,
                    pf.patient_id,
                    pf.appointment_id,
                    pf.assigned_doctor_id as doctor_id,
                    pf.department_id,
                    pf.stage_entry_time,
                    pf.stage_exit_time,
                    pf.dwell_time_seconds,
                    pf.is_bottleneck,
                    a.token_number,
                    a.queue_position,
                    qe.estimated_wait_seconds,
                    qe.actual_wait_seconds
                FROM patient_flow pf
                LEFT JOIN appointments a ON pf.appointment_id = a.appointment_id
                LEFT JOIN queue_events qe ON pf.flow_id = qe.flow_id
                WHERE pf.stage_entry_time IS NOT NULL";

        $res = $this->conn->query($sql);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $flowId = (int)$row['flow_id'];
                $patientId = (int)$row['patient_id'];
                $apptId = $row['appointment_id'] ? (int)$row['appointment_id'] : 0;
                $doctorId = $row['doctor_id'] ? (int)$row['doctor_id'] : 0;
                $deptId = (int)$row['department_id'];
                $entryTime = (string)$row['stage_entry_time'];
                $exitTime = $row['stage_exit_time'] ? (string)$row['stage_exit_time'] : null;
                $dwellSeconds = $row['dwell_time_seconds'] ?: $row['actual_wait_seconds'];

                $arrHour = FeatureEngineeringService::extractHour($entryTime);
                $dow = FeatureEngineeringService::extractDayOfWeek($entryTime);
                $queuePos = $row['queue_position'] ? (int)$row['queue_position'] : 1;
                $tokenNo = $row['token_number'] ?: ('T-' . $flowId);
                
                $estWaitMins = $row['estimated_wait_seconds'] ? round($row['estimated_wait_seconds'] / 60.0, 2) : 15.0;
                $actualWaitMins = FeatureEngineeringService::calculateWaitingTimeMinutes($entryTime, $exitTime, $dwellSeconds);
                $isBottleneckInt = ($row['is_bottleneck'] == 1 || $row['is_bottleneck'] === 't' || $row['is_bottleneck'] === true) ? 1 : 0;
                $docActivePatients = 3;

                $runId = $this->getRunIdForEntity('patient_flow', $flowId) ?: 'SIM-20260817-001';

                $insertSql = "INSERT INTO ai_dataset_waiting_time 
                                (run_id, flow_id, patient_id, appointment_id, doctor_id, department_id, queue_position, token_number, arrival_timestamp, arrival_hour, day_of_week, doctor_active_patients, estimated_wait_minutes, actual_wait_minutes, is_bottleneck)
                              VALUES 
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                              ON CONFLICT (flow_id) 
                              DO UPDATE SET 
                                estimated_wait_minutes = EXCLUDED.estimated_wait_minutes,
                                actual_wait_minutes = EXCLUDED.actual_wait_minutes,
                                is_bottleneck = EXCLUDED.is_bottleneck";

                $stmt = $this->conn->prepare($insertSql);
                if ($stmt) {
                    $stmt->bind_param("siiiiissiiiiddd", $runId, $flowId, $patientId, $apptId, $doctorId, $deptId, $queuePos, $tokenNo, $entryTime, $arrHour, $dow, $docActivePatients, $estWaitMins, $actualWaitMins, $isBottleneckInt);
                    if ($stmt->execute()) {
                        $extractedCount++;
                    }
                }
            }
        }

        return ['status' => 'SUCCESS', 'records_extracted' => $extractedCount];
    }

    /**
     * 3. Extract Bed Occupancy Dataset
     */
    public function extractBedOccupancyDataset() {
        $extractedCount = 0;

        $typeRes = $this->conn->query("SELECT bed_type, COUNT(*) as total_cnt FROM beds GROUP BY bed_type");
        if ($typeRes && $typeRes->num_rows > 0) {
            while ($bTypeRow = $typeRes->fetch_assoc()) {
                $bedType = (string)$bTypeRow['bed_type'];
                $totalBeds = (int)$bTypeRow['total_cnt'];

                $occRes = $this->conn->query("SELECT COUNT(*) as occ_cnt FROM beds WHERE bed_type = '" . addslashes($bedType) . "' AND status = 'Occupied'");
                $occRow = $occRes ? $occRes->fetch_assoc() : ['occ_cnt' => 0];
                $occupiedBeds = (int)$occRow['occ_cnt'];
                $availableBeds = max(0, $totalBeds - $occupiedBeds);

                $todayDate = date('Y-m-d');
                $admRes = $this->conn->query("SELECT COUNT(*) as cnt FROM bed_allocations WHERE DATE(admission_date) = '{$todayDate}'");
                $admCnt = ($admRes && ($aR = $admRes->fetch_assoc())) ? (int)$aR['cnt'] : 0;

                $disRes = $this->conn->query("SELECT COUNT(*) as cnt FROM bed_allocations WHERE DATE(discharge_date) = '{$todayDate}'");
                $disCnt = ($disRes && ($dR = $disRes->fetch_assoc())) ? (int)$dR['cnt'] : 0;

                $occupancyRate = FeatureEngineeringService::calculateOccupancyRate($occupiedBeds, $totalBeds);
                $avgStayDays = 2.50;
                $runId = 'SIM-20260817-001';

                $insertSql = "INSERT INTO ai_dataset_bed_occupancy 
                                (run_id, snapshot_date, bed_type, total_beds, occupied_beds, available_beds, admissions_count, discharges_count, avg_stay_duration_days, occupancy_rate_pct)
                              VALUES 
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                              ON CONFLICT (run_id, snapshot_date, bed_type)
                              DO UPDATE SET 
                                occupied_beds = EXCLUDED.occupied_beds,
                                available_beds = EXCLUDED.available_beds,
                                occupancy_rate_pct = EXCLUDED.occupancy_rate_pct";

                $stmt = $this->conn->prepare($insertSql);
                if ($stmt) {
                    $stmt->bind_param("sssiiiiidd", $runId, $todayDate, $bedType, $totalBeds, $occupiedBeds, $availableBeds, $admCnt, $disCnt, $avgStayDays, $occupancyRate);
                    if ($stmt->execute()) {
                        $extractedCount++;
                    }
                }
            }
        }

        return ['status' => 'SUCCESS', 'records_extracted' => $extractedCount];
    }

    /**
     * 4. Extract Doctor Workload Dataset
     */
    public function extractDoctorWorkloadDataset() {
        $extractedCount = 0;

        $docRes = $this->conn->query("SELECT doctor_id, department_id FROM doctors");
        if ($docRes && $docRes->num_rows > 0) {
            $todayDate = date('Y-m-d');
            while ($doc = $docRes->fetch_assoc()) {
                $doctorId = (int)$doc['doctor_id'];
                $deptId = (int)$doc['department_id'];

                $opdRes = $this->conn->query("SELECT COUNT(*) as cnt FROM appointments WHERE doctor_id = {$doctorId} AND appointment_date = '{$todayDate}'");
                $opdCount = ($opdRes && ($oR = $opdRes->fetch_assoc())) ? (int)$oR['cnt'] : 0;

                $ipdRes = $this->conn->query("SELECT COUNT(*) as cnt FROM ipd_admissions WHERE doctor_id = {$doctorId} AND status = 'Admitted'");
                $ipdCount = ($ipdRes && ($iR = $ipdRes->fetch_assoc())) ? (int)$iR['cnt'] : 0;

                $qRes = $this->conn->query("SELECT COUNT(*) as cnt FROM appointments WHERE doctor_id = {$doctorId} AND queue_status = 'Waiting'");
                $queueLength = ($qRes && ($qR = $qRes->fetch_assoc())) ? (int)$qR['cnt'] : 0;

                $totalCount = $opdCount + $ipdCount;
                $avgConsultMins = 15.00;
                $workloadScore = FeatureEngineeringService::calculateDoctorWorkloadScore($opdCount, $ipdCount, $queueLength);
                $burnoutRisk = FeatureEngineeringService::determineBurnoutRiskLevel($workloadScore);
                $runId = 'SIM-20260817-001';

                $insertSql = "INSERT INTO ai_dataset_doctor_workload 
                                (run_id, doctor_id, snapshot_date, department_id, opd_patient_count, ipd_patient_count, total_patient_count, avg_consult_duration_minutes, queue_length, workload_score, burnout_risk_level)
                              VALUES 
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                              ON CONFLICT (run_id, doctor_id, snapshot_date)
                              DO UPDATE SET 
                                opd_patient_count = EXCLUDED.opd_patient_count,
                                ipd_patient_count = EXCLUDED.ipd_patient_count,
                                workload_score = EXCLUDED.workload_score,
                                burnout_risk_level = EXCLUDED.burnout_risk_level";

                $stmt = $this->conn->prepare($insertSql);
                if ($stmt) {
                    $stmt->bind_param("siiiiiiidds", $runId, $doctorId, $todayDate, $deptId, $opdCount, $ipdCount, $totalCount, $avgConsultMins, $queueLength, $workloadScore, $burnoutRisk);
                    if ($stmt->execute()) {
                        $extractedCount++;
                    }
                }
            }
        }

        return ['status' => 'SUCCESS', 'records_extracted' => $extractedCount];
    }

    /**
     * Helper to trace entity to simulation run_id
     */
    private function getRunIdForEntity($entityType, $entityId) {
        if ($entityId !== null) {
            $sql = "SELECT run_id FROM simulation_entities WHERE entity_type = '{$entityType}' AND entity_id = {$entityId} LIMIT 1";
            $res = $this->conn->query($sql);
            if ($res && ($row = $res->fetch_assoc())) {
                return $row['run_id'];
            }
        }
        $runRes = $this->conn->query("SELECT run_id FROM simulation_runs ORDER BY created_at DESC LIMIT 1");
        if ($runRes && ($rRow = $runRes->fetch_assoc())) {
            return $rRow['run_id'];
        }
        return 'SIM-20260817-001';
    }
}
