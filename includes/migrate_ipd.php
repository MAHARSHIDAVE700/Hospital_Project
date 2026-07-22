<?php
// includes/migrate_ipd.php
// Running this script initializes the IPD Module tables in Postgres

require_once __DIR__ . '/config.php';

echo "Starting IPD (Admission & Discharge) tables migration...\n";

// Table 1: ipd_admissions
$table1 = "
CREATE TABLE IF NOT EXISTS ipd_admissions (
    ipd_id SERIAL PRIMARY KEY,
    patient_id INT NOT NULL REFERENCES patients(patient_id) ON DELETE CASCADE,
    doctor_id INT NOT NULL REFERENCES doctors(doctor_id) ON DELETE RESTRICT,
    bed_id INT NOT NULL REFERENCES beds(bed_id) ON DELETE RESTRICT,
    admission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admission_reason TEXT NOT NULL,
    initial_bp VARCHAR(20) NOT NULL,
    initial_temp VARCHAR(10) NOT NULL,
    initial_pulse VARCHAR(10) NOT NULL,
    initial_weight VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Admitted' CHECK (status IN ('Admitted', 'Discharged')),
    discharge_date TIMESTAMP NULL,
    discharge_summary TEXT NULL,
    discharge_status VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

// Table 2: ipd_progress_logs
$table2 = "
CREATE TABLE IF NOT EXISTS ipd_progress_logs (
    log_id SERIAL PRIMARY KEY,
    ipd_id INT NOT NULL REFERENCES ipd_admissions(ipd_id) ON DELETE CASCADE,
    log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pulse_rate VARCHAR(10) NOT NULL,
    temp_f VARCHAR(10) NOT NULL,
    blood_pressure VARCHAR(20) NOT NULL,
    clinical_notes TEXT NOT NULL,
    logged_by VARCHAR(50) NOT NULL DEFAULT 'Staff Nurse',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";

try {
    echo "Creating 'ipd_admissions' table... ";
    $res1 = $conn->query($table1);
    if ($res1 === false) {
        throw new Exception("Error creating ipd_admissions table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Creating 'ipd_progress_logs' table... ";
    $res2 = $conn->query($table2);
    if ($res2 === false) {
        throw new Exception("Error creating ipd_progress_logs table: " . $conn->error);
    }
    echo "SUCCESS.\n";

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "MIGRATION FAILED: " . $e->getMessage() . "\n";
}
?>
