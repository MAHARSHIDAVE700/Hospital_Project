<?php
session_start();
if (!isset($_SESSION['patient_id']) && !isset($_SESSION['doctor_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Record ID");
}

$query = "
    SELECT r.*, d.full_name AS doctor_name, dep.department_name, u.full_name AS patient_name, pat.phone, pat.age, pat.gender
    FROM medical_records r
    JOIN doctors d ON r.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    JOIN patients pat ON r.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    WHERE r.record_id = $id
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Medical record not found.");
}

$r = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Report #MR-<?= $r['record_id']; ?> | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .report-box { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-top: 6px solid #6f42c1; }
        @media print {
            .no-print { display: none !important; }
            .report-box { box-shadow: none; border-radius: 0; margin: 0; padding: 20px; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-purple text-white btn-lg shadow me-2" style="background-color: #6f42c1;"><i class="bi bi-printer"></i> Print / Download PDF Report</button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg shadow">Close</button>
    </div>

    <div class="report-box">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom">
            <div>
                <h2 class="mb-1" style="color: #6f42c1;">🏥 Narayan Hospital</h2>
                <p class="text-muted mb-0">Confidential Electronic Medical Record (EMR)</p>
            </div>
            <div class="text-end">
                <span class="badge bg-purple text-white px-3 py-2 fs-6" style="background-color: #6f42c1;">Report #MR-<?= $r['record_id']; ?></span>
                <p class="text-muted small mb-0 mt-1">Date: <?= date('d M Y', strtotime($r['created_at'])); ?></p>
            </div>
        </div>

        <!-- Patient Demographics -->
        <div class="row my-4 p-3 bg-light rounded">
            <div class="col-6">
                <p class="mb-1"><strong>Patient Name:</strong> <?= htmlspecialchars($r['patient_name']); ?></p>
                <p class="mb-0"><strong>Contact Phone:</strong> <?= htmlspecialchars($r['phone']); ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>Age / Gender:</strong> <?= htmlspecialchars($r['age']); ?> Yrs / <?= htmlspecialchars($r['gender']); ?></p>
                <p class="mb-0"><strong>Attending Doctor:</strong> Dr. <?= htmlspecialchars($r['doctor_name']); ?> (<?= htmlspecialchars($r['department_name']); ?>)</p>
            </div>
        </div>

        <!-- Medical Record Body -->
        <div class="my-4">
            <div class="mb-4">
                <h5 class="fw-bold text-uppercase fs-7 text-secondary">1. Clinical Diagnosis</h5>
                <div class="p-3 bg-white border rounded">
                    <?= nl2br(htmlspecialchars($r['diagnosis'])); ?>
                </div>
            </div>

            <div class="mb-4">
                <h5 class="fw-bold text-uppercase fs-7 text-secondary">2. Prescribed Treatment & Medications</h5>
                <div class="p-3 bg-white border rounded">
                    <?= nl2br(htmlspecialchars($r['prescription'])); ?>
                </div>
            </div>

            <?php if (!empty($r['notes'])): ?>
            <div class="mb-4">
                <h5 class="fw-bold text-uppercase fs-7 text-secondary">3. Clinical Notes & Diagnostic Summary</h5>
                <div class="p-3 bg-white border rounded">
                    <?= nl2br(htmlspecialchars($r['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Verification -->
        <div class="row pt-5 mt-5 border-top align-items-end">
            <div class="col-6">
                <small class="text-muted d-block">Narayan Health Records System</small>
                <small class="text-muted">Strictly confidential medical document.</small>
            </div>
            <div class="col-6 text-end">
                <div style="border-bottom: 2px solid #333; display: inline-block; width: 180px;" class="mb-2"></div>
                <p class="mb-0 fw-bold">Dr. <?= htmlspecialchars($r['doctor_name']); ?></p>
                <small class="text-muted">Medical Officer in Charge</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>
