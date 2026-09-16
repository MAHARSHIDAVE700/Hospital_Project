<?php
/**
 * AI-HODE Prediction & Forecasting Service Bridge
 * Path: ai_hode/PredictionService.php
 */

require_once __DIR__ . '/../includes/config.php';

class PredictionService {

    private static function getPythonPath() {
        return 'py -3.11';
    }

    /**
     * Run prediction inference for a specified model target
     */
    public static function predict($targetType, $inputData = []) {
        $pythonCmd = self::getPythonPath();
        $scriptPath = escapeshellarg(__DIR__ . '/training/predict.py');
        $targetArg = escapeshellarg(strtoupper($targetType));
        $dataArg = escapeshellarg(json_encode($inputData));

        $command = "{$pythonCmd} {$scriptPath} {$targetArg} {$dataArg} 2>&1";

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
            'status' => 'ERROR',
            'return_code' => $returnVar,
            'raw_output' => $rawOutput
        ];
    }
}
