<?php
session_start();

if (!isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// Validate ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid appointment.");
}

$apptId = intval($_GET['id']);

if (isset($_SESSION['admin_id'])) {
    // Admin access: bypass patient ownership checks
    $apptQuery = $conn->query("
        SELECT 
            a.appointment_id, a.appointment_date, a.appointment_time,
            a.status, a.token_number, a.queue_position, a.queue_status,
            COALESCE(a.opd_fee_paid, 0) AS opd_fee_paid,
            COALESCE(a.fee_status, 'Pending') AS fee_status,
            COALESCE(u.full_name, 'Patient') AS patient_name,
            u.email AS patient_email,
            p.phone AS patient_phone,
            p.age,
            COALESCE(d.full_name, 'Doctor') AS doctor_name,
            d.specialization,
            COALESCE(dep.department_name, 'General') AS department_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN departments dep ON d.department_id = dep.department_id
        WHERE a.appointment_id='$apptId'
        LIMIT 1
    ");
} else {
    // Patient access
    $userID = $_SESSION['patient_id'];
    $patientQuery = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
    $patient = $patientQuery->fetch_assoc();
    $patientID = $patient ? $patient['patient_id'] : null;

    if (!$patientID) die("Patient not found.");

    $apptQuery = $conn->query("
        SELECT 
            a.appointment_id, a.appointment_date, a.appointment_time,
            a.status, a.token_number, a.queue_position, a.queue_status,
            COALESCE(a.opd_fee_paid, 0) AS opd_fee_paid,
            COALESCE(a.fee_status, 'Pending') AS fee_status,
            COALESCE(u.full_name, 'Patient') AS patient_name,
            u.email AS patient_email,
            p.phone AS patient_phone,
            p.age,
            COALESCE(d.full_name, 'Doctor') AS doctor_name,
            d.specialization,
            COALESCE(dep.department_name, 'General') AS department_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN doctors d ON a.doctor_id = d.doctor_id
        LEFT JOIN departments dep ON d.department_id = dep.department_id
        WHERE a.appointment_id='$apptId' AND a.patient_id='$patientID'
        LIMIT 1
    ");
}

$appt = $apptQuery ? $apptQuery->fetch_assoc() : null;

if (!$appt) {
    die("
        <div style='font-family:sans-serif;text-align:center;padding:60px 20px;'>
            <div style='font-size:3rem;'>⚠️</div>
            <h3>Appointment Not Found</h3>
            <p style='color:#6b7280;'>This appointment does not exist or you don't have access to it.</p>
            <a href='my_appointments.php' style='background:#0d6efd;color:white;padding:10px 24px;border-radius:10px;text-decoration:none;'>← My Appointments</a>
        </div>
    ");
}

// Build QR payload — compact yet complete
$qrPayload = "HOSP-TOKEN\n"
    . "Appt: #" . $appt['appointment_id'] . "\n"
    . "Patient: " . $appt['patient_name'] . "\n"
    . "Doctor: Dr. " . $appt['doctor_name'] . "\n"
    . "Dept: " . $appt['department_name'] . "\n"
    . "Date: " . date('d M Y', strtotime($appt['appointment_date'])) . "\n"
    . "Time: " . date('h:i A', strtotime($appt['appointment_time'])) . "\n"
    . "Token: " . ($appt['token_number'] ?: "Pending Confirmation") . "\n"
    . "Fee: Rs." . number_format($appt['opd_fee_paid'], 2);

// Encode for QR API
$qrData = urlencode($qrPayload);

// Status badge colour
$statusColor = [
    'Pending'   => '#f59e0b',
    'Confirmed' => '#3b82f6',
    'Completed' => '#22c55e',
    'Cancelled' => '#ef4444',
][$appt['status']] ?? '#6b7280';

$queueStatusColor = [
    'Waiting'   => '#f59e0b',
    'Called'    => '#3b82f6',
    'Completed' => '#22c55e',
    'Skipped'   => '#ef4444',
][$appt['queue_status']] ?? '#6b7280';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPD Token Card | <?= htmlspecialchars($appt['patient_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 15px;
        }

        .token-wrapper {
            width: 100%;
            max-width: 520px;
        }

        .token-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.25);
        }

        /* Header */
        .token-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 100%);
            color: white;
            padding: 28px 32px 20px;
            position: relative;
            overflow: hidden;
        }
        .token-header::before {
            content: '';
            position: absolute;
            right: -30px; top: -30px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }
        .token-header::after {
            content: '';
            position: absolute;
            right: 60px; bottom: -50px;
            width: 100px; height: 100px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .hospital-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header-sub { font-size: 13px; opacity: 0.75; margin-top: 2px; }

        /* Token Number */
        .token-number-section {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-bottom: 2px dashed #bbf7d0;
            padding: 24px 32px;
            text-align: center;
        }
        .token-label { font-size: 12px; font-weight: 700; letter-spacing: 2px; color: #6b7280; text-transform: uppercase; }
        .token-number {
            font-size: 72px;
            font-weight: 800;
            color: #16a34a;
            line-height: 1;
            margin: 8px 0 4px;
            letter-spacing: -2px;
        }
        .token-pending {
            font-size: 22px;
            font-weight: 700;
            color: #f59e0b;
            line-height: 1;
            margin: 8px 0 4px;
        }
        .token-status-chip {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        /* QR Section */
        .qr-section {
            padding: 24px 32px;
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        .qr-image-wrap {
            border: 3px solid #e5e7eb;
            border-radius: 16px;
            padding: 8px;
            background: #fafafa;
            flex-shrink: 0;
        }
        .qr-image-wrap img { border-radius: 10px; display: block; }

        .appt-details { flex: 1; }
        .detail-row {
            display: flex;
            flex-direction: column;
            margin-bottom: 12px;
        }
        .detail-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            color: #9ca3af;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
        }

        /* Info row */
        .info-bar {
            display: flex;
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
        }
        .info-item {
            flex: 1;
            text-align: center;
            padding: 14px 10px;
            border-right: 1px solid #f3f4f6;
        }
        .info-item:last-child { border-right: none; }
        .info-item-label { font-size: 10px; color: #9ca3af; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .info-item-value { font-size: 14px; font-weight: 700; color: #1f2937; margin-top: 2px; }

        /* Footer */
        .token-footer {
            background: #1e3a8a;
            color: rgba(255,255,255,0.7);
            text-align: center;
            padding: 14px;
            font-size: 12px;
        }
        .token-footer strong { color: white; }

        /* Fee Receipt Section */
        .fee-section {
            padding: 14px 32px;
            background: #fffbeb;
            border-top: 1px dashed #fde68a;
            border-bottom: 1px dashed #fde68a;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .fee-label { font-size: 13px; font-weight: 700; color: #92400e; }
        .fee-amount { font-size: 18px; font-weight: 800; color: #b45309; }
        .fee-status-chip {
            font-size: 11px; font-weight: 700; letter-spacing: 0.5px;
            padding: 3px 10px; border-radius: 50px;
            background: #d1fae5; color: #065f46;
        }

        /* Actions */
        .action-bar {
            padding: 20px 32px;
            display: flex;
            gap: 12px;
            border-top: 1px solid #f3f4f6;
        }
        .btn-token-print {
            flex: 1;
            background: #1e3a8a; color: white;
            border: none; border-radius: 12px;
            padding: 12px; font-weight: 700;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-token-print:hover { background: #1d4ed8; }
        .btn-back {
            flex: 1;
            background: #f3f4f6; color: #374151;
            border: none; border-radius: 12px;
            padding: 12px; font-weight: 700;
            text-decoration: none; text-align: center;
            transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-back:hover { background: #e5e7eb; color: #111827; }

        @media print {
            body { background: white; padding: 0; }
            .action-bar { display: none !important; }
            .token-card { box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="token-wrapper">

    <div class="token-card">

        <!-- Header -->
        <div class="token-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="hospital-name">🏥 Narayan Hospital</div>
                    <div class="header-sub">OPD Appointment Token Card</div>
                </div>
                <div style="font-size: 32px; opacity: 0.4;">🎫</div>
            </div>
        </div>

        <!-- Token Number -->
        <div class="token-number-section">
            <div class="token-label">Your OPD Token Number</div>
            <?php if ($appt['token_number']) { ?>
                <div class="token-number"><?= htmlspecialchars($appt['token_number']) ?></div>
                <div>
                    <span class="token-status-chip" style="background: <?= $queueStatusColor ?>22; color: <?= $queueStatusColor ?>;">
                        Queue Status: <?= $appt['queue_status'] ?>
                    </span>
                </div>
            <?php } else { ?>
                <div class="token-pending">⏳ Pending Confirmation</div>
                <div class="small text-secondary mt-1">Your token will be assigned once admin confirms the appointment.</div>
            <?php } ?>
        </div>

        <!-- QR + Appointment Details -->
        <div class="qr-section">
            <div class="qr-image-wrap">
                <!-- Using QR Server API (no JS library needed, works offline via CDN) -->
                <img 
                    src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= $qrData ?>" 
                    alt="OPD Token QR Code"
                    width="160" height="160"
                    id="qrImg">
                <div style="text-align:center; font-size:10px; color:#9ca3af; margin-top:6px; font-weight:600;">SCAN FOR INFO</div>
            </div>

            <div class="appt-details">
                <div class="detail-row">
                    <span class="detail-label">Patient Name</span>
                    <span class="detail-value"><?= htmlspecialchars($appt['patient_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Doctor</span>
                    <span class="detail-value">Dr. <?= htmlspecialchars($appt['doctor_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Department</span>
                    <span class="detail-value"><?= htmlspecialchars($appt['department_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">
                        <span style="color: <?= $statusColor ?>; font-weight: 700;">● <?= $appt['status'] ?></span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Bar -->
        <div class="info-bar">
            <div class="info-item">
                <div class="info-item-label">Date</div>
                <div class="info-item-value"><?= date('d M Y', strtotime($appt['appointment_date'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">Time Slot</div>
                <div class="info-item-value"><?= date('h:i A', strtotime($appt['appointment_time'])) ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">Queue Pos.</div>
                <div class="info-item-value"><?= $appt['queue_position'] ?: '—' ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">Appt. ID</div>
                <div class="info-item-value">#<?= $appt['appointment_id'] ?></div>
            </div>
        </div>

        <!-- Fee Receipt -->
        <div class="fee-section">
            <div>
                <div class="fee-label"><i class="bi bi-receipt"></i> OPD Registration Fee</div>
                <div class="small text-muted mt-1">Payable at OPD counter on arrival</div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="fee-amount">₹<?= number_format($appt['opd_fee_paid'], 2) ?></div>
                <span class="fee-status-chip">
                    <?= $appt['fee_status'] ?: 'Pending' ?>
                </span>
            </div>
        </div>

        <!-- Action Buttons (non-printable) -->
        <div class="action-bar flex-wrap">
            <button class="btn-token-print" onclick="window.print()">
                <i class="bi bi-printer-fill"></i> Print Token Card
            </button>
            <a href="download_receipt.php?id=<?= $appt['appointment_id'] ?>" target="_blank" class="btn btn-outline-success">
                <i class="bi bi-receipt"></i> Print Receipt
            </a>
            <a href="add_to_calendar.php?id=<?= $appt['appointment_id'] ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-calendar-plus"></i> Google Calendar
            </a>
            <a href="my_appointments.php" class="btn-back">
                <i class="bi bi-arrow-left"></i> Appointments
            </a>
            <a href="../patient/dashboard.php" class="btn-back">
                <i class="bi bi-house-fill"></i> Dashboard
            </a>
        </div>

        <!-- Footer -->
        <div class="token-footer">
            Please arrive <strong>15 minutes early</strong>. Bring this token card to the OPD counter.
            <br>Narayan Hospital &bull; Smart OPD System &bull; <?= date('Y') ?>
        </div>

    </div>

</div>

</body>
</html>
