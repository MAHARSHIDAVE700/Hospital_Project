<?php
// includes/migrate_pharmacy.php
// Running this script initializes the Pharmacy Management tables in Postgres

require_once __DIR__ . '/config.php';

echo "Starting Pharmacy Management tables migration...\n";

// Table 1: medicines
$table1 = "
CREATE TABLE IF NOT EXISTS medicines (
    medicine_id SERIAL PRIMARY KEY,
    medicine_name VARCHAR(100) NOT NULL,
    generic_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

// Table 2: medicine_dispenses
$table2 = "
CREATE TABLE IF NOT EXISTS medicine_dispenses (
    dispense_id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(patient_id) ON DELETE CASCADE,
    dispense_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'Dispensed' CHECK (status IN ('Dispensed', 'Cancelled')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

// Table 3: medicine_dispense_items
$table3 = "
CREATE TABLE IF NOT EXISTS medicine_dispense_items (
    item_id SERIAL PRIMARY KEY,
    dispense_id INT NOT NULL REFERENCES medicine_dispenses(dispense_id) ON DELETE CASCADE,
    medicine_id INT NOT NULL REFERENCES medicines(medicine_id) ON DELETE CASCADE,
    quantity INT NOT NULL,
    price_per_unit DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

try {
    echo "Creating 'medicines' table... ";
    $res1 = $conn->query($table1);
    if ($res1 === false) {
        throw new Exception("Error creating medicines table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Creating 'medicine_dispenses' table... ";
    $res2 = $conn->query($table2);
    if ($res2 === false) {
        throw new Exception("Error creating medicine_dispenses table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Creating 'medicine_dispense_items' table... ";
    $res3 = $conn->query($table3);
    if ($res3 === false) {
        throw new Exception("Error creating medicine_dispense_items table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    // Insert some default mock medicines if empty
    $checkMeds = $conn->query("SELECT COUNT(*) AS total FROM medicines")->fetch_assoc()['total'];
    if ($checkMeds == 0) {
        echo "Inserting default clinical medicines into inventory... ";
        $mockMeds = [
            ['Paracetamol 500mg', 'Acetaminophen', 'Analgesic / Antipyretic', 120, 2.50, '2027-12-01'],
            ['Amoxicillin 250mg', 'Amoxicillin', 'Antibiotic', 80, 5.00, '2026-08-31'],
            ['Metformin 500mg', 'Metformin HCl', 'Antidiabetic', 15, 3.20, '2027-05-15'], // low stock
            ['Atorvastatin 10mg', 'Atorvastatin', 'Antihyperlipidemic', 150, 8.50, '2026-09-01'], // expires soon
            ['Cetirizine 10mg', 'Cetirizine Hydrochloride', 'Antihistamine', 200, 1.80, '2028-02-28'],
            ['Ibuprofen 400mg', 'Ibuprofen', 'NSAID / Analgesic', 90, 4.00, '2027-10-31'],
            ['Pantoprazole 40mg', 'Pantoprazole Sodium', 'Antacid / PPI', 5, 6.00, '2027-11-20'] // low stock
        ];
        
        $stmt = $conn->prepare("INSERT INTO medicines (medicine_name, generic_name, category, stock_quantity, unit_price, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($mockMeds as $med) {
            $stmt->bind_param("sssids", $med[0], $med[1], $med[2], $med[3], $med[4], $med[5]);
            $stmt->execute();
        }
        echo "SUCCESS.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
}
?>
