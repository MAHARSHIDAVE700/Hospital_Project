<?php
/**
 * AI-HODE (AI Hospital Operational Decision Engine)
 * Module: Predictive Analytics Engine (PHP Client Wrapper)
 * Path: ai_hode/predictions/predictive_analytics.php
 * 
 * Communicates with the FastAPI Prediction Microservice (POST /predict-wait-time).
 * Implements automated failover and fallback calculation if Python API is offline.
 */

class PredictiveAnalyticsEngine {

    private static $pythonApiUrl = "http://127.0.0.1:8000/predict-wait-time";

    /**
     * Calls the FastAPI microservice to get predicted wait time.
     *
     * @param string $department Department name (e.g., 'General OPD')
     * @param string $arrivalTime Arrival time (e.g., '09:30')
     * @param string $appointmentTime Appointment time (e.g., '10:00')
     * @param string $day Day of week (e.g., 'Monday')
     * @param int $queueLength Current queue length
     * @return array
     */
    public static function predictWaitTime($department, $arrivalTime, $appointmentTime, $day, $queueLength) {
        $payload = [
            'department' => $department,
            'arrival_time' => $arrivalTime,
            'appointment_time' => $appointmentTime,
            'day' => $day,
            'queue_length' => (int)$queueLength
        ];

        $jsonData = json_encode($payload);

        // Attempt HTTP POST via cURL
        $ch = curl_init(self::$pythonApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); // 3-second timeout

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && isset($result['success']) && $result['success']) {
                return $result;
            }
        }

        // Fallback Heuristic Computation if Python API is offline
        return self::fallbackWaitTimePredictor($department, $queueLength);
    }

    /**
     * Executes prediction and automatically persists result to PostgreSQL predictions table.
     */
    public static function predictAndLogWaitTime($conn, $department, $arrivalTime, $appointmentTime, $day, $queueLength, $targetEntityId = null) {
        require_once __DIR__ . '/prediction_logger.php';
        $res = self::predictWaitTime($department, $arrivalTime, $appointmentTime, $day, $queueLength);

        if ($res && isset($res['predicted_wait_minutes'])) {
            $predId = PredictionLogger::logPrediction(
                $conn,
                'WAIT_TIME',
                $targetEntityId,
                $res['predicted_wait_minutes'],
                $res['confidence_lower_minutes'] ?? null,
                $res['confidence_upper_minutes'] ?? null,
                $res['model_version'] ?? 'v1.2-xgboost-ensemble'
            );
            $res['prediction_id'] = $predId;
        }

        return $res;
    }

    /**
     * Fallback predictor used when Python API is unreachable.
     */
    private static function fallbackWaitTimePredictor($department, $queueLength) {
        $baseMins = 10.0;
        if (stripos($department, 'cardio') !== false) {
            $baseMins = 15.0;
        }
        $predictedMins = round(max(2.0, ($queueLength * $baseMins) + 3.0), 2);

        return [
            'success' => true,
            'department' => $department,
            'queue_length' => (int)$queueLength,
            'predicted_wait_minutes' => $predictedMins,
            'predicted_wait_seconds' => (int)($predictedMins * 60),
            'confidence_lower_minutes' => round($predictedMins * 0.85, 2),
            'confidence_upper_minutes' => round($predictedMins * 1.15, 2),
            'model_version' => 'v1.0-php-fallback',
            'prediction_timestamp' => date('Y-m-d\TH:i:s'),
            'message' => 'Predicted using fallback engine'
        ];
    }
}
