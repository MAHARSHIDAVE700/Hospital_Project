<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['patient_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['patient_id'];

// Get patient details
$patientQuery = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patient = $patientQuery->fetch_assoc();
$patientID = $patient ? $patient['patient_id'] : null;

$response = [
    'has_appointment' => false,
    'my_token' => '-',
    'queue_position' => '-',
    'live_token' => '-',
    'patients_ahead' => 0,
    'estimated_wait' => '-',
    'doctor_status' => 'Offline',
    'doctor_name' => '',
    'queue_progress' => 0
];

if ($patientID) {
    // Get patient's earliest pending/confirmed appointment today
    $apptQuery = $conn->query("
        SELECT a.appointment_id, a.doctor_id, d.full_name AS doctor_name, d.status AS doctor_status, a.token_number, a.queue_position
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.patient_id='$patientID' 
        AND a.status IN ('Pending', 'Confirmed') 
        AND a.appointment_date=CURRENT_DATE
        ORDER BY a.appointment_time ASC LIMIT 1
    ");
    
    if ($apptQuery && $appt = $apptQuery->fetch_assoc()) {
        $response['has_appointment'] = true;
        
        $myToken = $appt['token_number'] ?: 'Pending Confirmation';
        $myPosition = $appt['queue_position'] ?: '-';
        
        $response['my_token'] = $myToken;
        $response['queue_position'] = $myPosition;
        $response['doctor_name'] = $appt['doctor_name'];
        $docStatus = $appt['doctor_status'];
        $response['doctor_status'] = $docStatus;
        
        // Find current live serving token number (queue_status = 'Called' today)
        $liveQuery = $conn->query("
            SELECT token_number 
            FROM appointments 
            WHERE doctor_id='{$appt['doctor_id']}' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Called'
            LIMIT 1
        ");
        $liveAppt = $liveQuery ? $liveQuery->fetch_assoc() : null;
        
        if ($liveAppt) {
            $response['live_token'] = $liveAppt['token_number'];
        } else {
            // Find first waiting
            $waitingQuery = $conn->query("
                SELECT token_number 
                FROM appointments 
                WHERE doctor_id='{$appt['doctor_id']}' 
                AND appointment_date=CURRENT_DATE 
                AND queue_status='Waiting'
                ORDER BY queue_position ASC LIMIT 1
            ");
            $waitAppt = $waitingQuery ? $waitingQuery->fetch_assoc() : null;
            if ($waitAppt) {
                $response['live_token'] = $waitAppt['token_number'];
            } else {
                // Last completed today
                $lastCompletedQuery = $conn->query("
                    SELECT token_number 
                    FROM appointments 
                    WHERE doctor_id='{$appt['doctor_id']}' 
                    AND appointment_date=CURRENT_DATE 
                    AND queue_status='Completed'
                    ORDER BY queue_position DESC LIMIT 1
                ");
                $lastComp = $lastCompletedQuery ? $lastCompletedQuery->fetch_assoc() : null;
                $response['live_token'] = $lastComp ? $lastComp['token_number'] : '-';
            }
        }
        
        // Patients ahead in queue (Pending/Confirmed appointments today with queue_status = 'Waiting' and lower queue_position)
        if ($myPosition !== '-') {
            $aheadQuery = $conn->query("
                SELECT COUNT(*) AS count 
                FROM appointments 
                WHERE doctor_id='{$appt['doctor_id']}' 
                AND appointment_date=CURRENT_DATE 
                AND queue_status='Waiting' 
                AND queue_position < '$myPosition'
            ");
            $patientsAhead = $aheadQuery ? $aheadQuery->fetch_assoc()['count'] : 0;
            $response['patients_ahead'] = $patientsAhead;
        } else {
            $patientsAhead = 0;
            $response['patients_ahead'] = 0;
        }
        
        // Queue Progress calculations
        $completedCountQuery = $conn->query("
            SELECT COUNT(*) AS count 
            FROM appointments 
            WHERE doctor_id='{$appt['doctor_id']}' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Completed'
        ");
        $completedCount = $completedCountQuery ? $completedCountQuery->fetch_assoc()['count'] : 0;
        
        $totalCountQuery = $conn->query("
            SELECT COUNT(*) AS count 
            FROM appointments 
            WHERE doctor_id='{$appt['doctor_id']}' 
            AND appointment_date=CURRENT_DATE
            AND token_number IS NOT NULL
        ");
        $totalCount = $totalCountQuery ? $totalCountQuery->fetch_assoc()['count'] : 0;
        if ($totalCount > 0) {
            $response['queue_progress'] = round(($completedCount / $totalCount) * 100);
        }
        
        // Calculate Estimated Waiting Time based on Doctor Status
        if (in_array($docStatus, ['Break', 'Leave', 'Emergency', 'Offline'])) {
            if ($docStatus === 'Break') {
                $response['estimated_wait'] = 'Delayed (Doctor on Break)';
            } elseif ($docStatus === 'Leave') {
                $response['estimated_wait'] = 'Paused (Doctor on Leave)';
            } elseif ($docStatus === 'Emergency') {
                $response['estimated_wait'] = 'Delayed (Emergency Case)';
            } else {
                $response['estimated_wait'] = 'Paused (Doctor Offline)';
            }
        } else {
            $response['estimated_wait'] = ($patientsAhead * 12) . " mins";
        }
    }
}

echo json_encode($response);
?>
