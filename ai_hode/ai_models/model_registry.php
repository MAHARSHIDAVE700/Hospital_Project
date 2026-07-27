<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: AI Model Registry Engine
 * Path: ai_hode/ai_models/model_registry.php
 * 
 * Manages model registration, algorithm versions, hyperparameters,
 * active deployment status, and model lifecycle state in PostgreSQL.
 */

class ModelRegistry {

    /**
     * Registers a newly trained or updated AI model into the registry.
     *
     * @param NeonDB|mysqli $conn
     * @param string $modelName Model name (e.g. 'Wait Time Predictor')
     * @param string $algorithm Machine learning algorithm (e.g. 'XGBoost Regressor')
     * @param string $version Model semantic version (e.g. 'v1.2.0')
     * @param string $status Deployment status ('ACTIVE', 'INACTIVE', 'TRAINING')
     * @param array $parameters Model hyperparameters / metadata key-values
     * @return int|false Returns model_id on success
     */
    public static function registerModel($conn, $modelName, $algorithm, $version, $status = 'ACTIVE', $parameters = []) {
        try {
            $modelName = trim($modelName);
            $algorithm = trim($algorithm);
            $version = trim($version);
            $status = strtoupper(trim($status));

            $jsonParams = json_encode($parameters ? $parameters : [
                'n_estimators' => 100,
                'learning_rate' => 0.05,
                'max_depth' => 6,
                'framework' => 'XGBoost / Scikit-Learn'
            ]);

            // Deactivate previous versions if marking new model as ACTIVE
            if ($status === 'ACTIVE') {
                $deactStmt = $conn->prepare("UPDATE ai_models SET status = 'INACTIVE' WHERE model_name = ?");
                if ($deactStmt) {
                    $deactStmt->bind_param("s", $modelName);
                    $deactStmt->execute();
                }
            }

            $sql = "INSERT INTO ai_models 
                    (model_name, algorithm, version, status, parameters, last_trained_at) 
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                error_log("ModelRegistry Error preparing statement: " . $conn->error);
                return false;
            }

            $stmt->bind_param("sssss", $modelName, $algorithm, $version, $status, $jsonParams);
            $success = $stmt->execute();

            if ($success) {
                $modelId = $conn->insert_id;
                error_log("ModelRegistry Success: Registered model #{$modelId} ({$modelName} - {$version})");
                return $modelId;
            }

        } catch (Exception $e) {
            error_log("ModelRegistry Exception: " . $e->getMessage());
        }
        return false;
    }

    /**
     * Retrieves all registered AI models.
     *
     * @param NeonDB|mysqli $conn
     * @return array
     */
    public static function getAllModels($conn) {
        $sql = "SELECT model_id, model_name, algorithm, version, status, parameters, last_trained_at 
                FROM ai_models 
                ORDER BY model_id DESC";

        $res = $conn->query($sql);
        $models = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                if (is_string($row['parameters'])) {
                    $row['parameters_parsed'] = json_decode($row['parameters'], true);
                } else {
                    $row['parameters_parsed'] = $row['parameters'];
                }
                $models[] = $row;
            }
        }
        return $models;
    }

    /**
     * Updates model deployment status (ACTIVE, INACTIVE, TRAINING).
     */
    public static function updateStatus($conn, $modelId, $status) {
        $sql = "UPDATE ai_models SET status = ? WHERE model_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $status = strtoupper(trim($status));
            $stmt->bind_param("si", $status, $modelId);
            return $stmt->execute();
        }
        return false;
    }
}
