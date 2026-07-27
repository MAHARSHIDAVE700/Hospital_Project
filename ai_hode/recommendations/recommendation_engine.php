<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Operational Recommendation Engine
 * Path: ai_hode/recommendations/recommendation_engine.php
 * 
 * Heuristic & AI Rule Engine that evaluates real-time queue, wait-time, 
 * and doctor workload telemetry to generate actionable operational recommendations.
 */

class RecommendationEngine {

    /**
     * Evaluates all operational telemetry rules and generates pending recommendations.
     *
     * @param NeonDB|mysqli $conn
     * @return array Array of newly generated recommendations
     */
    public static function evaluateRulesAndGenerate($conn) {
        $generated = [];

        try {
            // ----------------------------------------------------------------
            // Rule 1: High Doctor Workload & Burnout Risk (STAFF_DISPATCH)
            // ----------------------------------------------------------------
            $docRes = $conn->query("
                SELECT dw.doctor_id, dw.workload_score, dw.active_patients, doc.full_name AS doctor_name, d.department_name
                FROM doctor_workload dw
                JOIN doctors doc ON dw.doctor_id = doc.doctor_id
                LEFT JOIN departments d ON doc.department_id = d.department_id
                WHERE dw.workload_score >= 70.0 OR dw.active_patients >= 4
                ORDER BY dw.workload_id DESC LIMIT 5
            ");

            if ($docRes) {
                while ($doc = $docRes->fetch_assoc()) {
                    $doctorName = $doc['doctor_name'];
                    $deptName = $doc['department_name'] ?? 'General OPD';
                    $score = floatval($doc['workload_score']);

                    $title = "High Doctor Workload Alert - {$doctorName}";
                    $urgency = ($score >= 90.0) ? 'CRITICAL' : 'HIGH';
                    $details = "Dr. {$doctorName} in {$deptName} is operating at {$score}% capacity with {$doc['active_patients']} active patients. Recommended Action: Open an additional consultation room or reassign non-critical patients to an available colleague.";

                    // Check if duplicate pending recommendation exists
                    if (!self::recommendationExists($conn, $title)) {
                        $recId = self::saveRecommendation($conn, 'STAFF_DISPATCH', $title, $details, $urgency, 85.00);
                        if ($recId) {
                            $generated[] = ['id' => $recId, 'title' => $title, 'urgency' => $urgency];
                        }
                    }
                }
            }

            // ----------------------------------------------------------------
            // Rule 2: Excessive Queue Wait Time (QUEUE_REROUTE)
            // ----------------------------------------------------------------
            $waitRes = $conn->query("
                SELECT predicted_value, target_entity_id 
                FROM predictions 
                WHERE target_type = 'WAIT_TIME' AND predicted_value >= 40.0
                ORDER BY prediction_id DESC LIMIT 3
            ");

            if ($waitRes) {
                while ($w = $waitRes->fetch_assoc()) {
                    $predictedMins = floatval($w['predicted_value']);
                    $title = "Queue Delay Alert - Predicted Wait {$predictedMins} min";
                    $details = "Predicted patient wait time has reached {$predictedMins} minutes. Recommended Action: Fast-track P1/P2 emergency triage patients and temporarily pause non-urgent (P4) appointment check-ins.";

                    if (!self::recommendationExists($conn, $title)) {
                        $recId = self::saveRecommendation($conn, 'QUEUE_REROUTE', $title, $details, 'HIGH', 90.00);
                        if ($recId) {
                            $generated[] = ['id' => $recId, 'title' => $title, 'urgency' => 'HIGH'];
                        }
                    }
                }
            }

            // ----------------------------------------------------------------
            // Rule 3: Operational Bottleneck Alert (TRIAGE_PRIORITY)
            // ----------------------------------------------------------------
            $bottleneckRes = $conn->query("
                SELECT COUNT(*) AS bottleneck_count 
                FROM patient_flow 
                WHERE is_bottleneck = TRUE AND stage_exit_time IS NULL
            ");

            if ($bottleneckRes && $bRow = $bottleneckRes->fetch_assoc()) {
                $bCount = (int)$bRow['bottleneck_count'];
                if ($bCount >= 2) {
                    $title = "Operational Bottleneck Trigger - {$bCount} Active Delays";
                    $details = "Detected {$bCount} active patient flows flagged with bottleneck SLA breaches. Recommended Action: Dispatch auxiliary nursing staff to triage desk to clear intake backlog.";

                    if (!self::recommendationExists($conn, $title)) {
                        $recId = self::saveRecommendation($conn, 'TRIAGE_PRIORITY', $title, $details, 'HIGH', 88.00);
                        if ($recId) {
                            $generated[] = ['id' => $recId, 'title' => $title, 'urgency' => 'HIGH'];
                        }
                    }
                }
            }

        } catch (Exception $e) {
            error_log("RecommendationEngine Exception: " . $e->getMessage());
        }

        return $generated;
    }

    /**
     * Persists a recommendation entry into PostgreSQL recommendations table.
     */
    public static function saveRecommendation($conn, $category, $title, $actionDetails, $urgencyLevel = 'MEDIUM', $impactScore = 80.00) {
        try {
            $sql = "INSERT INTO recommendations 
                    (category, title, action_details, urgency_level, impact_score, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'PENDING', CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) return false;

            $impact = floatval($impactScore);
            $stmt->bind_param("ssssd", $category, $title, $actionDetails, $urgencyLevel, $impact);
            $success = $stmt->execute();
            return $success ? $conn->insert_id : false;

        } catch (Exception $e) {
            error_log("RecommendationEngine Save Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if a pending recommendation with the same title already exists.
     */
    private static function recommendationExists($conn, $title) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM recommendations WHERE title = ? AND status = 'PENDING'");
        if ($stmt) {
            $stmt->bind_param("s", $title);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                return (int)$row['total'] > 0;
            }
        }
        return false;
    }

    /**
     * Gets all recommendations by status.
     */
    public static function getRecommendations($conn, $status = 'PENDING') {
        $sql = "SELECT recommendation_id, category, title, action_details, urgency_level, impact_score, status, created_at 
                FROM recommendations 
                WHERE status = ? 
                ORDER BY recommendation_id DESC";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("s", $status);
            $stmt->execute();
            $res = $stmt->get_result();
            $rows = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
            }
            return $rows;
        }
        return [];
    }
}
