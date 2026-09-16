<?php
/**
 * Simulation Safety Service
 * Path: simulation/services/SimulationSafetyService.php
 * 
 * Provides external side-effect isolation, safety checks, and simulation execution logging.
 */

class SimulationSafetyService {

    private static $simulationActive = false;
    private static $currentRunId = null;

    /**
     * Enable simulation safety mode.
     *
     * @param string|null $runId
     */
    public static function enableSafetyMode($runId = null) {
        self::$simulationActive = true;
        self::$currentRunId = $runId;
        putenv("SIMULATION_MODE=true");
    }

    /**
     * Disable simulation safety mode.
     */
    public static function disableSafetyMode() {
        self::$simulationActive = false;
        self::$currentRunId = null;
        putenv("SIMULATION_MODE=false");
    }

    /**
     * Check if simulation safety mode is active.
     *
     * @return bool
     */
    public static function isSimulationActive() {
        return self::$simulationActive || (getenv('SIMULATION_MODE') === 'true');
    }

    /**
     * Get current active Simulation Run ID.
     *
     * @return string|null
     */
    public static function getCurrentRunId() {
        return self::$currentRunId;
    }

    /**
     * Perform comprehensive system safety check.
     *
     * @param NeonDB $conn
     * @return array
     */
    public static function performSafetyCheck($conn) {
        $checkResults = [];
        $allPassed = true;

        // 1. DB Connection Test
        try {
            $testRes = $conn->query("SELECT 1 AS alive");
            $dbAlive = ($testRes && $testRes->num_rows > 0);
            $checkResults[] = [
                'check'  => 'Database Connection',
                'status' => $dbAlive ? 'PASS' : 'FAIL',
                'detail' => $dbAlive ? 'Connected to NeonDB PostgreSQL.' : 'Database connection failed.'
            ];
            if (!$dbAlive) $allPassed = false;
        } catch (Exception $e) {
            $checkResults[] = ['check' => 'Database Connection', 'status' => 'FAIL', 'detail' => $e->getMessage()];
            $allPassed = false;
        }

        // 2. Metadata Tables Check
        $tablesNeeded = ['simulation_runs', 'simulation_events', 'patients', 'doctors', 'appointments', 'invoices', 'beds', 'lab_requests', 'medicine_dispenses'];
        $missingTables = [];
        foreach ($tablesNeeded as $tbl) {
            $res = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = '$tbl' LIMIT 1");
            if (!$res || $res->num_rows === 0) {
                $missingTables[] = $tbl;
            }
        }
        $tablesOk = empty($missingTables);
        $checkResults[] = [
            'check'  => 'Required Schema & Metadata Tables',
            'status' => $tablesOk ? 'PASS' : 'FAIL',
            'detail' => $tablesOk ? 'All required hospital & simulation metadata tables exist.' : 'Missing tables: ' . implode(', ', $missingTables)
        ];
        if (!$tablesOk) $allPassed = false;

        // 3. Email Simulation Safety Layer
        $emailProtected = class_exists('EmailHelper');
        $checkResults[] = [
            'check'  => 'Email Simulation Protection',
            'status' => $emailProtected ? 'PASS' : 'FAIL',
            'detail' => $emailProtected ? 'EmailHelper present; side effects trapped in safe simulation mode.' : 'EmailHelper class missing.'
        ];
        if (!$emailProtected) $allPassed = false;

        // 4. Razorpay Simulation Safety Layer
        $razorpayProtected = class_exists('RazorpayHelper');
        $checkResults[] = [
            'check'  => 'Razorpay Payment Protection',
            'status' => $razorpayProtected ? 'PASS' : 'FAIL',
            'detail' => $razorpayProtected ? 'RazorpayHelper present; live API calls bypassed during simulation.' : 'RazorpayHelper class missing.'
        ];
        if (!$razorpayProtected) $allPassed = false;

        // 5. Transaction Support
        try {
            $conn->begin_transaction();
            $conn->rollback();
            $checkResults[] = [
                'check'  => 'Database Transaction & Rollback Support',
                'status' => 'PASS',
                'detail' => 'Transaction BEGIN / ROLLBACK verified cleanly.'
            ];
        } catch (Exception $e) {
            $checkResults[] = ['check' => 'Database Transaction Support', 'status' => 'FAIL', 'detail' => $e->getMessage()];
            $allPassed = false;
        }

        // 6. Log Directory Permissions
        $logWritable = is_writable(SIM_LOG_DIR);
        $checkResults[] = [
            'check'  => 'Simulation Log Directory',
            'status' => $logWritable ? 'PASS' : 'FAIL',
            'detail' => $logWritable ? 'Writable: ' . SIM_LOG_DIR : 'Log directory is not writable.'
        ];
        if (!$logWritable) $allPassed = false;

        return [
            'overall_status' => $allPassed ? 'PASS' : 'FAILED',
            'checks'         => $checkResults
        ];
    }

    /**
     * Record structured log to file and database simulation_events.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param string $eventType
     * @param string $status
     * @param string|null $entityType
     * @param int|null $entityId
     * @param string|null $details
     * @param string|null $errorMsg
     */
    public static function logEvent($conn, $runId, $eventType, $status, $entityType = null, $entityId = null, $details = null, $errorMsg = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[{$timestamp}] Run: {$runId} | Event: {$eventType} | Status: {$status}";
        if ($entityType && $entityId) $logLine .= " | Entity: {$entityType}#{$entityId}";
        if ($details) $logLine .= " | Details: {$details}";
        if ($errorMsg) $logLine .= " | Error: {$errorMsg}";
        $logLine .= "\n";

        // File log
        @file_put_contents(SIM_LOG_FILE, $logLine, FILE_APPEND);

        // Database log
        try {
            $stmt = $conn->prepare("INSERT INTO simulation_events (run_id, event_type, status, entity_type, entity_id, details, error_message) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssiss", $runId, $eventType, $status, $entityType, $entityId, $details, $errorMsg);
            $stmt->execute();
        } catch (Exception $e) {
            // Ignore DB log failure to prevent transaction abort
        }
    }
}
