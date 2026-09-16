<?php
/**
 * IPD Patient Journey Workflow Engine
 * Path: simulation/engines/IPDWorkflowEngine.php
 * 
 * Orchestrates automated IPD admissions, concurrency-safe bed allocations, clinical progress logging,
 * discharge summaries, bed releases, and simulation_entities tracking.
 */

if (file_exists(__DIR__ . '/../generators/IPDGenerator.php')) {
    require_once __DIR__ . '/../generators/IPDGenerator.php';
}

class IPDWorkflowEngine {

    /**
     * Execute IPD workflow for synthetic patients belonging to a simulation run.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param int|null $limit Optional patient limit (e.g. 5 for small test)
     * @return array Result summary
     */
    public static function executeIPDWorkflowForRun($conn, $runId, $limit = null) {
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

        // 2. Fetch active doctors
        $docRes = $conn->query("SELECT doctor_id, full_name FROM doctors ORDER BY doctor_id ASC");
        $doctors = [];
        if ($docRes && $docRes->num_rows > 0) {
            while ($row = $docRes->fetch_assoc()) {
                $doctors[] = $row;
            }
        }

        if (empty($doctors)) {
            return [
                'success' => false,
                'message' => "No active doctors found in system for IPD simulation."
            ];
        }

        $createdAdmissions = 0;
        $createdAllocations = 0;
        $createdLogs = 0;
        $completedDischarges = 0;
        $bedsReleased = 0;
        $failedJourneys = 0;

        $docCount = count($doctors);
        $patientIndex = 0;

        SimulationSafetyService::logEvent($conn, $runId, 'IPD_SIMULATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Starting IPD Workflow simulation for " . count($patients) . " patient(s)");

        foreach ($patients as $pat) {
            $patientId = intval($pat['patient_id']);
            $assignedDoc = $doctors[$patientIndex % $docCount];
            $doctorId = intval($assignedDoc['doctor_id']);
            $patientIndex++;

            $ipdPayload = IPDGenerator::generateIPDPayload();

            try {
                $conn->begin_transaction();

                // Step A: Find Available bed with FOR UPDATE row locking
                $bedRes = $conn->query("SELECT bed_id, bed_number FROM beds WHERE status = 'Available' ORDER BY bed_id ASC LIMIT 1 FOR UPDATE");
                if (!$bedRes || $bedRes->num_rows === 0) {
                    // Fallback: If all beds occupied, pick bed #1
                    $bedRes = $conn->query("SELECT bed_id, bed_number FROM beds ORDER BY bed_id ASC LIMIT 1 FOR UPDATE");
                }

                $selectedBed = $bedRes->fetch_assoc();
                $bedId = intval($selectedBed['bed_id']);

                // Step B: Mark Bed Occupied
                $conn->query("UPDATE beds SET status = 'Occupied' WHERE bed_id = $bedId");

                // Step C: Create Bed Allocation
                $stmtAlloc = $conn->prepare("INSERT INTO bed_allocations (bed_id, patient_id, admission_date, status) VALUES (?, ?, CURRENT_TIMESTAMP, 'Active')");
                $stmtAlloc->bind_param("ii", $bedId, $patientId);
                if (!$stmtAlloc->execute()) {
                    throw new Exception("Failed to insert bed allocation: " . $conn->error);
                }
                $allocationId = $conn->insert_id;
                $createdAllocations++;

                // Track bed_allocation
                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'bed_allocation', $allocationId, 'patient', $patientId)");

                // Step D: Create IPD Admission
                $stmtIpd = $conn->prepare("INSERT INTO ipd_admissions (patient_id, doctor_id, bed_id, admission_date, admission_reason, initial_bp, initial_temp, initial_pulse, initial_weight, status) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, 'Admitted')");
                $stmtIpd->bind_param("iiisssss", $patientId, $doctorId, $bedId, $ipdPayload['admission_reason'], $ipdPayload['initial_bp'], $ipdPayload['initial_temp'], $ipdPayload['initial_pulse'], $ipdPayload['initial_weight']);
                if (!$stmtIpd->execute()) {
                    throw new Exception("Failed to insert IPD admission: " . $conn->error);
                }
                $ipdId = $conn->insert_id;
                $createdAdmissions++;

                // Track ipd_admission
                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'ipd_admission', $ipdId, 'patient', $patientId)");

                // Step E: Create IPD Progress Log (Vitals & Clinical Notes)
                $stmtLog = $conn->prepare("INSERT INTO ipd_progress_logs (ipd_id, log_date, pulse_rate, temp_f, blood_pressure, clinical_notes, logged_by) VALUES (?, CURRENT_TIMESTAMP, ?, ?, ?, ?, 'Staff Nurse')");
                $stmtLog->bind_param("issss", $ipdId, $ipdPayload['progress_pulse'], $ipdPayload['progress_temp'], $ipdPayload['progress_bp'], $ipdPayload['clinical_notes']);
                if (!$stmtLog->execute()) {
                    throw new Exception("Failed to insert IPD progress log: " . $conn->error);
                }
                $logId = $conn->insert_id;
                $createdLogs++;

                // Track ipd_progress_log
                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'ipd_progress_log', $logId, 'ipd_admission', $ipdId)");

                // Step F: Complete Treatment & Discharge Patient
                $conn->query("UPDATE ipd_admissions SET status = 'Discharged', discharge_date = CURRENT_TIMESTAMP, discharge_summary = '{$ipdPayload['discharge_summary']}', discharge_status = 'Cured' WHERE ipd_id = $ipdId");
                $completedDischarges++;

                // Step G: Deactivate Bed Allocation & Release Bed
                $conn->query("UPDATE bed_allocations SET status = 'Discharged', discharge_date = CURRENT_TIMESTAMP WHERE allocation_id = $allocationId");
                $conn->query("UPDATE beds SET status = 'Available' WHERE bed_id = $bedId");
                $bedsReleased++;

                $conn->commit();

                SimulationSafetyService::logEvent($conn, $runId, 'IPD_JOURNEY_COMPLETED', 'SUCCESS', 'ipd_admission', $ipdId, "IPD journey complete for Patient #{$patientId} on Bed {$selectedBed['bed_number']}. Status: Discharged/Released.");

            } catch (Exception $e) {
                $conn->rollback();
                $failedJourneys++;
                SimulationSafetyService::logEvent($conn, $runId, 'IPD_JOURNEY_FAILED', 'FAILED', 'patient', $patientId, null, $e->getMessage());
            }
        }

        $totalPatientsProcessed = count($patients);
        SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $createdAdmissions, $failedJourneys);
        SimulationSafetyService::logEvent($conn, $runId, 'IPD_SIMULATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "IPD simulation finished: Processed={$totalPatientsProcessed}, Admissions={$createdAdmissions}, Allocations={$createdAllocations}, ProgressLogs={$createdLogs}, Discharges={$completedDischarges}, BedsReleased={$bedsReleased}, Failed={$failedJourneys}");

        return [
            'success'              => true,
            'run_id'               => $runId,
            'patients_processed'   => $totalPatientsProcessed,
            'admissions_created'   => $createdAdmissions,
            'bed_allocations'      => $createdAllocations,
            'progress_logs_created'=> $createdLogs,
            'discharges_completed' => $completedDischarges,
            'beds_released'        => $bedsReleased,
            'failed_journeys'      => $failedJourneys,
            'is_simulated'         => true
        ];
    }
}
