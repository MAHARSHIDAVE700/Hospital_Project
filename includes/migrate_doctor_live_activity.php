<?php
// includes/migrate_doctor_live_activity.php

require_once __DIR__ . '/config.php';

echo "Adding last_active_at column to doctors table...\n";

$sql = "ALTER TABLE doctors ADD COLUMN IF NOT EXISTS last_active_at TIMESTAMP DEFAULT NULL";

try {
    $res = $conn->query($sql);
    if ($res === false) {
        throw new Exception("Failed: " . $conn->error);
    }
    echo "SUCCESS: last_active_at column added to doctors table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
