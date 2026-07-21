<?php
session_start();
if (!isset($_SESSION['patient_id']) && !isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$apptId = intval($_GET['id'] ?? 0);
if (!$apptId) {
    die("Invalid appointment ID");
}

$query = "
    SELECT a.*, d.full_name AS doctor_name, u.full_name AS patient_name
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    WHERE a.appointment_id = $apptId
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Appointment not found.");
}

$appt = $res->fetch_assoc();

// Generate unique room name
$roomName = "NarayanHospital_Consultation_Appt_" . $apptId;
$displayName = isset($_SESSION['doctor_id']) ? "Dr. " . $appt['doctor_name'] : $appt['patient_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Video Consultation #<?= $apptId ?> | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://meet.jit.si/external_api.js"></script>
    <style>
        body { background: #1a1a2e; color: #fff; margin: 0; font-family: 'Segoe UI', sans-serif; }
        #meet-container { width: 100%; height: calc(100vh - 70px); }
    </style>
</head>
<body>

<div class="d-flex justify-content-between align-items-center px-4 py-2 bg-dark border-bottom border-secondary">
    <div>
        <h5 class="mb-0 text-white">📹 Online Video Consultation</h5>
        <small class="text-muted">Appt #<?= $apptId ?> | Dr. <?= htmlspecialchars($appt['doctor_name']) ?> &amp; <?= htmlspecialchars($appt['patient_name']) ?></small>
    </div>
    <div>
        <button onclick="window.close()" class="btn btn-outline-danger btn-sm">Leave Room</button>
    </div>
</div>

<div id="meet-container"></div>

<script>
const domain = 'meet.jit.si';
const options = {
    roomName: '<?= $roomName ?>',
    width: '100%',
    height: '100%',
    parentNode: document.querySelector('#meet-container'),
    userInfo: {
        displayName: '<?= htmlspecialchars($displayName) ?>'
    },
    configOverwrite: {
        startWithAudioMuted: false,
        startWithVideoMuted: false
    }
};
const api = new JitsiMeetExternalAPI(domain, options);
</script>

</body>
</html>
