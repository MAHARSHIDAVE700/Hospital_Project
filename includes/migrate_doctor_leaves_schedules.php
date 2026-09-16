<?php
// includes/migrate_doctor_leaves_schedules.php
// Running this script initializes doctor_leaves and doctor_schedules tables in PostgreSQL / NeonDB

require_once __DIR__ . '/config.php';

echo "Starting Doctor Leaves & Schedules migration...\n";

// Table 1: doctor_leaves
$table1 = "
CREATE TABLE IF NOT EXISTS doctor_leaves (
    leave_id SERIAL PRIMARY KEY,
    doctor_id INT NOT NULL REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
    admin_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

// Table 2: doctor_schedules
$table2 = "
CREATE TABLE IF NOT EXISTS doctor_schedules (
    schedule_id SERIAL PRIMARY KEY,
    doctor_id INT NOT NULL REFERENCES doctors(doctor_id) ON DELETE CASCADE,
    day_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL DEFAULT '09:00:00',
    end_time TIME NOT NULL DEFAULT '17:00:00',
    is_available INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_doctor_day UNIQUE(doctor_id, day_of_week)
)
";

try {
    echo "Creating 'doctor_leaves' table... ";
    $res1 = $conn->query($table1);
    if ($res1 === false) {
        throw new Exception("Error creating doctor_leaves table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Creating 'doctor_schedules' table... ";
    $res2 = $conn->query($table2);
    if ($res2 === false) {
        throw new Exception("Error creating doctor_schedules table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
