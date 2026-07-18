<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// Check required parameters
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    header("Location: manage_appointments.php");
    exit();
}

$id = (int)$_GET['id'];
$status = trim($_GET['status']);

// Allowed status values
$allowedStatus = ["Pending", "Confirmed", "Completed", "Cancelled"];

if (!in_array($status, $allowedStatus)) {
    die("Invalid Status");
}

$stmt = $conn->prepare("UPDATE appointments SET status=? WHERE appointment_id=?");
$stmt->bind_param("si", $status, $id);

if ($stmt->execute()) {
    // Generate daily token if confirmed
    if ($status === 'Confirmed') {
        $checkTokenQuery = $conn->query("SELECT token_number FROM appointments WHERE appointment_id = $id");
        if ($checkTokenQuery && $checkRow = $checkTokenQuery->fetch_assoc()) {
            if (empty($checkRow['token_number'])) {
                $apptQuery = $conn->query("SELECT doctor_id, appointment_date, appointment_time FROM appointments WHERE appointment_id = $id");
                if ($apptQuery && $appt = $apptQuery->fetch_assoc()) {
                    $doctorID = $appt['doctor_id'];
                    $apptDate = $appt['appointment_date'];
                    $apptTime = $appt['appointment_time'];
                    
                    $countQuery = $conn->query("
                        SELECT COUNT(*) AS count 
                        FROM appointments 
                        WHERE doctor_id = '$doctorID' 
                        AND appointment_date = '$apptDate' 
                        AND token_number IS NOT NULL
                    ");
                    $count = $countQuery ? $countQuery->fetch_assoc()['count'] : 0;
                    $nextSequence = $count + 1;
                    $tokenNumber = 'A' . sprintf('%03d', $nextSequence);
                    
                    $conn->query("
                        UPDATE appointments 
                        SET token_number = '$tokenNumber', 
                            queue_position = '$nextSequence', 
                            queue_status = 'Waiting',
                            est_consultation_time = '$apptTime',
                            check_in_status = 1
                        WHERE appointment_id = $id
                    ");
                }
            }
        }
    } elseif ($status === 'Completed') {
        $conn->query("UPDATE appointments SET queue_status = 'Completed' WHERE appointment_id = $id");
    } elseif ($status === 'Cancelled') {
        $conn->query("UPDATE appointments SET queue_status = 'Skipped' WHERE appointment_id = $id");
    }

    header("Location: manage_appointments.php?updated=1");
    exit();
} else {
    echo "Failed to update appointment.";
}
?>