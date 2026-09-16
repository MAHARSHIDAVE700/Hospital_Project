<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include "../includes/config.php";

$query = $conn->query("
    SELECT d.doctor_id, d.full_name, dep.department_name, d.status, d.last_active_at
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    ORDER BY d.full_name ASC
");

$queues = [];
while ($doc = $query->fetch_assoc()) {
    $doctorID = $doc['doctor_id'];
    
    // Evaluate if doctor is actively online on live dashboard (active within 5 minutes)
    $lastActive = $doc['last_active_at'] ?? null;
    $isOnline = (!empty($lastActive) && (time() - strtotime($lastActive)) <= 300);
    $effectiveStatus = ($isOnline && in_array($doc['status'] ?? 'Available', ['Available', 'Busy'])) ? $doc['status'] : 'Offline';

    // Count waiting patients today
    $pendingQuery = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id='$doctorID' AND appointment_date=CURRENT_DATE AND queue_status='Waiting'")->fetch_assoc()['total'];
    
    // Get live token (currently serving)
    $liveQuery = $conn->query("
        SELECT token_number 
        FROM appointments 
        WHERE doctor_id='$doctorID' 
        AND appointment_date=CURRENT_DATE 
        AND queue_status='Called' 
        LIMIT 1
    ");
    $liveAppt = $liveQuery ? $liveQuery->fetch_assoc() : null;
    $liveToken = '-';
    if ($liveAppt && !empty($liveAppt['token_number'])) {
        $liveToken = is_numeric($liveAppt['token_number']) ? 'Token #' . $liveAppt['token_number'] : $liveAppt['token_number'];
    } else {
        // Fallback to next waiting
        $waitingQuery = $conn->query("
            SELECT token_number 
            FROM appointments 
            WHERE doctor_id='$doctorID' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Waiting' 
            ORDER BY queue_position ASC LIMIT 1
        ");
        $waitAppt = $waitingQuery ? $waitingQuery->fetch_assoc() : null;
        if ($waitAppt && !empty($waitAppt['token_number'])) {
            $liveToken = is_numeric($waitAppt['token_number']) ? 'Token #' . $waitAppt['token_number'] : $waitAppt['token_number'];
        } else {
            // Get last completed today
            $lastCompletedQuery = $conn->query("
                SELECT token_number 
                FROM appointments 
                WHERE doctor_id='$doctorID' 
                AND appointment_date=CURRENT_DATE 
                AND queue_status='Completed'
                ORDER BY queue_position DESC LIMIT 1
            ");
            $lastComp = $lastCompletedQuery ? $lastCompletedQuery->fetch_assoc() : null;
            $liveToken = ($lastComp && !empty($lastComp['token_number'])) ? (is_numeric($lastComp['token_number']) ? 'Token #' . $lastComp['token_number'] : $lastComp['token_number']) : '-';
        }
    }
    
    $queues[] = [
        'doctor_name' => $doc['full_name'],
        'department' => $doc['department_name'] ?: 'General',
        'status' => $effectiveStatus,
        'live_token' => $liveToken,
        'waiting_count' => $pendingQuery
    ];
}

echo json_encode($queues);
?>
