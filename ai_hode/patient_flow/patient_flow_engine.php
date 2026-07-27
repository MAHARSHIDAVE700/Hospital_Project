<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Patient Flow Engine
 * Path: ai_hode/patient_flow/patient_flow_engine.php
 * 
 * Manages patient stage transitions, dwell times, and operational telemetry.
 */

class PatientFlowEngine {

    /**
     * Automatically registers a new patient_flow record when an appointment is booked.
     *
     * @param NeonDB|mysqli $conn Database connection handle
     * @param int $patientId ID of the patient from HMS patients table
     * @param int $appointmentId ID of the newly created appointment
     * @param int $doctorId ID of the assigned doctor
     * @param int|null $departmentId Department ID (If null, automatically resolved from doctor record)
     * @param string $triagePriority Triage level ('P1_CRITICAL', 'P2_URGENT', 'P3_STANDARD', 'P4_NON_URGENT')
     * @return int|false Returns flow_id on success, false on failure
     */
    public static function createFlowForAppointment($conn, $patientId, $appointmentId, $doctorId, $departmentId = null, $triagePriority = 'P3_STANDARD') {
        try {
            // Validation
            if (empty($patientId) || empty($appointmentId) || empty($doctorId)) {
                error_log("PatientFlowEngine Error: Missing required fields (Patient ID, Appointment ID, Doctor ID).");
                return false;
            }

            // If department_id is not provided, fetch it from doctors table
            if (empty($departmentId)) {
                $docStmt = $conn->prepare("SELECT department_id FROM doctors WHERE doctor_id = ?");
                if ($docStmt) {
                    $docStmt->bind_param("i", $doctorId);
                    $docStmt->execute();
                    $res = $docStmt->get_result();
                    if ($res && $row = $res->fetch_assoc()) {
                        $departmentId = (int)$row['department_id'];
                    }
                }
            }

            // Default department fallback if not assigned to a doctor
            if (empty($departmentId)) {
                $departmentId = 1; // Default General OPD department
            }

            // Prepared Statement to insert into patient_flow
            $sql = "INSERT INTO patient_flow 
                    (patient_id, appointment_id, department_id, current_stage, triage_priority, stage_entry_time, assigned_doctor_id) 
                    VALUES (?, ?, ?, 'CHECK_IN', ?, CURRENT_TIMESTAMP, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("PatientFlowEngine Error: Failed to prepare statement - " . $conn->error);
                return false;
            }

            $stmt->bind_param("iiisi", $patientId, $appointmentId, $departmentId, $triagePriority, $doctorId);
            $executed = $stmt->execute();

            if ($executed) {
                // Get inserted flow_id
                $flowId = $conn->insert_id;
                error_log("PatientFlowEngine Success: Created patient_flow ID {$flowId} for Appointment {$appointmentId}");

                // Automatically generate queue token & ISSUED queue event (AI-HODE STEP 2)
                require_once __DIR__ . '/../queue_events/queue_event_tracker.php';
                QueueEventTracker::initializeQueueToken($conn, $flowId, $departmentId, $appointmentId);

                return $flowId;
            } else {
                error_log("PatientFlowEngine Error: Execution failed - " . $stmt->error);
                return false;
            }

        } catch (Exception $e) {
            error_log("PatientFlowEngine Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing patient flow record to a new operational stage.
     *
     * @param NeonDB|mysqli $conn
     * @param int $flowId
     * @param string $newStage
     * @param string|null $delayReason
     * @param bool $isBottleneck
     * @return bool
     */
    public static function updateStage($conn, $flowId, $newStage, $delayReason = null, $isBottleneck = false) {
        try {
            // First update exit time of current stage and calculate dwell time
            $sql = "UPDATE patient_flow 
                    SET stage_exit_time = CURRENT_TIMESTAMP,
                        dwell_time_seconds = EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - stage_entry_time))::INT,
                        current_stage = ?,
                        is_bottleneck = ?,
                        delay_reason = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE flow_id = ?";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return false;
            }

            $isBottleneckInt = $isBottleneck ? 1 : 0;
            $stmt->bind_param("sisi", $newStage, $isBottleneckInt, $delayReason, $flowId);
            $success = $stmt->execute();

            if ($success) {
                // Fetch assigned_doctor_id to recalculate workload (AI-HODE STEP 3)
                $docRes = $conn->query("SELECT assigned_doctor_id FROM patient_flow WHERE flow_id = {$flowId}");
                if ($docRes && $dRow = $docRes->fetch_assoc() && !empty($dRow['assigned_doctor_id'])) {
                    require_once __DIR__ . '/../doctor_workload/workload_balancer.php';
                    WorkloadBalancer::recalculateDoctorWorkload($conn, (int)$dRow['assigned_doctor_id']);
                }
            }

            return $success;
        } catch (Exception $e) {
            error_log("PatientFlowEngine Exception on updateStage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves the current patient flow record for an appointment ID.
     *
     * @param NeonDB|mysqli $conn
     * @param int $appointmentId
     * @return array|null
     */
    public static function getFlowByAppointment($conn, $appointmentId) {
        $stmt = $conn->prepare("SELECT * FROM patient_flow WHERE appointment_id = ? ORDER BY flow_id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $res = $stmt->get_result();
            return $res ? $res->fetch_assoc() : null;
        }
        return null;
    }
}
