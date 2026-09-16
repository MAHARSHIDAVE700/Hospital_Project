<?php
/**
 * Automated OPD Workflow Engine
 * Path: simulation/engines/OPDWorkflowEngine.php
 * 
 * Orchestrates complete synthetic OPD patient journeys using existing HMS database schema
 * and AI-HODE patient flow & queue telemetry.
 */

if (file_exists(__DIR__ . '/../../ai_hode/patient_flow/patient_flow_engine.php')) {
    require_once __DIR__ . '/../../ai_hode/patient_flow/patient_flow_engine.php';
}

class OPDWorkflowEngine {

    /**
     * Execute full automated OPD journey for synthetic patients belonging to a simulation run.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param int|null $limit Optional patient limit (e.g. 5 for small test)
     * @return array Result summary
     */
    public static function executeOPDJourneyForRun($conn, $runId, $limit = null) {
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

        // 2. Fetch existing active doctors
        $docRes = $conn->query("SELECT doctor_id, department_id, full_name FROM doctors ORDER BY doctor_id ASC");
        $doctors = [];
        if ($docRes && $docRes->num_rows > 0) {
            while ($row = $docRes->fetch_assoc()) {
                $doctors[] = $row;
            }
        }

        if (empty($doctors)) {
            return [
                'success' => false,
                'message' => "No active doctors found in system for OPD simulation."
            ];
        }

        $reasons = [
            'Fever and severe body ache',
            'General routine health checkup',
            'Persistent headache and fatigue',
            'Joint pain and swelling',
            'Lower back pain',
            'Follow-up post consultation',
            'Acute cold, cough and congestion'
        ];

        $diagnoses = [
            'Viral Fever with dehydration',
            'Upper Respiratory Tract Infection',
            'Tension Headache',
            'Mild Osteoarthritis',
            'Lumbar Strain',
            'Essential Hypertension'
        ];

        $medications = [
            'Paracetamol 650mg TDS x 3 days, ORS Sachet BD',
            'Amoxicillin 500mg TDS x 5 days, Cetirizine 10mg HS',
            'Naproxen 250mg BD, Pantoprazole 40mg OD',
            'Calcium + Vitamin D3 OD, Diclofenac Gel for topical use',
            'Telmisartan 40mg OD in morning'
        ];

        $slotTimes = ['09:00 AM', '09:30 AM', '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM', '02:00 PM', '02:30 PM', '03:00 PM'];

        $createdAppointments = 0;
        $createdFlows = 0;
        $createdConsultations = 0;
        $createdMedicalRecords = 0;
        $createdPrescriptions = 0;
        $failedJourneys = 0;

        $docCount = count($doctors);
        $patientIndex = 0;

        SimulationSafetyService::logEvent($conn, $runId, 'OPD_SIMULATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Starting OPD workflow simulation for " . count($patients) . " patient(s)");

        foreach ($patients as $pat) {
            $patientId = intval($pat['patient_id']);
            
            // Distribute patients across available doctors
            $assignedDoc = $doctors[$patientIndex % $docCount];
            $doctorId = intval($assignedDoc['doctor_id']);
            $departmentId = intval($assignedDoc['department_id'] ?? 1);
            $patientIndex++;

            $apptDate = date('Y-m-d', strtotime('+' . rand(0, 3) . ' days'));
            $apptTime = $slotTimes[array_rand($slotTimes)];
            $reason = $reasons[array_rand($reasons)];
            $diagnosis = $diagnoses[array_rand($diagnoses)];
            $meds = $medications[array_rand($medications)];

            try {
                $conn->begin_transaction();

                // Step A: Create Appointment
                $stmt = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, fee_status, opd_fee_paid) VALUES (?, ?, ?, ?, 'Approved', 'Pending', 200.00)");
                $stmt->bind_param("iiss", $patientId, $doctorId, $apptDate, $apptTime);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert appointment: " . $conn->error);
                }
                $appointmentId = $conn->insert_id;
                $createdAppointments++;

                // Track appointment
                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'appointment', $appointmentId, 'patient', $patientId)");

                // Step B: AI-HODE Patient Flow & Queue Initialization
                $flowId = false;
                if (class_exists('PatientFlowEngine')) {
                    $flowId = PatientFlowEngine::createFlowForAppointment($conn, $patientId, $appointmentId, $doctorId, $departmentId, 'P3_STANDARD');
                }

                if ($flowId) {
                    $createdFlows++;
                    $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'patient_flow', $flowId, 'appointment', $appointmentId)");

                    // Progress queue stage
                    PatientFlowEngine::updateStage($conn, $flowId, 'WAITING_OPD');
                    PatientFlowEngine::updateStage($conn, $flowId, 'IN_CONSULTATION');
                }

