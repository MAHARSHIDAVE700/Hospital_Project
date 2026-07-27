<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Decision Logging Engine
 * Path: ai_hode/decision_logs/decision_logger.php
 * 
 * Persists administrator interactions (ACCEPTED, REJECTED, IGNORED)
 * with AI operational recommendations into PostgreSQL decision_logs table.
 */

class DecisionLogger {

    private static $allowedActions = ['ACCEPTED', 'REJECTED', 'IGNORED'];

    /**
     * Records a decision action taken on an AI recommendation.
     *
     * @param NeonDB|mysqli $conn
     * @param int $recommendationId
     * @param string $actionTaken Action ('ACCEPTED', 'REJECTED', 'IGNORED')
     * @param int|null $executedBy HMS user ID of administrator/operator
     * @param string|null $outcomeNotes Notes or justification for decision
     * @param string $executionType Execution mode ('MANUAL' or 'AUTOMATED')
     * @return int|false Returns log_id on success, false on failure
     */
    public static function logDecision($conn, $recommendationId, $actionTaken, $executedBy = null, $outcomeNotes = null, $executionType = 'MANUAL') {
        try {
            $actionTaken = strtoupper(trim($actionTaken));
            if (!in_array($actionTaken, self::$allowedActions)) {
                error_log("DecisionLogger Error: Invalid action_taken '{$actionTaken}'. Allowed: ACCEPTED, REJECTED, IGNORED");
                return false;
            }

            // 1. Insert decision log entry
            $sql = "INSERT INTO decision_logs 
                    (recommendation_id, action_taken, executed_by, execution_type, outcome_notes, timestamp) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("DecisionLogger Error preparing statement: " . $conn->error);
                return false;
            }

            $recId = intval($recommendationId);
            $userId = $executedBy !== null ? intval($executedBy) : null;
            $notes = !empty($outcomeNotes) ? trim($outcomeNotes) : "Decision executed by administrator ({$actionTaken})";

            $stmt->bind_param("isis", $recId, $actionTaken, $userId, $executionType, $notes);
            $success = $stmt->execute();

            if ($success) {
                $logId = $conn->insert_id;

                // 2. Update recommendation status in recommendations table
                $recStatus = ($actionTaken === 'ACCEPTED') ? 'ACCEPTED' : (($actionTaken === 'REJECTED') ? 'REJECTED' : 'EXPIRED');
                $updateStmt = $conn->prepare("UPDATE recommendations SET status = ? WHERE recommendation_id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $recStatus, $recId);
                    $updateStmt->execute();
                }

                error_log("DecisionLogger Success: Logged decision #{$logId} for Recommendation #{$recId} ({$actionTaken})");
                return $logId;
            }

        } catch (Exception $e) {
            error_log("DecisionLogger Exception: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Fetches decision audit logs history with joined recommendation details.
     *
     * @param NeonDB|mysqli $conn
     * @param int $limit
     * @return array
     */
    public static function getDecisionLogs($conn, $limit = 50) {
        $sql = "SELECT dl.log_id, dl.recommendation_id, dl.action_taken, dl.executed_by, 
                       dl.execution_type, dl.outcome_notes, dl.timestamp,
                       r.title AS rec_title, r.category AS rec_category, r.urgency_level,
                       u.full_name AS user_name
                FROM decision_logs dl
                LEFT JOIN recommendations r ON dl.recommendation_id = r.recommendation_id
                LEFT JOIN users u ON dl.executed_by = u.id
                ORDER BY dl.log_id DESC LIMIT " . intval($limit);

        $res = $conn->query($sql);
        $logs = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $logs[] = $row;
            }
        }
        return $logs;
    }
}
