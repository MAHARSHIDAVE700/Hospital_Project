<?php
session_start();

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['doctor_id'])) {
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

if ($status === 'Completed') {
    $rxCheck = $conn->query("SELECT prescription_id FROM prescriptions WHERE appointment_id = $id");
    if (!$rxCheck || $rxCheck->num_rows == 0) {
        if (isset($_SESSION['doctor_id'])) {
            header("Location: ../doctor/write_prescription.php?id=$id&error=rx_required");
            exit();
        } else {
            die("<div style='font-family:sans-serif; text-align:center; margin-top:50px;'><h2>Prescription Required</h2><p>An appointment cannot be marked as Completed until the doctor writes a mandatory prescription for it.</p><a href='manage_appointments.php'>Back to Appointments</a></div>");
        }
    }
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
                    
                    $tokenNumber = getNextContinuousToken($conn);
                    
                    $conn->query("
                        UPDATE appointments 
                        SET token_number = '$tokenNumber', 
                            queue_position = '$tokenNumber', 
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

    // Fetch details for email & in-app notification
    try {
        $detailsQuery = $conn->query("
            SELECT u.id AS user_id, u.email, u.full_name AS patient_name, d.full_name AS doctor_name, a.appointment_date, a.appointment_time, a.token_number
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN users u ON p.user_id = u.id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.appointment_id = $id
        ");
        if ($detailsQuery && $row = $detailsQuery->fetch_assoc()) {
            include_once "../includes/notification_helper.php";
            if ($status === 'Confirmed') {
                NotificationHelper::add($row['user_id'], 'Appointment Confirmed', 'Your appointment with Dr. ' . $row['doctor_name'] . ' has been approved. Token #' . ($row['token_number'] ?? 'N/A'));
                if (!empty($row['email'])) {
                    include_once "../includes/email_helper.php";
                    EmailHelper::sendAppointmentApproval($row['email'], $row['patient_name'], $row['doctor_name'], $row['appointment_date'], $row['appointment_time'], $row['token_number'] ?? '-');
                }
            } elseif ($status === 'Cancelled') {
                NotificationHelper::add($row['user_id'], 'Appointment Cancelled', 'Your appointment with Dr. ' . $row['doctor_name'] . ' has been cancelled.');
                if (!empty($row['email'])) {
                    include_once "../includes/email_helper.php";
                    EmailHelper::sendAppointmentCancellation($row['email'], $row['patient_name'], $row['doctor_name'], $row['appointment_date'], $row['appointment_time']);
                }
            }
        }
    } catch (Exception $e) { /* ignore notification errors */ }

    if (isset($_SESSION['doctor_id'])) {
        header("Location: ../doctor/my_appointments.php?updated=1");
    } else {
        header("Location: manage_appointments.php?updated=1");
    }
    exit();
} else {
    echo "Failed to update appointment.";
}
?>
