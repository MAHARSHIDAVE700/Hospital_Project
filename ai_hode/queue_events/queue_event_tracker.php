<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Queue Events Tracker
 * Path: ai_hode/queue_events/queue_event_tracker.php
 * 
 * Manages token generation, queue position tracking, estimated wait time,
 * called/completed events, and real-time token telemetry.
 */

class QueueEventTracker {

    /**
     * Generates a unique queue token for a patient flow.
     * Example format: OPD-104 or CARD-012
     *
     * @param string $deptCode Department code prefix (e.g. OPD, CARD, ER)
     * @param int $appointmentId
     * @return string
     */
    public static function generateToken($deptCode = 'OPD', $appointmentId = 0) {
        $prefix = strtoupper(trim($deptCode));
        $num = $appointmentId ? $appointmentId : rand(100, 999);
        return sprintf("%s-%03d", $prefix, $num % 1000);
    }

    /**
     * Calculates the current position of a patient in the department queue.
     *
     * @param NeonDB|mysqli $conn
     * @param int $departmentId
     * @param int $flowId
     * @return int Queue position (1 = next in line)
     */
    public static function calculateQueuePosition($conn, $departmentId, $flowId) {
        try {
            $sql = "SELECT COUNT(*) AS position 
                    FROM patient_flow 
                    WHERE department_id = ? 
                      AND current_stage IN ('CHECK_IN', 'TRIAGE', 'WAITING_FOR_CONSULTATION') 
                      AND flow_id <= ?";

            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("ii", $departmentId, $flowId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    return (int)$row['position'];
                }
            }
        } catch (Exception $e) {
            error_log("QueueEventTracker Position Error: " . $e->getMessage());
        }
        return 1;
    }

    /**
     * Estimates remaining wait time in seconds based on queue position and average consultation speed.
     *
     * @param int $queuePosition
     * @param int $avgConsultationSec Average consultation duration in seconds (default 600s / 10 mins)
     * @return int Estimated wait time in seconds
     */
    public static function calculateEstimatedWaitTime($queuePosition, $avgConsultationSec = 600) {
        $position = max(1, $queuePosition - 1);
        return $position * $avgConsultationSec;
    }

    /**
     * Records a queue lifecycle event (ISSUED, CALLED, DELAYED, CONSULTING, COMPLETED, NO_SHOW).
     *
     * @param NeonDB|mysqli $conn
     * @param int $flowId
     * @param string $queueToken
     * @param string $eventType
     * @param int|null $estimatedWaitSec
     * @param int|null $actualWaitSec
     * @return int|false
     */
    public static function recordEvent($conn, $flowId, $queueToken, $eventType, $estimatedWaitSec = null, $actualWaitSec = null) {
        try {
            $sql = "INSERT INTO queue_events 
                    (flow_id, queue_token, event_type, event_timestamp, estimated_wait_seconds, actual_wait_seconds) 
                    VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("QueueEventTracker Record Event Error: " . $conn->error);
                return false;
            }

            $stmt->bind_param("issii", $flowId, $queueToken, $eventType, $estimatedWaitSec, $actualWaitSec);
            $success = $stmt->execute();

            if ($success) {
                return $conn->insert_id;
            }
        } catch (Exception $e) {
            error_log("QueueEventTracker Exception: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Helper to issue initial token event for an appointment flow.
     *
     * @param NeonDB|mysqli $conn
     * @param int $flowId
     * @param int $departmentId
     * @param int $appointmentId
     * @return array Contains token, queue_position, estimated_wait_seconds
     */
    public static function initializeQueueToken($conn, $flowId, $departmentId, $appointmentId) {
        $deptCode = 'OPD';
        $deptQuery = $conn->query("SELECT department_name FROM departments WHERE department_id = {$departmentId}");
        if ($deptQuery && $dRow = $deptQuery->fetch_assoc()) {
            $deptCode = substr(strtoupper(preg_replace('/[^A-Za-z]/', '', $dRow['department_name'])), 0, 4);
        }

        $token = self::generateToken($deptCode, $appointmentId);
        $position = self::calculateQueuePosition($conn, $departmentId, $flowId);
        $estWaitSec = self::calculateEstimatedWaitTime($position);

        self::recordEvent($conn, $flowId, $token, 'ISSUED', $estWaitSec, null);

        return [
            'token' => $token,
            'queue_position' => $position,
            'estimated_wait_seconds' => $estWaitSec
        ];
    }
}
