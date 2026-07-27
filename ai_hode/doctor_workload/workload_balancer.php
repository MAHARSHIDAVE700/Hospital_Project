<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Doctor Workload Engine & Load Balancer
 * Path: ai_hode/doctor_workload/workload_balancer.php
 * 
 * Calculates real-time doctor active load, consultations completed,
 * average consultation duration, workload score (0-100), and burnout risk.
 */

class WorkloadBalancer {

    /**
     * Recalculates and logs doctor workload metrics whenever consultation status changes.
     *
     * @param NeonDB|mysqli $conn
     * @param int $doctorId
     * @return array|false Returns workload snapshot array on success
     */
    public static function recalculateDoctorWorkload($conn, $doctorId) {
        try {
            if (empty($doctorId)) {
                return false;
            }

            // 1. Calculate active patients assigned to doctor currently in OPD pipeline
            $activeStmt = $conn->prepare("
                SELECT COUNT(*) AS active_count 
                FROM patient_flow 
                WHERE assigned_doctor_id = ? 
                  AND current_stage IN ('CHECK_IN', 'TRIAGE', 'WAITING_FOR_CONSULTATION', 'IN_CONSULTATION')
            ");
            $activeStmt->bind_param("i", $doctorId);
            $activeStmt->execute();
            $activeRes = $activeStmt->get_result();
            $activePatients = ($activeRes && $aRow = $activeRes->fetch_assoc()) ? (int)$aRow['active_count'] : 0;

            // 2. Calculate consultations completed today
            $completedStmt = $conn->prepare("
                SELECT COUNT(*) AS completed_count 
                FROM patient_flow 
                WHERE assigned_doctor_id = ? 
                  AND current_stage IN ('DISCHARGED', 'LAB_PHARMACY', 'BILLING')
                  AND DATE(stage_entry_time) = CURRENT_DATE()
            ");
            $completedStmt->bind_param("i", $doctorId);
            $completedStmt->execute();
            $completedRes = $completedStmt->get_result();
            $completedToday = ($completedRes && $cRow = $completedRes->fetch_assoc()) ? (int)$cRow['completed_count'] : 0;

            // 3. Calculate average consultation time in seconds for today's completed patients
            $avgTimeStmt = $conn->prepare("
                SELECT AVG(dwell_time_seconds) AS avg_time 
                FROM patient_flow 
                WHERE assigned_doctor_id = ? 
                  AND current_stage IN ('DISCHARGED', 'LAB_PHARMACY', 'BILLING')
                  AND dwell_time_seconds IS NOT NULL
                  AND DATE(stage_entry_time) = CURRENT_DATE()
            ");
            $avgTimeStmt->bind_param("i", $doctorId);
            $avgTimeStmt->execute();
            $avgTimeRes = $avgTimeStmt->get_result();
            $avgConsultationSec = ($avgTimeRes && $tRow = $avgTimeRes->fetch_assoc() && $tRow['avg_time'] !== null) 
                ? (int)round($tRow['avg_time']) 
                : 600; // 10 minutes default fallback

            // 4. Calculate Workload Score (0.00 to 100.00)
            // Weighting formula: (Active Patients * 15) + (Completed Today * 5)
            $rawScore = ($activePatients * 15.0) + ($completedToday * 5.0);
            $workloadScore = min(100.00, max(0.00, round($rawScore, 2)));

            // 5. Evaluate Burnout Risk Level
            $burnoutRisk = 'LOW';
            if ($workloadScore >= 90.0) {
                $burnoutRisk = 'CRITICAL';
            } elseif ($workloadScore >= 70.0) {
                $burnoutRisk = 'HIGH';
            } elseif ($workloadScore >= 40.0) {
                $burnoutRisk = 'MEDIUM';
            }

            // 6. Insert new telemetry row into doctor_workload
            $sql = "INSERT INTO doctor_workload 
                    (doctor_id, active_patients, consultations_completed_today, avg_consultation_time_seconds, workload_score, burnout_risk_level, recorded_at) 
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("WorkloadBalancer Error preparing statement: " . $conn->error);
                return false;
            }

            $stmt->bind_param("iiiids", $doctorId, $activePatients, $completedToday, $avgConsultationSec, $workloadScore, $burnoutRisk);
            $stmt->execute();

            return [
                'doctor_id' => $doctorId,
                'active_patients' => $activePatients,
                'consultations_completed_today' => $completedToday,
                'avg_consultation_time_seconds' => $avgConsultationSec,
                'workload_score' => $workloadScore,
                'burnout_risk_level' => $burnoutRisk
            ];

        } catch (Exception $e) {
            error_log("WorkloadBalancer Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the latest workload status for all active doctors.
     */
    public static function getLatestWorkloadSummary($conn) {
        $sql = "SELECT DISTINCT ON (dw.doctor_id)
                    dw.workload_id,
                    dw.doctor_id,
                    dw.active_patients,
                    dw.consultations_completed_today,
                    dw.avg_consultation_time_seconds,
                    dw.workload_score,
                    dw.burnout_risk_level,
                    dw.recorded_at,
                    doc.full_name AS doctor_name,
                    d.department_name
                FROM doctor_workload dw
                JOIN doctors doc ON dw.doctor_id = doc.doctor_id
                LEFT JOIN departments d ON doc.department_id = d.department_id
                ORDER BY dw.doctor_id, dw.recorded_at DESC";

        $res = $conn->query($sql);
        $doctors = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $doctors[] = $r;
            }
        }
        return $doctors;
    }
}
