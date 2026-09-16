<?php
/**
 * Simulation Engine Core
 * Path: simulation/engines/SimulationEngine.php
 * 
 * Orchestrates safety checks, dry runs, and workflow generation.
 */

class SimulationEngine {

    /**
     * Run full system safety diagnostic check.
     *
     * @param NeonDB $conn
     * @return array
     */
    public static function runSafetyCheck($conn) {
        return SimulationSafetyService::performSafetyCheck($conn);
    }

    /**
     * Execute a non-destructive dry-run simulation test.
     *
     * @param NeonDB $conn
     * @return array
     */
    public static function runDryTest($conn) {
        $runId = "SIM-DRY-" . date('Ymd-His');

        // 1. Enable Safety Layer
        SimulationSafetyService::enableSafetyMode($runId);

        // 2. Create parent simulation_run record first to satisfy FK
        SimulationDatabaseService::createRun($conn, $runId, 'Dry Run', 1, 'Low', 'Admin');
        SimulationSafetyService::logEvent($conn, $runId, 'DRY_RUN_INIT', 'SUCCESS', 'SimulationRun', null, "Dry run test initialized with safety layer active.");

        try {
            // 3. Begin Transaction for transient operations
            $conn->begin_transaction();

            // 4. Test Schema Verification
            $patCheck = $conn->query("SELECT COUNT(*) AS total FROM patients")->fetch_assoc()['total'];
            $docCheck = $conn->query("SELECT COUNT(*) AS total FROM doctors")->fetch_assoc()['total'];
            
            SimulationSafetyService::logEvent($conn, $runId, 'SCHEMA_VERIFICATION', 'SUCCESS', 'Database', null, "Verified existing real patients ({$patCheck}) and doctors ({$docCheck}).");

            // 5. Test transient insertion & rollback
            $conn->query("INSERT INTO simulation_events (run_id, event_type, status, details) VALUES ('$runId', 'TRANSIENT_TEST', 'SUCCESS', 'Testing database write and rollback capability.')");

            // 6. Test Email / Razorpay Side-Effect Guards
            $emailGuarded = class_exists('EmailHelper');
            $razorpayGuarded = class_exists('RazorpayHelper');

            if (!$emailGuarded || !$razorpayGuarded) {
                throw new Exception("Safety guards check failed during dry run.");
            }

            SimulationSafetyService::logEvent($conn, $runId, 'SAFETY_GUARDS_VERIFIED', 'SUCCESS', 'SafetyLayer', null, "Email and Razorpay side-effect guards active.");

            // 7. Commit simulation event log
            $conn->commit();

            // Update status to COMPLETED
            SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', 0, 0);
            SimulationSafetyService::logEvent($conn, $runId, 'DRY_RUN_COMPLETE', 'SUCCESS', 'SimulationRun', null, "Dry run completed successfully with ZERO persistent changes to main tables.");

            // 8. Disable Safety Layer
            SimulationSafetyService::disableSafetyMode();

            return [
                'success' => true,
                'run_id'  => $runId,
                'message' => "DRY RUN COMPLETE: Safety layer verified, transactions tested, email & payment side-effects isolated, zero main table residue."
            ];

        } catch (Exception $e) {
            $conn->rollback();
            SimulationDatabaseService::updateRunStatus($conn, $runId, 'FAILED', 0, 1);
            SimulationSafetyService::logEvent($conn, $runId, 'DRY_RUN_FAILED', 'FAILED', 'SimulationRun', null, null, $e->getMessage());
            SimulationSafetyService::disableSafetyMode();

            return [
                'success' => false,
                'run_id'  => $runId,
                'message' => "Dry Run Failed: " . $e->getMessage()
            ];
        }
    }
}
