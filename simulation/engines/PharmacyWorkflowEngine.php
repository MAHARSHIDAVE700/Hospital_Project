<?php
/**
 * Pharmacy & Medication Workflow Engine
 * Path: simulation/engines/PharmacyWorkflowEngine.php
 * 
 * Orchestrates automated medication dispenses, inventory stock reduction, and simulation entity tracking.
 */

if (file_exists(__DIR__ . '/../generators/PharmacyGenerator.php')) {
    require_once __DIR__ . '/../generators/PharmacyGenerator.php';
}

class PharmacyWorkflowEngine {

    /**
     * Execute automated pharmacy dispense workflow for synthetic patients of a simulation run.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param int|null $limit Optional patient limit (e.g. 5 for small test)
     * @return array Result summary
     */
    public static function executePharmacyWorkflowForRun($conn, $runId, $limit = null) {
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

        // 2. Fetch master medicines with positive stock
        $medRes = $conn->query("SELECT medicine_id, medicine_name, generic_name, stock_quantity, unit_price FROM medicines WHERE stock_quantity > 0 ORDER BY medicine_id ASC");
        $availableMedicines = [];
        if ($medRes && $medRes->num_rows > 0) {
            while ($row = $medRes->fetch_assoc()) {
                $availableMedicines[] = $row;
            }
        }

        if (empty($availableMedicines)) {
            return [
                'success' => false,
                'message' => "No available medicine inventory with stock found in database."
            ];
        }

        $createdDispenses = 0;
        $createdItems = 0;
        $stockUpdates = 0;
        $failedDispenses = 0;
        $prescriptionsFound = 0;

        SimulationSafetyService::logEvent($conn, $runId, 'PHARMACY_SIMULATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Starting Pharmacy Workflow simulation for " . count($patients) . " patient(s)");

        foreach ($patients as $pat) {
            $patientId = intval($pat['patient_id']);

            // Fetch prescription from Phase 3.3 if available
            $rxRes = $conn->query("SELECT prescription_id, medicines FROM prescriptions WHERE patient_id = $patientId ORDER BY prescription_id DESC LIMIT 1");
            $prescribedText = '';
            if ($rxRes && $rxRes->num_rows > 0) {
                $prescriptionsFound++;
                $prescribedText = $rxRes->fetch_assoc()['medicines'] ?? '';
            }

            // Build dispense item payload using current stock state
            $itemsToDispense = PharmacyGenerator::buildDispensePayload($availableMedicines, $prescribedText);
            if (empty($itemsToDispense)) {
                continue;
            }

            try {
                $conn->begin_transaction();

                $dispenseTotal = 0.00;
                foreach ($itemsToDispense as $item) {
                    $dispenseTotal += floatval($item['total_price']);
                }

                // Insert into medicine_dispenses
                $dispenseDate = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("INSERT INTO medicine_dispenses (patient_id, dispense_date, total_price, status) VALUES (?, ?, ?, 'Dispensed')");
                $stmt->bind_param("isd", $patientId, $dispenseDate, $dispenseTotal);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert medicine_dispense: " . $conn->error);
                }

                $dispenseId = $conn->insert_id;
                $createdDispenses++;

                // Track dispense entity
                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'pharmacy_dispense', $dispenseId, 'patient', $patientId)");

                // Insert items & decrease stock atomically
                foreach ($itemsToDispense as $item) {
                    $medId = intval($item['medicine_id']);
                    $qty = intval($item['quantity']);
                    $unitPrice = floatval($item['price_per_unit']);
                    $totalItemPrice = floatval($item['total_price']);

                    // Verify stock before decreasing
                    $stockCheck = $conn->query("SELECT stock_quantity FROM medicines WHERE medicine_id = $medId FOR UPDATE");
                    $currentStock = ($stockCheck && $stockCheck->num_rows > 0) ? intval($stockCheck->fetch_assoc()['stock_quantity']) : 0;

                    if ($currentStock < $qty) {
                        throw new Exception("Insufficient stock for Medicine #{$medId}. Available: {$currentStock}, Requested: {$qty}");
                    }

                    $itemStmt = $conn->prepare("INSERT INTO medicine_dispense_items (dispense_id, medicine_id, quantity, price_per_unit, total_price) VALUES (?, ?, ?, ?, ?)");
                    $itemStmt->bind_param("iiidd", $dispenseId, $medId, $qty, $unitPrice, $totalItemPrice);
                    if (!$itemStmt->execute()) {
                        throw new Exception("Failed to insert dispense item: " . $conn->error);
                    }
                    $itemId = $conn->insert_id;
                    $createdItems++;

                    // Track item entity
                    $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'pharmacy_dispense_item', $itemId, 'pharmacy_dispense', $dispenseId)");

                    // Decrease stock
                    $updateStock = $conn->query("UPDATE medicines SET stock_quantity = stock_quantity - $qty WHERE medicine_id = $medId");
                    if ($updateStock) {
                        $stockUpdates++;
                    }
                }

                $conn->commit();

                // Refresh available medicines list in-memory stock numbers
                foreach ($availableMedicines as &$am) {
                    foreach ($itemsToDispense as $it) {
                        if ($am['medicine_id'] === $it['medicine_id']) {
                            $am['stock_quantity'] -= $it['quantity'];
                        }
                    }
                }

                SimulationSafetyService::logEvent($conn, $runId, 'PHARMACY_DISPENSE_COMPLETED', 'SUCCESS', 'medicine_dispense', $dispenseId, "Pharmacy dispense #{$dispenseId} completed for Patient #{$patientId}. Total: INR {$dispenseTotal}");

            } catch (Exception $e) {
                $conn->rollback();
                $failedDispenses++;
                SimulationSafetyService::logEvent($conn, $runId, 'PHARMACY_DISPENSE_FAILED', 'FAILED', 'patient', $patientId, null, $e->getMessage());
            }
        }

        $totalPatientsProcessed = count($patients);
        SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $createdDispenses, $failedDispenses);
        SimulationSafetyService::logEvent($conn, $runId, 'PHARMACY_SIMULATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "Pharmacy simulation complete: Patients={$totalPatientsProcessed}, PrescriptionsFound={$prescriptionsFound}, Dispenses={$createdDispenses}, Items={$createdItems}, StockUpdates={$stockUpdates}, Failed={$failedDispenses}");

        return [
            'success'             => true,
            'run_id'              => $runId,
            'patients_processed'  => $totalPatientsProcessed,
            'prescriptions_found' => $prescriptionsFound,
            'dispenses_created'   => $createdDispenses,
            'dispense_items_created' => $createdItems,
            'stock_updates'       => $stockUpdates,
            'failed_dispenses'    => $failedDispenses,
            'is_simulated'        => true
        ];
    }
}
