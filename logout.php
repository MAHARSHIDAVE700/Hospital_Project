<?php

session_start();
include "includes/config.php";

if (isset($_SESSION['patient_id'])) {
    ActivityLogger::log($_SESSION['patient_id'], 'patient', 'Logout', 'Patient logged out');
} elseif (isset($_SESSION['doctor_id'])) {
    ActivityLogger::log($_SESSION['doctor_id'], 'doctor', 'Logout', 'Doctor logged out');
    $docEmail = $_SESSION['doctor_email'] ?? '';
    if (empty($docEmail)) {
        $docUserId = $_SESSION['doctor_id'];
        $uRow = $conn->query("SELECT email FROM users WHERE id='$docUserId'")->fetch_assoc();
        $docEmail = $uRow ? $uRow['email'] : '';
    }
    if (!empty($docEmail)) {
        $emailEsc = $conn->real_escape_string($docEmail);
        $conn->query("UPDATE doctors SET status='Offline', last_active_at=NULL WHERE LOWER(email)=LOWER('$emailEsc')");
    }
} elseif (isset($_SESSION['admin_id'])) {
    ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Logout', 'Administrator logged out');
}

session_destroy();

header("Location: login.php");

exit();

?>
