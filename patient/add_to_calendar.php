<?php
session_start();
include "../includes/config.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid appointment ID");
}

$query = "
    SELECT a.*, d.full_name AS doctor_name, dep.department_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN departments dep ON d.department_id = dep.department_id
    WHERE a.appointment_id = $id
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Appointment not found");
}

$appt = $res->fetch_assoc();

$title = urlencode("Appointment with Dr. " . $appt['doctor_name'] . " (" . $appt['department_name'] . ")");
$details = urlencode("Hospital OPD Appointment at Narayan Hospital. Token: #" . ($appt['token_number'] ?? 'Pending'));
$location = urlencode("Narayan Hospital, Halvad, Gujarat");

// Format dates for Google Calendar (YYYYMMDDTHHISZ)
$startTimeStr = $appt['appointment_date'] . ' ' . $appt['appointment_time'];
$startDT = new DateTime($startTimeStr);
$endDT = clone $startDT;
$endDT->modify('+30 minutes');

$dates = $startDT->format('Ymd\THis') . '/' . $endDT->format('Ymd\THis');

$googleUrl = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$dates}&details={$details}&location={$location}";

header("Location: " . $googleUrl);
exit();
?>
