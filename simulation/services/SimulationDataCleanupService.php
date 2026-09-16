<?php
/**
 * Simulation Data Cleanup Service
 * Path: simulation/services/SimulationDataCleanupService.php
 * 
 * Safely removes simulation run data using simulation_entities tracking table.
 * Restores unbilled service linkages, medicine stock, and master bed statuses prior to deleting simulation records.
 */

class SimulationDataCleanupService {

    /**
     * Preview records created during a specific simulation run.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @return array
     */
    public static function previewCleanup($conn, $runId) {
        $run = SimulationDatabaseService::getRun($conn, $runId);
        if (!$run) {
            return ['error' => "Simulation Run '$runId' not found."];
        }

        $entitiesRes = $conn->query("SELECT entity_type, COUNT(*) as cnt FROM simulation_entities WHERE run_id = '$runId' GROUP BY entity_type");
        $entitySummary = [];
        if ($entitiesRes && $entitiesRes->num_rows > 0) {
            while ($row = $entitiesRes->fetch_assoc()) {
                $entitySummary[] = $row;
            }
        }

        $eventsRes = $conn->query("SELECT event_type, status, COUNT(*) as cnt FROM simulation_events WHERE run_id = '$runId' GROUP BY event_type, status");
        $eventSummary = [];
        if ($eventsRes && $eventsRes->num_rows > 0) {
            while ($row = $eventsRes->fetch_assoc()) {
                $eventSummary[] = $row;
            }
        }

        return [
            'run_id'         => $runId,
            'status'         => $run['status'],
            'created_at'     => $run['created_at'],
            'entity_summary' => $entitySummary,
            'event_summary'  => $eventSummary
        ];
    }

