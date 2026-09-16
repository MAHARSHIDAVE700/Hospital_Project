<?php
// includes/migrate_continuous_tokens.php

require_once __DIR__ . '/config.php';

echo "Starting Continuous Token Migration...\n";

// Fetch all appointments ordered by appointment_id ASC
$res = $conn->query("SELECT appointment_id, token_number FROM appointments ORDER BY appointment_id ASC");

if ($res && $res->num_rows > 0) {
    $seq = 1;
    while ($row = $res->fetch_assoc()) {
        $apptId = $row['appointment_id'];
        $tokenStr = (string)$seq;
        
        $conn->query("
            UPDATE appointments 
            SET token_number = '$tokenStr', 
                queue_position = '$seq',
                queue_status = CASE WHEN queue_status IS NULL OR queue_status = '' THEN 'Waiting' ELSE queue_status END
            WHERE appointment_id = $apptId
        ");
        echo "Appointment #$apptId assigned Token #$tokenStr\n";
        $seq++;
    }
    echo "SUCCESS: All existing appointments migrated to continuous sequential tokens 1 to " . ($seq - 1) . ".\n";
} else {
    echo "No appointments found to migrate.\n";
}
?>
