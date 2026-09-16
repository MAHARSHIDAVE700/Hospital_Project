<?php
// includes/cleanup_doctor_stale_status.php

require_once __DIR__ . '/config.php';

echo "Cleaning up stale doctor status...\n";

// Update doctors whose last_active_at is older than 5 minutes or NULL to 'Offline'
$conn->query("
    UPDATE doctors 
    SET status = 'Offline' 
    WHERE last_active_at IS NULL 
    OR last_active_at < NOW() - INTERVAL '5 minutes'
");

echo "SUCCESS: Stale doctor statuses reset to Offline.\n";
?>
