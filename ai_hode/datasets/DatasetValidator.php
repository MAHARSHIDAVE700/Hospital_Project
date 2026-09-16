<?php
/**
 * AI-HODE Dataset Validator Engine
 * Path: ai_hode/datasets/DatasetValidator.php
 */

require_once __DIR__ . '/../../includes/config.php';

class DatasetValidator {

    private $conn;

    public function __construct($dbConnection = null) {
        global $conn;
        $this->conn = $dbConnection ? $dbConnection : $conn;
    }

    /**
     * Validate all extracted dataset tables
     */
    public function validateAll() {
        $report = [
            'patient_arrivals' => $this->validatePatientArrivals(),
            'waiting_time'     => $this->validateWaitingTime(),
            'bed_occupancy'    => $this->validateBedOccupancy(),
            'doctor_workload'  => $this->validateDoctorWorkload(),
            'overall_status'   => 'PASS',
            'errors'           => []
        ];

        foreach (['patient_arrivals', 'waiting_time', 'bed_occupancy', 'doctor_workload'] as $dsKey) {
            if ($report[$dsKey]['status'] === 'FAIL') {
                $report['overall_status'] = 'FAIL';
                $report['errors'] = array_merge($report['errors'], $report[$dsKey]['errors']);
            }
        }

        return $report;
    }

    /**
     * Validate Patient Arrival Dataset
     */
    public function validatePatientArrivals() {
        $errors = [];
        $res = $this->conn->query("SELECT * FROM ai_dataset_patient_arrivals");
        $totalRows = $res ? $res->num_rows : 0;

        if ($totalRows > 0) {
            while ($row = $res->fetch_assoc()) {
                if (empty($row['arrival_date'])) {
                    $errors[] = "Arrival ID {$row['dataset_id']}: Null/Invalid arrival_date";
                }
                if ($row['arrival_hour'] < 0 || $row['arrival_hour'] > 23) {
                    $errors[] = "Arrival ID {$row['dataset_id']}: Out of bounds arrival_hour ({$row['arrival_hour']})";
                }
                if ($row['actual_arrival_count'] < 0) {
                    $errors[] = "Arrival ID {$row['dataset_id']}: Negative actual_arrival_count ({$row['actual_arrival_count']})";
                }
            }
        }

        return [
            'total_rows' => $totalRows,
            'status'     => empty($errors) ? 'PASS' : 'FAIL',
            'errors'     => $errors
        ];
    }

    /**
     * Validate Waiting Time Dataset
     */
    public function validateWaitingTime() {
        $errors = [];
        $res = $this->conn->query("SELECT * FROM ai_dataset_waiting_time");
        $totalRows = $res ? $res->num_rows : 0;

        if ($totalRows > 0) {
            while ($row = $res->fetch_assoc()) {
                if (empty($row['arrival_timestamp'])) {
                    $errors[] = "Waiting Time ID {$row['dataset_id']}: Null arrival_timestamp";
                }
                if ($row['actual_wait_minutes'] < 0) {
                    $errors[] = "Waiting Time ID {$row['dataset_id']}: Impossible negative waiting time ({$row['actual_wait_minutes']} mins)";
                }
                if ($row['doctor_id']) {
                    $docCheck = $this->conn->query("SELECT doctor_id FROM doctors WHERE doctor_id = " . (int)$row['doctor_id']);
                    if (!$docCheck || $docCheck->num_rows === 0) {
                        $errors[] = "Waiting Time ID {$row['dataset_id']}: Invalid doctor_id reference ({$row['doctor_id']})";
                    }
                }
            }
        }

        return [
            'total_rows' => $totalRows,
            'status'     => empty($errors) ? 'PASS' : 'FAIL',
            'errors'     => $errors
        ];
    }

    /**
     * Validate Bed Occupancy Dataset
     */
    public function validateBedOccupancy() {
        $errors = [];
        $res = $this->conn->query("SELECT * FROM ai_dataset_bed_occupancy");
        $totalRows = $res ? $res->num_rows : 0;

        if ($totalRows > 0) {
            while ($row = $res->fetch_assoc()) {
                if ($row['occupied_beds'] < 0 || $row['total_beds'] < 0) {
                    $errors[] = "Bed Occupancy ID {$row['dataset_id']}: Negative bed count";
                }
                if ($row['occupied_beds'] > $row['total_beds']) {
                    $errors[] = "Bed Occupancy ID {$row['dataset_id']}: Occupied beds ({$row['occupied_beds']}) > Total beds ({$row['total_beds']})";
                }
                $rate = (float)$row['occupancy_rate_pct'];
                if ($rate < 0.0 || $rate > 100.0) {
                    $errors[] = "Bed Occupancy ID {$row['dataset_id']}: Invalid occupancy rate percentage ({$rate}%)";
                }
            }
        }

        return [
            'total_rows' => $totalRows,
            'status'     => empty($errors) ? 'PASS' : 'FAIL',
            'errors'     => $errors
        ];
    }

    /**
     * Validate Doctor Workload Dataset
     */
    public function validateDoctorWorkload() {
        $errors = [];
        $res = $this->conn->query("SELECT * FROM ai_dataset_doctor_workload");
        $totalRows = $res ? $res->num_rows : 0;

        if ($totalRows > 0) {
            while ($row = $res->fetch_assoc()) {
                if ($row['workload_score'] < 0) {
                    $errors[] = "Doctor Workload ID {$row['dataset_id']}: Negative workload_score ({$row['workload_score']})";
                }
                if (!in_array($row['burnout_risk_level'], ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])) {
                    $errors[] = "Doctor Workload ID {$row['dataset_id']}: Invalid burnout_risk_level '{$row['burnout_risk_level']}'";
                }
                $docCheck = $this->conn->query("SELECT doctor_id FROM doctors WHERE doctor_id = " . (int)$row['doctor_id']);
                if (!$docCheck || $docCheck->num_rows === 0) {
                    $errors[] = "Doctor Workload ID {$row['dataset_id']}: Invalid doctor_id reference ({$row['doctor_id']})";
                }
            }
        }

        return [
            'total_rows' => $totalRows,
            'status'     => empty($errors) ? 'PASS' : 'FAIL',
            'errors'     => $errors
        ];
    }
}
