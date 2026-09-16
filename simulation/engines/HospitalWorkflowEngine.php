<?php
/**
 * Master Hospital Workflow Simulation Engine
 * Path: simulation/engines/HospitalWorkflowEngine.php
 * 
 * Orchestrates complete end-to-end hospital operational flows across synthetic patient generation,
 * OPD appointments, Queue/Patient Flow, Doctor Consultations, Prescriptions, Lab Tests, Pharmacy Dispenses,
 * IPD Bed Allocations, Discharges, Consolidated Invoicing, and Simulated Payments.
 */

if (file_exists(__DIR__ . '/../generators/PatientGenerator.php')) {
    require_once __DIR__ . '/../generators/PatientGenerator.php';
}
if (file_exists(__DIR__ . '/OPDWorkflowEngine.php')) {
    require_once __DIR__ . '/OPDWorkflowEngine.php';
}
if (file_exists(__DIR__ . '/LaboratoryWorkflowEngine.php')) {
    require_once __DIR__ . '/LaboratoryWorkflowEngine.php';
}
if (file_exists(__DIR__ . '/PharmacyWorkflowEngine.php')) {
    require_once __DIR__ . '/PharmacyWorkflowEngine.php';
}
if (file_exists(__DIR__ . '/IPDWorkflowEngine.php')) {
    require_once __DIR__ . '/IPDWorkflowEngine.php';
}
if (file_exists(__DIR__ . '/BillingWorkflowEngine.php')) {
    require_once __DIR__ . '/BillingWorkflowEngine.php';
}

class HospitalWorkflowEngine {

    /**
     * Preset Simulation Profiles
     */
    public static function getProfiles() {
        return [
            'quick_test' => [
                'name'               => 'Quick Test',
                'patient_count'      => 10,
                'opd_probability'    => 90,
                'lab_probability'    => 40,
                'pharmacy_probability'=> 60,
                'ipd_probability'    => 20,
                'billing_enabled'    => true
            ],
            'small_day' => [
                'name'               => 'Small Hospital Day',
                'patient_count'      => 25,
                'opd_probability'    => 85,
                'lab_probability'    => 35,
                'pharmacy_probability'=> 55,
                'ipd_probability'    => 15,
                'billing_enabled'    => true
            ],
            'normal_day' => [
                'name'               => 'Normal Hospital Day',
                'patient_count'      => 50,
                'opd_probability'    => 85,
                'lab_probability'    => 40,
                'pharmacy_probability'=> 60,
                'ipd_probability'    => 15,
                'billing_enabled'    => true
            ],
            'busy_day' => [
                'name'               => 'Busy Hospital Day',
                'patient_count'      => 100,
                'opd_probability'    => 90,
                'lab_probability'    => 50,
                'pharmacy_probability'=> 70,
                'ipd_probability'    => 20,
                'billing_enabled'    => true
            ],
            'high_load' => [
                'name'               => 'High Load / Stress Test',
                'patient_count'      => 200,
                'opd_probability'    => 90,
                'lab_probability'    => 50,
                'pharmacy_probability'=> 75,
                'ipd_probability'    => 25,
                'billing_enabled'    => true
            ]
        ];
    }

