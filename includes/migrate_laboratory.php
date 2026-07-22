<?php
// includes/migrate_laboratory.php
// Running this script initializes the Laboratory Management tables in Postgres

require_once __DIR__ . '/config.php';

echo "Starting Laboratory Management tables migration...\n";

// Table 1: lab_tests
$table1 = "
CREATE TABLE IF NOT EXISTS lab_tests (
    test_id SERIAL PRIMARY KEY,
    test_name VARCHAR(100) NOT NULL,
    test_code VARCHAR(50) UNIQUE NOT NULL,
    sample_type VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

// Table 2: lab_requests
$table2 = "
CREATE TABLE IF NOT EXISTS lab_requests (
    request_id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(patient_id) ON DELETE CASCADE,
    doctor_id INT NULL REFERENCES doctors(doctor_id) ON DELETE SET NULL,
    test_id INT NOT NULL REFERENCES lab_tests(test_id) ON DELETE CASCADE,
    status VARCHAR(30) NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Sample Collected', 'Completed', 'Cancelled')),
    result_summary TEXT NULL,
    result_details TEXT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    result_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

try {
    echo "Creating 'lab_tests' table... ";
    $res1 = $conn->query($table1);
    if ($res1 === false) {
        throw new Exception("Error creating lab_tests table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Creating 'lab_requests' table... ";
    $res2 = $conn->query($table2);
    if ($res2 === false) {
        throw new Exception("Error creating lab_requests table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    // Insert some default mock tests if the table is empty
    $checkTests = $conn->query("SELECT COUNT(*) AS total FROM lab_tests")->fetch_assoc()['total'];
    if ($checkTests == 0) {
        echo "Inserting default clinical laboratory tests... ";
        $mockTests = [
            ['Complete Blood Count (CBC)', 'LAB-CBC', 'Blood', 350.00],
            ['Lipid Profile (Cholesterol)', 'LAB-LPD', 'Blood', 650.00],
            ['Liver Function Test (LFT)', 'LAB-LFT', 'Blood', 800.00],
            ['Thyroid Profile (T3, T4, TSH)', 'LAB-THY', 'Blood', 900.00],
            ['Random Blood Sugar (RBS)', 'LAB-RBS', 'Blood', 150.00],
            ['Urine Routine & Microscopy', 'LAB-URN', 'Urine', 200.00],
            ['Kidney Function Test (KFT)', 'LAB-KFT', 'Blood', 750.00]
        ];
        
        $stmt = $conn->prepare("INSERT INTO lab_tests (test_name, test_code, sample_type, price) VALUES (?, ?, ?, ?)");
        foreach ($mockTests as $test) {
            $stmt->bind_param("sssd", $test[0], $test[1], $test[2], $test[3]);
            $stmt->execute();
        }
        echo "SUCCESS.\n";
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
}
?>
