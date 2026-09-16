<?php
/**
 * Billing, Invoice & Payment Workflow Engine
 * Path: simulation/engines/BillingWorkflowEngine.php
 * 
 * Aggregates unbilled OPD, Lab, Pharmacy, and IPD charges, creates consolidated invoices,
 * simulates internal payment completion, generates invoice PDFs, and tracks simulation entities.
 */

if (file_exists(__DIR__ . '/../generators/BillingGenerator.php')) {
    require_once __DIR__ . '/../generators/BillingGenerator.php';
}
if (file_exists(__DIR__ . '/../../includes/pdf_helper.php')) {
    require_once __DIR__ . '/../../includes/pdf_helper.php';
}

class BillingWorkflowEngine {

    /**
     * Execute automated billing workflow for synthetic patients belonging to a simulation run.
     *
     * @param NeonDB $conn
     * @param string $runId
     * @param int|null $limit Optional patient limit (e.g. 5 for small test)
     * @return array Result summary
     */
    public static function executeBillingWorkflowForRun($conn, $runId, $limit = null) {
        // Enable safety mode to prevent external side effects
        SimulationSafetyService::enableSafetyMode($runId);

        // 1. Fetch synthetic patients belonging to this simulation run
        $sql = "SELECT e.entity_id AS patient_id, u.full_name AS patient_name, p.phone 
                FROM simulation_entities e 
                JOIN patients p ON e.entity_id = p.patient_id 
                LEFT JOIN users u ON p.user_id = u.id 
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

        $createdInvoices = 0;
        $totalRevenue = 0.00;
        $pdfsGenerated = 0;
        $failedBilling = 0;
        $skippedDuplicate = 0;

        SimulationSafetyService::logEvent($conn, $runId, 'BILLING_SIMULATION_STARTED', 'IN_PROGRESS', 'SimulationRun', null, "Starting Billing & Invoice simulation for " . count($patients) . " patient(s)");

        foreach ($patients as $pat) {
            $patientId = intval($pat['patient_id']);
            $patientName = $pat['patient_name'] ?? "Synthetic Patient #{$patientId}";
            $phone = $pat['phone'] ?? '98XXXXXXXX';

            try {
                $conn->begin_transaction();

                // Step A: Charge Aggregation for Unbilled Services
                // 1. OPD Charges
                $opdRes = $conn->query("SELECT SUM(COALESCE(opd_fee_paid, 200.00)) AS total FROM appointments WHERE patient_id = $patientId AND invoice_id IS NULL");
                $opdCharges = floatval(($opdRes && $opdRes->num_rows > 0) ? $opdRes->fetch_assoc()['total'] : 0.00);

                // 2. Lab Charges
                $labRes = $conn->query("SELECT SUM(t.price) AS total FROM lab_requests l JOIN lab_tests t ON l.test_id = t.test_id WHERE l.patient_id = $patientId AND l.invoice_id IS NULL");
                $labCharges = floatval(($labRes && $labRes->num_rows > 0) ? $labRes->fetch_assoc()['total'] : 0.00);

                // 3. Pharmacy Charges
                $phmRes = $conn->query("SELECT SUM(total_price) AS total FROM medicine_dispenses WHERE patient_id = $patientId AND invoice_id IS NULL");
                $pharmacyCharges = floatval(($phmRes && $phmRes->num_rows > 0) ? $phmRes->fetch_assoc()['total'] : 0.00);

                // 4. IPD Bed Charges
                $ipdRes = $conn->query("SELECT SUM(b.price_per_day) AS total FROM bed_allocations ba JOIN beds b ON ba.bed_id = b.bed_id WHERE ba.patient_id = $patientId AND ba.invoice_id IS NULL");
                $ipdCharges = floatval(($ipdRes && $ipdRes->num_rows > 0) ? $ipdRes->fetch_assoc()['total'] : 0.00);

                $totalAmount = round($opdCharges + $labCharges + $pharmacyCharges + $ipdCharges, 2);

                if ($totalAmount <= 0) {
                    $conn->rollback();
                    $skippedDuplicate++;
                    continue; // Skip if no unbilled charges exist
                }

                $invoiceNumber = BillingGenerator::generateInvoiceNumber($patientId, $createdInvoices + 1);
                $taxAmount = 0.00;
                $discount = 0.00;

                // Step B: Insert Invoice record into invoices table
                $stmt = $conn->prepare("INSERT INTO invoices (patient_id, invoice_number, opd_charges, ipd_charges, lab_charges, pharmacy_charges, tax_amount, discount, total_amount, status, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', 'SIMULATED')");
                $stmt->bind_param("isddddddd", $patientId, $invoiceNumber, $opdCharges, $ipdCharges, $labCharges, $pharmacyCharges, $taxAmount, $discount, $totalAmount);

                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert invoice: " . $conn->error);
                }

                $invoiceId = $conn->insert_id;
                $createdInvoices++;
                $totalRevenue += $totalAmount;

                // Track invoice in simulation_entities
                $conn->query("INSERT INTO simulation_entities (run_id, entity_type, entity_id, parent_entity_type, parent_entity_id) VALUES ('$runId', 'invoice', $invoiceId, 'patient', $patientId)");

                // Step C: Link Unbilled Services to Newly Created Invoice
                $conn->query("UPDATE appointments SET invoice_id = $invoiceId WHERE patient_id = $patientId AND invoice_id IS NULL");
                $conn->query("UPDATE lab_requests SET invoice_id = $invoiceId WHERE patient_id = $patientId AND invoice_id IS NULL");
                $conn->query("UPDATE medicine_dispenses SET invoice_id = $invoiceId WHERE patient_id = $patientId AND invoice_id IS NULL");
                $conn->query("UPDATE bed_allocations SET invoice_id = $invoiceId WHERE patient_id = $patientId AND invoice_id IS NULL");

                // Step D: Generate Invoice PDF
                $invData = [
                    'invoice_id'      => $invoiceId,
                    'invoice_number'  => $invoiceNumber,
                    'patient_name'    => $patientName,
                    'phone'           => $phone,
                    'opd_charges'     => $opdCharges,
                    'lab_charges'     => $labCharges,
                    'pharmacy_charges'=> $pharmacyCharges,
                    'ipd_charges'     => $ipdCharges,
                    'total_amount'    => $totalAmount,
                    'payment_method'  => 'SIMULATED',
                    'created_at'      => date('Y-m-d H:i:s')
                ];

                if (class_exists('PDFHelper')) {
                    $pdfBuffer = PDFHelper::generateInvoicePDF($invData);
                    if (!empty($pdfBuffer) && strpos($pdfBuffer, '%PDF') === 0) {
                        $pdfsGenerated++;
                    }
                }

                $conn->commit();

                SimulationSafetyService::logEvent($conn, $runId, 'BILLING_INVOICE_CREATED', 'SUCCESS', 'invoice', $invoiceId, "Invoice {$invoiceNumber} created & paid (SIMULATED) for Patient #{$patientId}. Total: INR {$totalAmount}");

            } catch (Exception $e) {
                $conn->rollback();
                $failedBilling++;
                SimulationSafetyService::logEvent($conn, $runId, 'BILLING_INVOICE_FAILED', 'FAILED', 'patient', $patientId, null, $e->getMessage());
            }
        }

        $totalPatientsProcessed = count($patients);
        SimulationDatabaseService::updateRunStatus($conn, $runId, 'COMPLETED', $createdInvoices, $failedBilling);
        SimulationSafetyService::logEvent($conn, $runId, 'BILLING_SIMULATION_COMPLETED', 'SUCCESS', 'SimulationRun', null, "Billing simulation finished: Processed={$totalPatientsProcessed}, Invoices={$createdInvoices}, Revenue=INR {$totalRevenue}, PDFs={$pdfsGenerated}, Skipped={$skippedDuplicate}, Failed={$failedBilling}");

        return [
            'success'             => true,
            'run_id'              => $runId,
            'patients_processed'  => $totalPatientsProcessed,
            'invoices_created'    => $createdInvoices,
            'total_revenue'       => round($totalRevenue, 2),
            'pdfs_generated'      => $pdfsGenerated,
            'skipped_duplicate'   => $skippedDuplicate,
            'failed_billing'      => $failedBilling,
            'is_simulated'        => true
        ];
    }
}