                // Step C: Create Medical Record
                $mrNotes = "Simulated consultation completed. Patient symptoms: {$reason}. Advised prescribed medications.";
                $mrStmt = $conn->prepare("INSERT INTO medical_records (patient_id, doctor_id, diagnosis, prescription, notes) VALUES (?, ?, ?, ?, ?)");
                $mrStmt->bind_param("iisss", $patientId, $doctorId, $diagnosis, $meds, $mrNotes);
                if (!$mrStmt->execute()) {
                    throw new Exception("Failed to insert medical record: " . $conn->error);
                }
                $recordId = $conn->insert_id;
                $createdMedicalRecords++;

                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'medical_record', $recordId, 'patient', $patientId)");

                // Step D: Create Prescription
                $rxNotes = "Take medications after food. Drink plenty of water.";
                $rxStmt = $conn->prepare("INSERT INTO prescriptions (appointment_id, patient_id, doctor_id, diagnosis, medicines, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $rxStmt->bind_param("iiisss", $appointmentId, $patientId, $doctorId, $diagnosis, $meds, $rxNotes);
                if (!$rxStmt->execute()) {
                    throw new Exception("Failed to insert prescription: " . $conn->error);
                }
                $prescriptionId = $conn->insert_id;
                $createdPrescriptions++;

                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'prescription', $prescriptionId, 'appointment', $appointmentId)");

                // Step E: Complete Flow & Mark Appointment Completed
                if ($flowId && class_exists('PatientFlowEngine')) {
                    PatientFlowEngine::updateStage($conn, $flowId, 'DISCHARGED');
                }
                $conn->query("UPDATE appointments SET status = 'Completed' WHERE appointment_id = $appointmentId");

                $conn->commit();
                $createdConsultations++;

                SimulationSafetyService::logEvent($conn, $runId, 'OPD_JOURNEY_COMPLETED', 'SUCCESS', 'appointment', $appointmentId, "OPD journey complete for Patient #{$patientId} with Dr. {$assignedDoc['full_name']}");

            } catch (Exception $e) {
                $conn->rollback();
                $failedJourneys++;
                SimulationSafetyService::logEvent($conn, $runId, 'OPD_JOURNEY_FAILED', 'FAILED', 'patient', $patientId, null, $e->getMessage());
            }
        }

        $totalProcessed = count($patients);
        SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $createdAppointments + $createdConsultations, $failedJourneys);
        SimulationSafetyService::logEvent($conn, $runId, 'OPD_SIMULATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "OPD Simulation finished: Processed={$totalProcessed}, Appointments={$createdAppointments}, Consultations={$createdConsultations}, Prescriptions={$createdPrescriptions}, Failed={$failedJourneys}");

        return [
            'success'                => true,
            'run_id'                 => $runId,
            'patients_processed'     => $totalProcessed,
            'appointments_created'   => $createdAppointments,
            'flows_created'          => $createdFlows,
            'consultations_completed'=> $createdConsultations,
            'medical_records_created'=> $createdMedicalRecords,
            'prescriptions_created'  => $createdPrescriptions,
            'failed_journeys'        => $failedJourneys,
            'is_simulated'           => true
        ];
    }
}
