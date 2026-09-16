<?php
/**
 * AI-HODE Model Training Service Bridge
 * Path: ai_hode/ModelTrainingService.php
 */

require_once __DIR__ . '/../includes/config.php';

class ModelTrainingService {

    private static function getPythonPath() {
        // Preferred Python executable
        return 'py -3.11';
    }

    /**
     * Train all 4 AI-HODE machine learning models
     */
    public static function trainAllModels() {
        $pythonCmd = self::getPythonPath();
        $scriptPath = escapeshellarg(__DIR__ . '/training/train_models.py');

        $command = "{$pythonCmd} {$scriptPath} 2>&1";
        
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        $rawOutput = implode("\n", $output);
        $jsonStart = strpos($rawOutput, '{');
        if ($jsonStart !== false) {
            $jsonStr = substr($rawOutput, $jsonStart);
            $parsed = json_decode($jsonStr, true);
            if ($parsed && isset($parsed['status'])) {
                return $parsed;
            }
        }

        return [
            'status' => ($returnVar === 0) ? 'SUCCESS' : 'ERROR',
            'return_code' => $returnVar,
            'raw_output' => $rawOutput
        ];
    }
}
