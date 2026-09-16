<?php
/**
 * Simulation Controller API Endpoint
 * Path: simulation/simulation_controller.php
 */

require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'safety_check':
        $result = SimulationEngine::runSafetyCheck($conn);
        echo json_encode($result);
        break;

    case 'dry_run':
        $result = SimulationEngine::runDryTest($conn);
        echo json_encode($result);
        break;

    case 'generate_patients':
        $count = intval($_POST['count'] ?? $_GET['count'] ?? 10);
        $runId = SimulationDatabaseService::generateRunId($conn);
        SimulationDatabaseService::createRun($conn, $runId, 'Patient Generation', 1, 'Low', 'Admin');
        $result = PatientGenerator::generatePatients($conn, $runId, $count);
        echo json_encode($result);
        break;

    case 'run_opd_test':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = OPDWorkflowEngine::executeOPDJourneyForRun($conn, $targetRunId, 5);
        echo json_encode($result);
        break;

    case 'run_full_opd':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = OPDWorkflowEngine::executeOPDJourneyForRun($conn, $targetRunId);
        echo json_encode($result);
        break;

    case 'run_lab_test':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = LaboratoryWorkflowEngine::executeLabWorkflowForRun($conn, $targetRunId, 5);
        echo json_encode($result);
        break;

    case 'run_full_lab':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = LaboratoryWorkflowEngine::executeLabWorkflowForRun($conn, $targetRunId);
        echo json_encode($result);
        break;

    case 'run_pharmacy_test':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = PharmacyWorkflowEngine::executePharmacyWorkflowForRun($conn, $targetRunId, 5);
        echo json_encode($result);
        break;

    case 'run_full_pharmacy':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = PharmacyWorkflowEngine::executePharmacyWorkflowForRun($conn, $targetRunId);
        echo json_encode($result);
        break;

    case 'run_ipd_test':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = IPDWorkflowEngine::executeIPDWorkflowForRun($conn, $targetRunId, 5);
        echo json_encode($result);
        break;

    case 'run_full_ipd':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = IPDWorkflowEngine::executeIPDWorkflowForRun($conn, $targetRunId);
        echo json_encode($result);
        break;

    case 'run_billing_test':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = BillingWorkflowEngine::executeBillingWorkflowForRun($conn, $targetRunId, 5);
        echo json_encode($result);
        break;

    case 'run_full_billing':
        $runRes = $conn->query("SELECT run_id FROM simulation_entities WHERE entity_type = 'patient' ORDER BY entity_tracking_id DESC LIMIT 1");
        if (!$runRes || $runRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No simulation run with patients found. Please generate synthetic patients first.']);
            exit();
        }
        $targetRunId = $runRes->fetch_assoc()['run_id'];
        $result = BillingWorkflowEngine::executeBillingWorkflowForRun($conn, $targetRunId);
        echo json_encode($result);
        break;

    case 'run_master_quick_test':
        $profiles = HospitalWorkflowEngine::getProfiles();
        $config = $profiles['quick_test'];
        $result = HospitalWorkflowEngine::executeMasterSimulation($conn, $config);
        echo json_encode($result);
        break;

    case 'run_master_simulation':
        $profileKey = $_POST['profile'] ?? $_GET['profile'] ?? 'custom';
        $profiles   = HospitalWorkflowEngine::getProfiles();

        if (isset($profiles[$profileKey])) {
            $config = $profiles[$profileKey];
            $config['profile_name'] = $profiles[$profileKey]['name'];
        } else {
            $config = [
                'profile_name'       => 'Custom Simulation',
                'patient_count'      => max(1, min(500, intval($_POST['patient_count'] ?? 10))),
                'opd_probability'    => max(0, min(100, intval($_POST['opd_probability'] ?? 85))),
                'lab_probability'    => max(0, min(100, intval($_POST['lab_probability'] ?? 40))),
                'pharmacy_probability'=> max(0, min(100, intval($_POST['pharmacy_probability'] ?? 60))),
                'ipd_probability'    => max(0, min(100, intval($_POST['ipd_probability'] ?? 15))),
                'billing_enabled'    => isset($_POST['billing_enabled']) ? ($_POST['billing_enabled'] === 'true' || $_POST['billing_enabled'] === '1') : true
            ];
        }

        $result = HospitalWorkflowEngine::executeMasterSimulation($conn, $config);
        echo json_encode($result);
        break;

    case 'verify_master_integrity':
        // Check OPD, Lab, Pharmacy, IPD, and Billing Integrity
        $orphanAdmissions = intval($conn->query("SELECT COUNT(*) AS total FROM ipd_admissions i LEFT JOIN patients p ON i.patient_id = p.patient_id WHERE p.patient_id IS NULL")->fetch_assoc()['total']);
        $orphanAllocations = intval($conn->query("SELECT COUNT(*) AS total FROM bed_allocations b LEFT JOIN patients p ON b.patient_id = p.patient_id WHERE p.patient_id IS NULL")->fetch_assoc()['total']);
        $orphanInvoices    = intval($conn->query("SELECT COUNT(*) AS total FROM invoices i LEFT JOIN patients p ON i.patient_id = p.patient_id WHERE p.patient_id IS NULL")->fetch_assoc()['total']);
        $occupiedNoAlloc   = intval($conn->query("SELECT COUNT(*) AS total FROM beds b LEFT JOIN bed_allocations ba ON b.bed_id = ba.bed_id AND ba.status = 'Active' WHERE b.status = 'Occupied' AND ba.allocation_id IS NULL")->fetch_assoc()['total']);
        $negativeStockCount= intval($conn->query("SELECT COUNT(*) AS total FROM medicines WHERE stock_quantity < 0")->fetch_assoc()['total']);
        $negativeTotals    = intval($conn->query("SELECT COUNT(*) AS total FROM invoices WHERE total_amount < 0")->fetch_assoc()['total']);

        $passed = ($orphanAdmissions === 0 && $orphanAllocations === 0 && $orphanInvoices === 0 && $occupiedNoAlloc === 0 && $negativeStockCount === 0 && $negativeTotals === 0);

        echo json_encode([
            'success'                => $passed,
            'orphan_admissions'      => $orphanAdmissions,
            'orphan_bed_allocations' => $orphanAllocations,
            'orphan_invoices'        => $orphanInvoices,
            'occupied_beds_no_alloc' => $occupiedNoAlloc,
            'negative_stock_medicines'=> $negativeStockCount,
            'negative_invoice_totals'=> $negativeTotals,
            'master_integrity_status'=> $passed ? 'PASS' : 'FAIL'
        ]);
        break;

    case 'reset_run':
        $runId = trim($_POST['run_id'] ?? '');
        if (empty($runId)) {
            echo json_encode(['success' => false, 'message' => 'Missing simulation run_id parameter.']);
            exit();
        }
        $result = SimulationDataCleanupService::executeCleanup($conn, $runId);
        echo json_encode($result);
        break;

    case 'status':
        $recentRuns = SimulationDatabaseService::getRecentRuns($conn, 5);
        echo json_encode(['success' => true, 'recent_runs' => $recentRuns]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid or missing action parameter.']);
        break;
}
