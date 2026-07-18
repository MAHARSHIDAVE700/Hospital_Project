<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include "../includes/config.php";

$query = $conn->query("
    SELECT d.doctor_id, d.full_name, dep.department_name, d.status
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    ORDER BY d.full_name ASC
");

$queues = [];
while ($doc = $query->fetch_assoc()) {
    $doctorID = $doc['doctor_id'];
    
    // Count pending appointments today
    $pendingQuery = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id='$doctorID' AND appointment_date=CURRENT_DATE AND status='Pending'")->fetch_assoc()['total'];
    
    // Get live token (oldest pending)
    $liveQuery = $conn->query("
        SELECT appointment_id 
        FROM appointments 
        WHERE doctor_id='$doctorID' 
        AND appointment_date=CURRENT_DATE 
        AND status='Pending'
        ORDER BY appointment_time ASC, appointment_id ASC LIMIT 1
    ");
    $liveAppt = $liveQuery ? $liveQuery->fetch_assoc() : null;
    $liveToken = '-';
    if ($liveAppt) {
        $liveToken = $liveAppt['appointment_id'];
    } else {
        $lastCompletedQuery = $conn->query("
            SELECT appointment_id 
            FROM appointments 
            WHERE doctor_id='$doctorID' 
            AND appointment_date=CURRENT_DATE 
            AND status='Completed'
            ORDER BY appointment_id DESC LIMIT 1
        ");
        $lastComp = $lastCompletedQuery ? $lastCompletedQuery->fetch_assoc() : null;
        $liveToken = $lastComp ? $lastComp['appointment_id'] : '-';
    }
    
    $queues[] = [
        'doctor_name' => $doc['full_name'],
        'department' => $doc['department_name'] ?: 'General',
        'status' => $doc['status'],
        'live_token' => $liveToken,
        'waiting_count' => $pendingQuery
    ];
}

echo json_encode($queues);
?>
