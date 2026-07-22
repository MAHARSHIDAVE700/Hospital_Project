<?php
// includes/migrate_beds.php
// Running this script initializes the Bed Management tables in Postgres

require_once __DIR__ . '/config.php';

echo "Starting Bed Management tables migration...\n";

// Table 1: beds
$table1 = "
CREATE TABLE IF NOT EXISTS beds (
    bed_id SERIAL PRIMARY KEY,
    bed_number VARCHAR(50) UNIQUE NOT NULL,
    bed_type VARCHAR(50) NOT NULL,
    price_per_day DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'Available' CHECK (status IN ('Available', 'Occupied', 'Maintenance')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

// Table 2: bed_allocations
$table2 = "
CREATE TABLE IF NOT EXISTS bed_allocations (
    allocation_id SERIAL PRIMARY KEY,
    bed_id INT NOT NULL REFERENCES beds(bed_id) ON DELETE CASCADE,
    patient_id INT NOT NULL REFERENCES patients(patient_id) ON DELETE CASCADE,
    admission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    discharge_date TIMESTAMP NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Active' CHECK (status IN ('Active', 'Discharged')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

try {
    echo "Creating 'beds' table... ";
    $res1 = $conn->query($table1);
    if ($res1 === false) {
        throw new Exception("Error creating beds table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Creating 'bed_allocations' table... ";
    $res2 = $conn->query($table2);
    if ($res2 === false) {
        throw new Exception("Error creating bed_allocations table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    // Insert some default mock beds if the table is empty
    $checkBeds = $conn->query("SELECT COUNT(*) AS total FROM beds")->fetch_assoc()['total'];
    if ($checkBeds == 0) {
        echo "Inserting default mock beds... ";
        $mockBeds = [
            ['ICU-101', 'ICU', 1500.00, 'Available'],
            ['ICU-102', 'ICU', 1500.00, 'Available'],
            ['WARD-A-201', 'General Ward', 300.00, 'Available'],
            ['WARD-A-202', 'General Ward', 300.00, 'Available'],
            ['WARD-B-301', 'Semi-Private', 600.00, 'Available'],
            ['WARD-B-302', 'Semi-Private', 600.00, 'Available'],
            ['PVT-401', 'Private Suite', 1200.00, 'Available'],
            ['PVT-402', 'Private Suite', 1200.00, 'Available']
        ];
        
        $stmt = $conn->prepare("INSERT INTO beds (bed_number, bed_type, price_per_day, status) VALUES (?, ?, ?, ?)");
        foreach ($mockBeds as $bed) {
            $stmt->bind_param("ssds", $bed[0], $bed[1], $bed[2], $bed[3]);
            $stmt->execute();
        }
        echo "SUCCESS.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
}
?>
