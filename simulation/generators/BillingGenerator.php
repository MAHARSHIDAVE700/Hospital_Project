<?php
/**
 * Simulation Billing & Invoice Generator
 * Path: simulation/generators/BillingGenerator.php
 * 
 * Generates simulated invoice identifiers, payment references, and charge aggregation calculations.
 */

class BillingGenerator {

    /**
     * Generate unique simulated invoice number.
     *
     * @param int $patientId
     * @param int $seq
     * @return string
     */
    public static function generateInvoiceNumber($patientId, $seq = 1) {
        return 'INV-SIM-' . date('Ymd') . '-' . sprintf('%04d', $patientId) . '-' . sprintf('%02d', $seq);
    }

    /**
     * Generate unique simulated payment transaction reference.
     *
     * @param int $patientId
     * @return string
     */
    public static function generatePaymentReference($patientId) {
        return 'SIM-PAY-' . date('Ymd') . '-' . sprintf('%04d', $patientId) . '-' . rand(1000, 9999);
    }
}