    /**
     * Execute Master Hospital Simulation run.
     *
     * @param NeonDB $conn
     * @param array $userConfig
     * @return array Simulation result summary
     */
    public static function executeMasterSimulation($conn, $userConfig = []) {
        // Default Configuration
        $config = array_merge([
            'patient_count'       => 10,
            'opd_probability'     => 85,
            'lab_probability'     => 40,
            'pharmacy_probability' => 60,
            'ipd_probability'     => 15,
            'billing_enabled'     => true,
            'profile_name'        => 'Custom Master Simulation'
        ], $userConfig);

        // Sanitize patient count limits (1 to 500)
        $patientCount = max(1, min(500, intval($config['patient_count'])));
        
        $runId = SimulationDatabaseService::generateRunId($conn);
        SimulationDatabaseService::createRun($conn, $runId, $config['profile_name'], 1, 'Medium', 'Master Simulation Controller');

        try {
            // Enable Safety Layer
            SimulationSafetyService::enableSafetyMode($runId);

            SimulationSafetyService::logEvent($conn, $runId, 'MASTER_SIMULATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Master Simulation started with profile '{$config['profile_name']}' for {$patientCount} patient(s)");

            // 1. Patient Generation
            $genResult = PatientGenerator::generatePatients($conn, $runId, $patientCount);
            if (!$genResult['success']) {
                throw new Exception("Patient generation failed: " . ($genResult['message'] ?? 'Unknown error'));
            }

            // 2. OPD Consultation & Prescription
            $opdLimit = max(1, intval(round($patientCount * ($config['opd_probability'] / 100))));
            $opdResult = OPDWorkflowEngine::executeOPDJourneyForRun($conn, $runId, $opdLimit);

            // 3. Laboratory Branching
            $labLimit = max(1, intval(round($patientCount * ($config['lab_probability'] / 100))));
            $labResult = LaboratoryWorkflowEngine::executeLabWorkflowForRun($conn, $runId, $labLimit);

            // 4. Pharmacy Branching
            $phmLimit = max(1, intval(round($patientCount * ($config['pharmacy_probability'] / 100))));
            $phmResult = PharmacyWorkflowEngine::executePharmacyWorkflowForRun($conn, $runId, $phmLimit);

            // 5. IPD Admission & Bed Allocation Branching
            $ipdLimit = max(1, intval(round($patientCount * ($config['ipd_probability'] / 100))));
            $ipdResult = IPDWorkflowEngine::executeIPDWorkflowForRun($conn, $runId, $ipdLimit);

            // 6. Billing & Payment Simulation
            $billingResult = ['invoices_created' => 0, 'total_revenue' => 0.00];
            if ($config['billing_enabled']) {
                $billingResult = BillingWorkflowEngine::executeBillingWorkflowForRun($conn, $runId);
            }

            // Summarize Results
            $totalProcessed = $genResult['patients_created'] ?? $patientCount;
            $opdCount       = $opdResult['appointments_created'] ?? 0;
            $labCount       = $labResult['requests_created'] ?? 0;
            $phmCount       = $phmResult['dispenses_created'] ?? 0;
            $ipdCount       = $ipdResult['admissions_created'] ?? 0;
            $invoicesCount  = $billingResult['invoices_created'] ?? 0;
            $totalRevenue   = $billingResult['total_revenue'] ?? 0.00;

            SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $totalProcessed, 0);

            SimulationSafetyService::logEvent($conn, $runId, 'MASTER_SIMULATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "Master simulation run {$runId} finished successfully. Patients={$totalProcessed}, OPD={$opdCount}, Lab={$labCount}, Pharmacy={$phmCount}, IPD={$ipdCount}, Invoices={$invoicesCount}, Revenue=INR {$totalRevenue}");

            return [
                'success'           => true,
                'run_id'            => $runId,
                'profile'           => $config['profile_name'],
                'patients_processed'=> $totalProcessed,
                'opd_visits'        => $opdCount,
                'lab_requests'      => $labCount,
                'pharmacy_dispenses'=> $phmCount,
                'ipd_admissions'    => $ipdCount,
                'invoices_created'  => $invoicesCount,
                'total_revenue'     => $totalRevenue,
                'is_simulated'      => true
            ];

        } catch (Exception $e) {
            SimulationDatabaseService::updateRunStatus($conn, $runId, 'FAILED', 0, 1);
            SimulationSafetyService::logEvent($conn, $runId, 'MASTER_SIMULATION_FAILED', 'FAILED', 'SimulationRun', null, $e->getMessage());

            return [
                'success' => false,
                'run_id'  => $runId,
                'message' => "Master simulation failed: " . $e->getMessage()
            ];
        } finally {
            // Restore Safety Layer
            SimulationSafetyService::disableSafetyMode();
        }
    }
}
