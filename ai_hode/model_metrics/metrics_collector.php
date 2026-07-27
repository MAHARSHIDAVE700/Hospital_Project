<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Model Evaluation Metrics Collector
 * Path: ai_hode/model_metrics/metrics_collector.php
 * 
 * Persists machine learning evaluation metrics (MAE, RMSE, Accuracy/R², Latency)
 * into PostgreSQL model_metrics table.
 */

class MetricsCollector {

    /**
     * Records evaluation telemetry for an AI model.
     *
     * @param NeonDB|mysqli $conn
     * @param int $modelId Foreign key referencing ai_models.model_id
     * @param float|null $mae Mean Absolute Error
     * @param float|null $rmse Root Mean Squared Error
     * @param float|null $accuracyScore Accuracy or R-squared score (0.0 to 1.0)
     * @param int|null $latencyMs Inference latency in milliseconds
     * @return int|false Returns metric_id on success
     */
    public static function recordMetrics($conn, $modelId, $mae = null, $rmse = null, $accuracyScore = null, $latencyMs = null) {
        try {
            $sql = "INSERT INTO model_metrics 
                    (model_id, mae, rmse, accuracy_score, latency_ms, evaluated_at) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("MetricsCollector Error preparing statement: " . $conn->error);
                return false;
            }

            $mId = intval($modelId);
            $valMae = $mae !== null ? floatval($mae) : null;
            $valRmse = $rmse !== null ? floatval($rmse) : null;
            $valAcc = $accuracyScore !== null ? floatval($accuracyScore) : null;
            $valLat = $latencyMs !== null ? intval($latencyMs) : null;

            $stmt->bind_param("idddi", $mId, $valMae, $valRmse, $valAcc, $valLat);
            $success = $stmt->execute();

            if ($success) {
                $metricId = $conn->insert_id;
                error_log("MetricsCollector Success: Logged evaluation #{$metricId} for Model #{$mId}");
                return $metricId;
            }

        } catch (Exception $e) {
            error_log("MetricsCollector Exception: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Gets the latest performance evaluation records joined with model metadata.
     *
     * @param NeonDB|mysqli $conn
     * @param int $limit
     * @return array
     */
    public static function getEvaluationHistory($conn, $limit = 50) {
        $sql = "SELECT mm.metric_id, mm.model_id, mm.mae, mm.rmse, mm.accuracy_score, 
                       mm.latency_ms, mm.evaluated_at,
                       m.model_name, m.algorithm, m.version
                FROM model_metrics mm
                JOIN ai_models m ON mm.model_id = m.model_id
                ORDER BY mm.metric_id DESC LIMIT " . intval($limit);

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
