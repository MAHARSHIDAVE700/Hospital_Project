<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// Fetch appointment status distribution
$statusRes = $conn->query("SELECT status, COUNT(*) AS count FROM appointments GROUP BY status");
$statusData = ['Pending' => 0, 'Confirmed' => 0, 'Completed' => 0, 'Cancelled' => 0];
if ($statusRes) {
    while ($r = $statusRes->fetch_assoc()) {
        $statusData[$r['status']] = (int)$r['count'];
    }
}

// Fetch doctor performance (appointments per doctor)
$docRes = $conn->query("
    SELECT d.full_name, COUNT(a.appointment_id) AS appt_count
    FROM doctors d
    LEFT JOIN appointments a ON d.doctor_id = a.doctor_id
    GROUP BY d.doctor_id, d.full_name
    ORDER BY appt_count DESC LIMIT 5
");
$docNames = [];
$docApptCounts = [];
if ($docRes) {
    while ($r = $docRes->fetch_assoc()) {
        $docNames[] = $r['full_name'];
        $docApptCounts[] = (int)$r['appt_count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>📊 Hospital Analytics &amp; Performance Reports</h2>
            <p class="text-muted mb-0">Visual Insights for Appointments, Doctors, &amp; Revenue</p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary"><i class="bi bi-speedometer2"></i> Dashboard</a>
    </div>

    <div class="row g-4 mb-4">
        <!-- Chart 1: Status Distribution -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-4 h-100">
                <h5 class="fw-bold text-primary mb-3">Appointment Status Breakdown</h5>
                <canvas id="statusChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Chart 2: Doctor Performance -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 rounded-4 p-4 h-100">
                <h5 class="fw-bold text-success mb-3">Doctor Consultation Volume</h5>
                <canvas id="docChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Status Chart
const ctx1 = document.getElementById('statusChart').getContext('2d');
new Chart(ctx1, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
        datasets: [{
            data: [<?= $statusData['Pending'] ?>, <?= $statusData['Confirmed'] ?>, <?= $statusData['Completed'] ?>, <?= $statusData['Cancelled'] ?>],
            backgroundColor: ['#ffc107', '#0d6efd', '#198754', '#dc3545']
        }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});

// Doctor Chart
const ctx2 = document.getElementById('docChart').getContext('2d');
new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: <?= json_encode($docNames) ?>,
        datasets: [{
            label: 'Appointments Handled',
            data: <?= json_encode($docApptCounts) ?>,
            backgroundColor: '#198754'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true } }
    }
});
</script>

</body>
</html>
