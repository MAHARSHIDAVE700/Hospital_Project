<?php
session_start();
if (!isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Appointment ID");
}

$query = "
    SELECT a.*, d.full_name AS doctor_name, dep.department_name, u.full_name AS patient_name, pat.phone
    FROM appointments a
    JOIN doctors d ON a.doctor_id = d.doctor_id
    JOIN departments dep ON d.department_id = dep.department_id
    JOIN patients pat ON a.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    WHERE a.appointment_id = $id
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Appointment record not found.");
}

$a = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OPD Receipt #<?= $a['appointment_id']; ?> | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .receipt-card { max-width: 650px; margin: 30px auto; background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border-top: 5px solid #198754; }
        @media print {
            .no-print { display: none !important; }
            .receipt-card { box-shadow: none; border-radius: 0; margin: 0; padding: 20px; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg shadow me-2"><i class="bi bi-printer"></i> Print / Save PDF</button>
        <button onclick="window.close()" class="btn btn-secondary btn-lg shadow">Close</button>
    </div>

    <div class="receipt-card">
        <!-- Header -->
        <div class="text-center pb-3 border-bottom">
            <h2 class="text-success font-weight-bold mb-1">🏥 Narayan Hospital</h2>
            <p class="text-muted mb-0">Official OPD Fee Receipt</p>
            <small class="text-muted">GSTIN: 24AAACN1234F1Z9 | Reg No: NH/2026/789</small>
        </div>

        <div class="d-flex justify-content-between align-items-center my-3">
            <div>
                <strong>Receipt No:</strong> REC-<?= sprintf('%05d', $a['appointment_id']); ?>
            </div>
            <div>
                <strong>Date:</strong> <?= date('d M Y'); ?>
            </div>
        </div>

        <!-- Details -->
        <table class="table table-bordered my-3">
            <tbody>
                <tr>
                    <th class="bg-light" style="width: 35%;">Patient Name</th>
                    <td><?= htmlspecialchars($a['patient_name']); ?></td>
                </tr>
                <tr>
                    <th class="bg-light">Phone</th>
                    <td><?= htmlspecialchars($a['phone']); ?></td>
                </tr>
                <tr>
                    <th class="bg-light">Consulting Doctor</th>
                    <td>Dr. <?= htmlspecialchars($a['doctor_name']); ?> (<?= htmlspecialchars($a['department_name']); ?>)</td>
                </tr>
                <tr>
                    <th class="bg-light">Appointment Slot</th>
                    <td><?= htmlspecialchars($a['appointment_date']); ?> at <?= htmlspecialchars($a['appointment_time']); ?></td>
                </tr>
                <tr>
                    <th class="bg-light">Queue Token No</th>
                    <td><span class="badge bg-success fs-6"><?= $a['token_number'] ?? 'Pending Token'; ?></span></td>
                </tr>
                <tr>
                    <th class="bg-light">OPD Consultation Fee</th>
                    <td class="fw-bold text-success">₹<?= number_format($a['opd_fee_paid'] ?? 200, 2); ?></td>
                </tr>
                <tr>
                    <th class="bg-light">Payment Status</th>
                    <td><span class="badge bg-primary"><?= htmlspecialchars($a['fee_status'] ?? 'Paid'); ?></span></td>
                </tr>
            </tbody>
        </table>

        <!-- Footer -->
        <div class="pt-4 border-top text-center text-muted small">
            <p class="mb-1">Thank you for choosing Narayan Hospital. Wish you a speedy recovery!</p>
            <p class="mb-0">This receipt is electronically issued and valid without signature.</p>
        </div>
    </div>
</div>

</body>
</html>