    /**
     * Execute safe cleanup of a specific simulation run, restore medicine stock, and reset bed statuses.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @return array
     */
    public static function executeCleanup($conn, $runId) {
        $run = SimulationDatabaseService::getRun($conn, $runId);
        if (!$run) {
            return ['success' => false, 'message' => "Simulation Run '$runId' not found."];
        }

        try {
            $conn->begin_transaction();

            SimulationSafetyService::logEvent($conn, $runId, 'SIMULATION_CLEANUP_INIT', 'IN_PROGRESS', 'SimulationRun', null, "Starting cleanup for simulation run '$runId'");

            // 1. Unlink invoice_id references from services linked to simulation invoices
            $conn->query("UPDATE appointments SET invoice_id = NULL WHERE invoice_id IN (SELECT entity_id FROM simulation_entities WHERE run_id = '$runId' AND entity_type = 'invoice')");
            $conn->query("UPDATE lab_requests SET invoice_id = NULL WHERE invoice_id IN (SELECT entity_id FROM simulation_entities WHERE run_id = '$runId' AND entity_type = 'invoice')");
            $conn->query("UPDATE medicine_dispenses SET invoice_id = NULL WHERE invoice_id IN (SELECT entity_id FROM simulation_entities WHERE run_id = '$runId' AND entity_type = 'invoice')");
            $conn->query("UPDATE bed_allocations SET invoice_id = NULL WHERE invoice_id IN (SELECT entity_id FROM simulation_entities WHERE run_id = '$runId' AND entity_type = 'invoice')");

            // 2. Restore medicine stock for pharmacy dispense items tracked in this run
            $stockRestoreRes = $conn->query("SELECT mdi.medicine_id, SUM(mdi.quantity) AS total_qty 
                                             FROM simulation_entities se 
                                             JOIN medicine_dispense_items mdi ON se.entity_id = mdi.item_id 
                                             WHERE se.run_id = '$runId' AND se.entity_type = 'pharmacy_dispense_item' 
                                             GROUP BY mdi.medicine_id");
            $restoredStockCount = 0;
            if ($stockRestoreRes && $stockRestoreRes->num_rows > 0) {
                while ($row = $stockRestoreRes->fetch_assoc()) {
                    $medId = intval($row['medicine_id']);
                    $qtyToRestore = intval($row['total_qty']);
                    $conn->query("UPDATE medicines SET stock_quantity = stock_quantity + $qtyToRestore WHERE medicine_id = $medId");
                    $restoredStockCount++;
                }
            }

            // 3. Restore master bed statuses for bed allocations in this run
            $conn->query("UPDATE beds SET status = 'Available' WHERE bed_id IN (SELECT bed_id FROM bed_allocations ba JOIN simulation_entities se ON ba.allocation_id = se.entity_id WHERE se.run_id = '$runId' AND se.entity_type = 'bed_allocation')");

            // 4. Retrieve all tracked entities for this run
            $entities = [];
            $res = $conn->query("SELECT entity_type, entity_id FROM simulation_entities WHERE run_id = '$runId' ORDER BY entity_tracking_id DESC");
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $entities[] = $row;
                }
            }

            // Group entity IDs by entity_type for batch deletion
            $typeMap = [];
            foreach ($entities as $e) {
                $typeMap[$e['entity_type']][] = intval($e['entity_id']);
            }

            // 5. Delete child entities in strict reverse dependency order

            // A. Invoices
            if (!empty($typeMap['invoice'])) {
                $ids = implode(',', array_unique($typeMap['invoice']));
                $conn->query("DELETE FROM invoices WHERE invoice_id IN ($ids)");
            }

            // B. IPD Progress Logs
            if (!empty($typeMap['ipd_progress_log'])) {
                $ids = implode(',', array_unique($typeMap['ipd_progress_log']));
                $conn->query("DELETE FROM ipd_progress_logs WHERE log_id IN ($ids)");
            }

            // C. IPD Admissions
            if (!empty($typeMap['ipd_admission'])) {
                $ids = implode(',', array_unique($typeMap['ipd_admission']));
                $conn->query("DELETE FROM ipd_progress_logs WHERE ipd_id IN ($ids)");
                $conn->query("DELETE FROM ipd_admissions WHERE ipd_id IN ($ids)");
            }

            // D. Bed Allocations
            if (!empty($typeMap['bed_allocation'])) {
                $ids = implode(',', array_unique($typeMap['bed_allocation']));
                $conn->query("DELETE FROM bed_allocations WHERE allocation_id IN ($ids)");
            }

            // E. Pharmacy Dispense Items
            if (!empty($typeMap['pharmacy_dispense_item'])) {
                $ids = implode(',', array_unique($typeMap['pharmacy_dispense_item']));
                $conn->query("DELETE FROM medicine_dispense_items WHERE item_id IN ($ids)");
            }

            // F. Pharmacy Dispenses
            if (!empty($typeMap['pharmacy_dispense'])) {
                $ids = implode(',', array_unique($typeMap['pharmacy_dispense']));
                $conn->query("DELETE FROM medicine_dispenses WHERE dispense_id IN ($ids)");
            }

            // G. Lab Requests
            if (!empty($typeMap['lab_request'])) {
                $ids = implode(',', array_unique($typeMap['lab_request']));
                $conn->query("DELETE FROM lab_requests WHERE request_id IN ($ids)");
            }

            // H. Prescriptions
            if (!empty($typeMap['prescription'])) {
                $ids = implode(',', array_unique($typeMap['prescription']));
                $conn->query("DELETE FROM prescriptions WHERE prescription_id IN ($ids)");
            }

            // I. Medical Records
            if (!empty($typeMap['medical_record'])) {
                $ids = implode(',', array_unique($typeMap['medical_record']));
                $conn->query("DELETE FROM medical_records WHERE record_id IN ($ids)");
            }

            // J. Queue Events & Patient Flow
            if (!empty($typeMap['patient_flow'])) {
                $ids = implode(',', array_unique($typeMap['patient_flow']));
                $conn->query("DELETE FROM queue_events WHERE flow_id IN ($ids)");
                $conn->query("DELETE FROM patient_flow WHERE flow_id IN ($ids)");
            }

            // K. Appointments
            if (!empty($typeMap['appointment'])) {
                $ids = implode(',', array_unique($typeMap['appointment']));
                $conn->query("DELETE FROM appointments WHERE appointment_id IN ($ids)");
            }

            // L. Patients
            if (!empty($typeMap['patient'])) {
                $ids = implode(',', array_unique($typeMap['patient']));
                $conn->query("DELETE FROM patients WHERE patient_id IN ($ids)");
            }

            // M. Users
            if (!empty($typeMap['user'])) {
                $ids = implode(',', array_unique($typeMap['user']));
                $conn->query("DELETE FROM users WHERE id IN ($ids)");
            }

            // 6. Delete entity tracking records
            $conn->query("DELETE FROM simulation_entities WHERE run_id = '$runId'");

            // 7. Delete simulation events
            $conn->query("DELETE FROM simulation_events WHERE run_id = '$runId'");

            // 8. Delete simulation run record
            $conn->query("DELETE FROM simulation_runs WHERE run_id = '$runId'");

            $conn->commit();

            return [
                'success' => true,
                'run_id'  => $runId,
                'message' => "Simulation Run '$runId' cleaned up successfully. Restored unbilled linkages, stock for $restoredStockCount medicine(s), and master bed statuses to 'Available'. All tracked billing, IPD, pharmacy, lab, and OPD entities removed cleanly. Zero real hospital records affected."
            ];
        } catch (Exception $e) {
            $conn->rollback();
            return [
                'success' => false,
                'message' => "Cleanup failed: " . $e->getMessage()
            ];
        }
    }
}
