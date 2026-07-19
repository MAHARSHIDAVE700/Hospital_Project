<?php
session_start();
if (!isset($_SESSION['patient_id']) && !isset($_SESSION['doctor_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Prescription ID");
}

$query = "
    SELECT p.*, d.full_name AS doctor_name, dep.department_name, u.full_name AS patient_name, pat.phone, pat.age, pat.gender
    FROM prescriptions p
    JOIN doctors d ON p.doctor_id = d.doctor_id
    JOIN departments dep ON d.department_id = dep.department_id
    JOIN patients pat ON p.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    WHERE p.prescription_id = $id
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Prescription not found.");
}

$p = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prescription #<?= $p['prescription_id']; ?> | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .prescription-box { max-width: 800px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-top: 6px solid #0d6efd; }
        .header-title { font-weight: 700; color: #0d6efd; }
        .rx-symbol { font-size: 32px; font-weight: bold; color: #0d6efd; font-family: Georgia, serif; }
        @media print {
            .no-print { display: none !important; }
            .prescription-box { box-shadow: none; border-radius: 0; margin: 0; padding: 20px; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary btn-lg shadow me-2"><i class="bi bi-printer"></i> Print / Download PDF</button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg shadow">Close</button>
    </div>

    <div class="prescription-box">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom">
            <div>
                <h2 class="header-title mb-1">🏥 Narayan Hospital</h2>
                <p class="text-muted mb-0">Multi-Specialty OPD & Research Center</p>
                <small class="text-muted">123 Health Ave, Medical Zone | Emergency Contact: +91 98765 43210</small>
            </div>
            <div class="text-end">
                <h5 class="mb-0">Dr. <?= htmlspecialchars($p['doctor_name']); ?></h5>
                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($p['department_name']); ?></span>
            </div>
        </div>

        <!-- Patient Info -->
        <div class="row my-4 p-3 bg-light rounded">
            <div class="col-6">
                <p class="mb-1"><strong>Patient Name:</strong> <?= htmlspecialchars($p['patient_name']); ?></p>
                <p class="mb-0"><strong>Age / Gender:</strong> <?= htmlspecialchars($p['age']); ?> Yrs / <?= htmlspecialchars($p['gender']); ?></p>
            </div>
            <div class="col-6 text-end">
                <p class="mb-1"><strong>Prescription ID:</strong> #<?= $p['prescription_id']; ?></p>
                <p class="mb-0"><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($p['created_at'])); ?></p>
            </div>
        </div>

        <!-- RX Body -->
        <div class="my-4">
            <div class="mb-3">
                <h6 class="text-secondary fw-bold text-uppercase fs-7">Diagnosis / Findings</h6>
                <div class="p-3 bg-white border rounded">
                    <?= nl2br(htmlspecialchars($p['diagnosis'])); ?>
                </div>
            </div>

            <div class="my-4">
                <div class="rx-symbol mb-2">Rx</div>
                <h6 class="text-secondary fw-bold text-uppercase fs-7">Prescribed Medicines</h6>
                <div class="p-3 bg-white border rounded" style="min-height: 120px;">
                    <?= nl2br(htmlspecialchars($p['medicines'])); ?>
                </div>
            </div>

            <?php if (!empty($p['notes'])): ?>
            <div class="mb-3">
                <h6 class="text-secondary fw-bold text-uppercase fs-7">Additional Instructions & Advice</h6>
                <div class="p-3 bg-white border rounded">
                    <?= nl2br(htmlspecialchars($p['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer / Signature -->
        <div class="row pt-5 mt-5 border-top align-items-end">
            <div class="col-6">
                <small class="text-muted d-block">This is a computer-generated medical prescription.</small>
                <small class="text-muted">Valid across all registered pharmacies.</small>
            </div>
            <div class="col-6 text-end">
                <div style="border-bottom: 2px solid #333; display: inline-block; width: 180px;" class="mb-2"></div>
                <p class="mb-0 fw-bold">Dr. <?= htmlspecialchars($p['doctor_name']); ?></p>
                <small class="text-muted">Authorized Medical Practitioner</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>
