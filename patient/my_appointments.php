<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['patient_id'];

$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patient = $getPatient->fetch_assoc();
$patientID = $patient['patient_id'];

$query = "
SELECT
    a.*,
    d.full_name AS doctor_name,
    dep.department_name
FROM appointments a
JOIN doctors d ON a.doctor_id = d.doctor_id
JOIN departments dep ON d.department_id = dep.department_id
WHERE a.patient_id = '$patientID'
ORDER BY a.appointment_date DESC, a.appointment_time DESC
";

$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-modern shadow-sm sticky-top">
    <div class="container">
        <span class="navbar-brand d-flex align-items-center gap-2">🏥 <strong>Smart Hospital</strong></span>
        <a href="dashboard.php" class="btn btn-outline-primary btn-modern py-2 px-3">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
    </div>
</nav>

<div class="container mt-5 mb-5">

    <div class="mb-4">
        <p class="text-secondary mb-1">Patient Portal</p>
        <h2><i class="bi bi-calendar-check-fill text-success"></i> My Appointments</h2>
    </div>

    <div class="card-modern">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clock-history text-success"></i> Appointment History</h5>
            <a href="../appointment.php" class="btn btn-success btn-modern btn-sm px-3">
                <i class="bi bi-plus-circle"></i> Book New
            </a>
        </div>
        <div class="card-body-modern p-0">
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Token</th>
                            <th>Doctor</th>
                            <th>Department</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0) { ?>
                            <?php while ($row = $result->fetch_assoc()) {
                                $statusBadge = match($row['status']) {
                                    'Confirmed' => '<span class="badge bg-primary-subtle text-primary badge-modern">Confirmed</span>',
                                    'Completed' => '<span class="badge bg-success-subtle text-success badge-modern">Completed</span>',
                                    'Cancelled' => '<span class="badge bg-danger-subtle text-danger badge-modern">Cancelled</span>',
                                    default     => '<span class="badge bg-warning-subtle text-warning badge-modern">Pending</span>'
                                };
                                
                                $tokenDisplay = $row['token_number'] 
                                    ? '<span class="badge fs-6 px-3 py-2" style="background:#16a34a22; color:#16a34a; border-radius:8px; font-weight:700;">' . $row['token_number'] . '</span>'
                                    : '<span class="text-secondary small">—</span>';
                                
                                $feeDisplay = $row['opd_fee_paid'] > 0
                                    ? '₹' . number_format($row['opd_fee_paid'], 0)
                                    : '<span class="text-secondary">—</span>';
                            ?>
                            <tr>
                                <td class="fw-semibold">#<?= $row['appointment_id'] ?></td>
                                <td><?= $tokenDisplay ?></td>
                                <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                                <td><?= htmlspecialchars($row['department_name']) ?></td>
                                <td><?= date('d M Y', strtotime($row['appointment_date'])) ?></td>
                                <td><?= date('h:i A', strtotime($row['appointment_time'])) ?></td>
                                <td class="fw-semibold"><?= $feeDisplay ?></td>
                                <td><?= $statusBadge ?></td>
                                <td>
                                    <a href="token_card.php?id=<?= $row['appointment_id'] ?>" 
                                       class="btn btn-sm btn-primary btn-modern px-3 py-1"
                                       target="_blank"
                                       title="View Token Card & QR Code">
                                        <i class="bi bi-qr-code"></i> Token Card
                                    </a>
                                </td>
                            </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div style="font-size: 3rem; opacity: 0.2; margin-bottom: 12px;">📋</div>
                                    <p class="text-secondary mb-2">No appointments found.</p>
                                    <a href="../appointment.php" class="btn btn-primary btn-modern px-4">
                                        <i class="bi bi-calendar-plus"></i> Book Your First Appointment
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-outline-secondary btn-modern px-4">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

</div>

<footer class="text-center py-4 text-secondary border-top">
    Narayan Hospital OPD Management System &copy; <?= date('Y') ?>
</footer>

</body>
</html>