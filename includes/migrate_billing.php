<?php
// includes/migrate_billing.php
// Running this script initializes the Advanced Billing tables and alters FK constraints in Postgres

require_once __DIR__ . '/config.php';

echo "Starting Advanced Billing tables migration...\n";

// Table: invoices
$createInvoicesTable = "
CREATE TABLE IF NOT EXISTS invoices (
    invoice_id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(patient_id) ON DELETE CASCADE,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    opd_charges DECIMAL(10, 2) DEFAULT 0.00,
    ipd_charges DECIMAL(10, 2) DEFAULT 0.00,
    lab_charges DECIMAL(10, 2) DEFAULT 0.00,
    pharmacy_charges DECIMAL(10, 2) DEFAULT 0.00,
    tax_amount DECIMAL(10, 2) DEFAULT 0.00,
    discount DECIMAL(10, 2) DEFAULT 0.00,
    total_amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Unpaid' CHECK (status IN ('Unpaid', 'Paid')),
    payment_method VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

try {
    echo "Creating 'invoices' table... ";
    $res = $conn->query($createInvoicesTable);
    if ($res === false) {
        throw new Exception("Error creating invoices table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    // Alter table helper columns
    $alterations = [
        ['appointments', 'invoice_id', 'INT', 'invoices(invoice_id)'],
        ['bed_allocations', 'invoice_id', 'INT', 'invoices(invoice_id)'],
        ['lab_requests', 'invoice_id', 'INT', 'invoices(invoice_id)'],
        ['medicine_dispenses', 'invoice_id', 'INT', 'invoices(invoice_id)']
    ];

    foreach ($alterations as $alt) {
        $table = $alt[0];
        $col = $alt[1];
        $type = $alt[2];
        $ref = $alt[3];
        
        echo "Adding column '$col' to '$table'... ";
        // PostgreSQL Add Column If Not Exists syntax
        $conn->query("ALTER TABLE $table ADD COLUMN IF NOT EXISTS $col $type");
        
        // Add foreign key constraint safely in separate try block
        try {
            $conn->query("ALTER TABLE $table ADD CONSTRAINT fk_{$table}_{$col} FOREIGN KEY ($col) REFERENCES $ref ON DELETE SET NULL");
        } catch (Exception $ex) {
            // Constraint might already exist, which is fine
        }
        echo "SUCCESS.\n";
    }

    echo "Advanced Billing migration completed successfully!\n";
} catch (Exception $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
}
?>
