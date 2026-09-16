<?php
/**
 * Laboratory Workflow Engine
 * Path: simulation/engines/LaboratoryWorkflowEngine.php
 * 
 * Orchestrates automated laboratory test requests, status progression, and result generation for synthetic patients.
 */

if (file_exists(__DIR__ . '/../generators/LaboratoryGenerator.php')) {
    require_once __DIR__ . '/../generators/LaboratoryGenerator.php';
}

class LaboratoryWorkflowEngine {

    /**
     * Execute laboratory workflow for synthetic patients belonging to a simulation run.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param int|null $limit Optional patient limit (e.g. 5 for small test)
     * @return array Result summary
     */
    public static function executeLabWorkflowForRun($conn, $runId, $limit = null) {
        // Enable safety mode to prevent external side effects
        SimulationSafetyService::enableSafetyMode($runId);

        // 1. Fetch synthetic patients belonging to this simulation run
        $sql = "SELECT e.entity_id AS patient_id, p.user_id 
                FROM simulation_entities e 
                JOIN patients p ON e.entity_id = p.patient_id 
                WHERE e.run_id = '$runId' AND e.entity_type = 'patient' 
                ORDER BY p.patient_id ASC";
        if ($limit !== null && intval($limit) > 0) {
            $sql .= " LIMIT " . intval($limit);
        }

        $res = $conn->query($sql);
        if (!$res || $res->num_rows === 0) {
            return [
                'success' => false,
                'message' => "No synthetic patients found for Simulation Run '$runId'."
            ];
        }

        $patients = [];
        while ($row = $res->fetch_assoc()) {
            $patients[] = $row;
        }

        // 2. Fetch master lab tests
        $labRes = $conn->query("SELECT test_id, test_name, test_code, sample_type, price FROM lab_tests ORDER BY test_id ASC");
        $labTests = [];
        if ($labRes && $labRes->num_rows > 0) {
            while ($row = $labRes->fetch_assoc()) {
                $labTests[] = $row;
            }
        }

        if (empty($labTests)) {
            return [
                'success' => false,
                'message' => "No master laboratory tests found in database."
            ];
        }

        // 3. Fetch active doctors
        $docRes = $conn->query("SELECT doctor_id, full_name FROM doctors ORDER BY doctor_id ASC");
        $doctors = [];
        if ($docRes && $docRes->num_rows > 0) {
            while ($row = $docRes->fetch_assoc()) {
                $doctors[] = $row;
            }
        }

        $createdRequests = 0;
        $completedTests = 0;
        $generatedResults = 0;
        $failedRequests = 0;

        $docCount = count($doctors);
        $labCount = count($labTests);
        $patientIndex = 0;

        SimulationSafetyService::logEvent($conn, $runId, 'LAB_SIMULATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Starting Laboratory Workflow simulation for " . count($patients) . " patient(s)");

        foreach ($patients as $pat) {
            $patientId = intval($pat['patient_id']);
            $assignedDoc = !empty($doctors) ? $doctors[$patientIndex % $docCount] : ['doctor_id' => 1];
            $doctorId = intval($assignedDoc['doctor_id']);
            $patientIndex++;

            // Assign 1 or 2 lab tests per patient
            $testsToAssign = rand(1, 2);
            for ($t = 0; $t < $testsToAssign; $t++) {
                $selectedTest = $labTests[($patientIndex + $t) % $labCount];
                $testId = intval($selectedTest['test_id']);
                $testName = $selectedTest['test_name'];

                $resultPayload = LaboratoryGenerator::generateResultForTest($testName);

                try {
                    $conn->begin_transaction();

                    $reqDate = date('Y-m-d H:i:s', strtotime('-' . rand(1, 10) . ' hours'));
                    $resDate = date('Y-m-d H:i:s');

                    $stmt = $conn->prepare("INSERT INTO lab_requests (patient_id, doctor_id, test_id, status, result_summary, result_details, request_date, result_date) VALUES (?, ?, ?, 'Completed', ?, ?, ?, ?)");
                    $stmt->bind_param("iiissss", $patientId, $doctorId, $testId, $resultPayload['summary'], $resultPayload['details'], $reqDate, $resDate);

                    if (!$stmt->execute()) {
                        throw new Exception("Failed to insert lab request: " . $conn->error);
                    }

                    $requestId = $conn->insert_id;
                    $createdRequests++;
                    $completedTests++;
                    $generatedResults++;

                    // Track in simulation_entities
                    $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'lab_request', $requestId, 'patient', $patientId)");

                    $conn->commit();

                    SimulationSafetyService::logEvent($conn, $runId, 'LAB_REQUEST_COMPLETED', 'SUCCESS', 'lab_request', $requestId, "Lab test '{$testName}' completed for Patient #{$patientId}");

                } catch (Exception $e) {
                    $conn->rollback();
                    $failedRequests++;
                    SimulationSafetyService::logEvent($conn, $runId, 'LAB_REQUEST_FAILED', 'FAILED', 'patient', $patientId, null, $e->getMessage());
                }
            }
        }

        $totalPatientsProcessed = count($patients);
        SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $createdRequests, $failedRequests);
        SimulationSafetyService::logEvent($conn, $runId, 'LAB_SIMULATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "Laboratory simulation complete: Patients={$totalPatientsProcessed}, Requests={$createdRequests}, Completed={$completedTests}, Results={$generatedResults}, Failed={$failedRequests}");

        return [
            'success'             => true,
            'run_id'              => $runId,
            'patients_processed'  => $totalPatientsProcessed,
            'lab_requests_created'=> $createdRequests,
            'tests_completed'     => $completedTests,
            'results_generated'   => $generatedResults,
            'failed_requests'     => $failedRequests,
            'is_simulated'        => true
        ];
    }
}
