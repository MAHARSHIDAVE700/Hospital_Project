<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Prediction Persistence & Telemetry Logger
 * Path: ai_hode/predictions/prediction_logger.php
 * 
 * Persists all AI model outputs (WAIT_TIME, ARRIVAL, QUEUE, WORKLOAD)
 * into PostgreSQL predictions table.
 */

class PredictionLogger {

    private static $allowedTypes = ['WAIT_TIME', 'ARRIVAL', 'QUEUE', 'WORKLOAD'];

    /**
     * Persists an AI prediction output to the database.
     *
     * @param NeonDB|mysqli $conn
     * @param string $targetType Prediction category ('WAIT_TIME', 'ARRIVAL', 'QUEUE', 'WORKLOAD')
     * @param string|int|null $targetEntityId Entity identifier (e.g., flow_id, dept_id, doctor_id)
     * @param float $predictedValue Numerical output value
     * @param float|null $confidenceLower Lower bound confidence threshold
     * @param float|null $confidenceUpper Upper bound confidence threshold
     * @param string $modelVersion Algorithm/Model version tag
     * @return int|false Returns prediction_id on success
     */
    public static function logPrediction($conn, $targetType, $targetEntityId, $predictedValue, $confidenceLower = null, $confidenceUpper = null, $modelVersion = 'v1.2-xgboost-ensemble') {
        try {
            $targetType = strtoupper(trim($targetType));
            if (!in_array($targetType, self::$allowedTypes)) {
                error_log("PredictionLogger Error: Invalid target_type '{$targetType}'. Must be one of: " . implode(', ', self::$allowedTypes));
                return false;
            }

            $sql = "INSERT INTO predictions 
                    (target_type, target_entity_id, predicted_value, confidence_lower, confidence_upper, prediction_time, model_version) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("PredictionLogger Error preparing statement: " . $conn->error);
                return false;
            }

            $entityIdStr = $targetEntityId !== null ? (string)$targetEntityId : null;
            $predVal = floatval($predictedValue);
            $confLow = $confidenceLower !== null ? floatval($confidenceLower) : null;
            $confHigh = $confidenceUpper !== null ? floatval($confidenceUpper) : null;

            $stmt->bind_param("ssddds", $targetType, $entityIdStr, $predVal, $confLow, $confHigh, $modelVersion);
            $success = $stmt->execute();

            if ($success) {
                $predId = $conn->insert_id;
                error_log("PredictionLogger Success: Persisted {$targetType} prediction #{$predId} (Value: {$predVal})");
                return $predId;
            }

        } catch (Exception $e) {
            error_log("PredictionLogger Exception: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Retrieves the recent telemetry history of predictions.
     *
     * @param NeonDB|mysqli $conn
     * @param int $limit
     * @return array
     */
    public static function getLatestPredictions($conn, $limit = 50) {
        $sql = "SELECT prediction_id, target_type, target_entity_id, predicted_value, 
                       confidence_lower, confidence_upper, prediction_time, model_version 
                FROM predictions 
                ORDER BY prediction_id DESC LIMIT " . intval($limit);

        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        return $rows;
    }
}
