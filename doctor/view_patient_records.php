<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if (!isset($_GET['patient_id']) || !is_numeric($_GET['patient_id'])) {
    die("Invalid patient ID.");
}

$patientID = intval($_GET['patient_id']);

// Fetch Patient Info
$patientQuery = $conn->prepare("
    SELECT u.full_name, u.email, p.phone, p.gender, p.age, p.address
    FROM patients p
    JOIN users u ON p.user_id = u.id
    WHERE p.patient_id = ?
");
$patientQuery->bind_param("i", $patientID);
$patientQuery->execute();
$patient = $patientQuery->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient record not found.");
}

// Fetch Medical Records
$recordsQuery = $conn->prepare("
    SELECT r.record_id, r.diagnosis, r.prescription, r.notes, r.created_at, d.full_name AS doctor_name, dep.department_name
    FROM medical_records r
    LEFT JOIN doctors d ON r.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    WHERE r.patient_id = ?
    ORDER BY r.created_at DESC
");
$recordsQuery->bind_param("i", $patientID);
$recordsQuery->execute();
$records = $recordsQuery->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Medical History | Narayan Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5 mb-5">
    <!-- Back to Dashboard -->
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-secondary px-3 py-2">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Patient Info Card -->
    <div class="card-modern mb-4 p-4">
        <div class="d-flex align-items-center gap-3">
            <div class="avatar-circle-lg bg-primary-subtle text-primary fs-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 50%;">
                <?= strtoupper(substr($patient['full_name'], 0, 2)) ?>
            </div>
            <div>
                <h3 class="mb-1"><?= htmlspecialchars($patient['full_name']) ?></h3>
                <p class="text-secondary mb-0">
                    <span class="me-3"><strong>Age:</strong> <?= htmlspecialchars($patient['age']) ?> yrs</span>
                    <span class="me-3"><strong>Gender:</strong> <?= htmlspecialchars($patient['gender']) ?></span>
                    <span class="me-3"><strong>Phone:</strong> <?= htmlspecialchars($patient['phone'] ?? 'N/A') ?></span>
                    <span><strong>Email:</strong> <?= htmlspecialchars($patient['email']) ?></span>
                </p>
            </div>
        </div>
    </div>

    <!-- Medical History List -->
    <div class="card-modern">
        <div class="card-header-modern bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-file-earmark-medical-fill"></i> Patient Medical History & Records</h4>
        </div>
        <div class="card-body-modern p-4">
            <?php if ($records && $records->num_rows > 0) { ?>
                <div class="timeline">
                    <?php while ($rec = $records->fetch_assoc()) { ?>
                        <div class="border rounded p-3 mb-3 bg-white shadow-sm" style="border-left: 5px solid var(--primary-color) !important;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary-subtle text-primary">
                                    <i class="bi bi-person-badge"></i> Dr. <?= htmlspecialchars($rec['doctor_name'] ?? 'Hospital Staff') ?> (<?= htmlspecialchars($rec['department_name'] ?? 'General') ?>)
                                </span>
                                <span class="text-secondary small">
                                    <i class="bi bi-calendar3"></i> <?= date('d M Y, h:i A', strtotime($rec['created_at'])) ?>
                                </span>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <strong>Diagnosis:</strong>
                                    <p class="text-dark mb-0 bg-light p-2 rounded mt-1"><?= nl2br(htmlspecialchars($rec['diagnosis'])) ?></p>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <strong>Prescription:</strong>
                                    <p class="text-dark mb-0 bg-light p-2 rounded mt-1"><?= nl2br(htmlspecialchars($rec['prescription'])) ?></p>
                                </div>
                                <?php if (!empty($rec['notes'])) { ?>
                                    <div class="col-12">
                                        <strong>Notes:</strong>
                                        <p class="text-secondary mb-0 bg-light p-2 rounded mt-1"><?= nl2br(htmlspecialchars($rec['notes'])) ?></p>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="text-center py-5 text-secondary">
                    <i class="bi bi-folder-x display-1 text-muted d-block mb-3"></i>
                    <h5>No Medical Records Found</h5>
                    <p>There are no past clinical records or prescription reports stored for this patient.</p>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
