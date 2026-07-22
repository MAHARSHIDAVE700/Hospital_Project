<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['patient_id'];

// Get patient_id
$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patient = $getPatient->fetch_assoc();
$patientID = $patient ? $patient['patient_id'] : 0;

$admissions = $conn->query("
    SELECT ipd.*, d.full_name AS doctor_name, b.bed_number, b.bed_type
    FROM ipd_admissions ipd
    JOIN doctors d ON ipd.doctor_id = d.doctor_id
    JOIN beds b ON ipd.bed_id = b.bed_id
    WHERE ipd.patient_id = '$patientID'
    ORDER BY ipd.admission_date DESC
");

// Total stay metrics
$totalStays = $admissions->num_rows;
$activeAdmissions = $conn->query("SELECT COUNT(*) AS total FROM ipd_admissions WHERE patient_id='$patientID' AND status='Admitted'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My IPD Admissions | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .timeline-item {
            position: relative;
            padding-left: 30px;
            border-left: 2px solid var(--border-color);
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 5px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary-color);
        }
    </style>
</head>
<body class="bg-light">

<div class="hms-layout">
    <!-- Sidebar -->
    <aside class="hms-sidebar" id="sidebar">
        <div class="hms-sidebar-brand">
            <span>🏥</span>
            <strong>Narayan Clinic</strong>
        </div>
        <div class="hms-sidebar-menu">
            <div class="hms-sidebar-group-title">Patient Portal</div>
            <a href="dashboard.php" class="hms-sidebar-item">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            
            <div class="hms-sidebar-group-title">OPD Appointments</div>
            <a href="../appointment.php" class="hms-sidebar-item">
                <i class="bi bi-calendar-plus"></i> Book Appointment
            </a>
            <a href="my_appointments.php" class="hms-sidebar-item">
                <i class="bi bi-calendar-check"></i> My Appointments
            </a>
            <a href="live_queue.php" class="hms-sidebar-item">
                <i class="bi bi-people-fill"></i> Live Queue Status
            </a>
            
            <div class="hms-sidebar-group-title">Medical Records</div>
            <a href="my_prescriptions.php" class="hms-sidebar-item">
                <i class="bi bi-prescription"></i> Prescriptions
            </a>
            <a href="my_lab_reports.php" class="hms-sidebar-item">
                <i class="bi bi-virus2"></i> Lab Reports
            </a>
            <a href="my_pharmacy_bills.php" class="hms-sidebar-item">
                <i class="bi bi-receipt"></i> Pharmacy Bills
            </a>
            <a href="my_bills.php" class="hms-sidebar-item">
                <i class="bi bi-wallet2"></i> Invoices & Bills
            </a>
            <a href="my_admissions.php" class="hms-sidebar-item active">
                <i class="bi bi-hospital"></i> Clinical Admissions
            </a>
            <a href="symptom_checker.php" class="hms-sidebar-item">
                <i class="bi bi-heart-pulse"></i> Symptom Checker
            </a>
            
            <div class="hms-sidebar-group-title">Emergency Services</div>
            <a href="ambulance.php" class="hms-sidebar-item text-danger">
                <i class="bi bi-ambulance"></i> Ambulance Requests
            </a>
        </div>
        <div class="hms-sidebar-footer">
            <a href="../logout.php" class="hms-sidebar-item text-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="hms-main" id="main-content">
        <!-- Topbar -->
        <header class="hms-topbar">
            <div class="hms-topbar-left">
                <button class="hms-sidebar-toggle" id="sidebar-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <div class="hms-breadcrumb">
                    <span>Patient Portal</span>
                    <span><i class="bi bi-chevron-right text-muted fs-8"></i></span>
                    <span class="hms-breadcrumb-item-active">In-Patient Admissions History</span>
                </div>
            </div>
            <div class="hms-topbar-right">
                <div class="live-clock-widget me-3">
                    <i class="bi bi-clock"></i>
                    <span><?= date('D, M d, Y · h:i A') ?></span>
                </div>
                <div class="hms-topbar-profile">
                    <div class="avatar-circle">
                        <?php 
                            $nameParts = explode(' ', $_SESSION['patient_name']);
                            $initials = '';
                            foreach($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div class="d-none d-md-block text-start">
                        <strong class="d-block text-dark small" style="line-height: 1.2;"><?php echo htmlspecialchars($_SESSION['patient_name']); ?></strong>
                        <span class="badge role-badge role-badge-patient" style="font-size: 9px !important; padding: 2px 4px !important;">Patient</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="hms-content p-4">
            <!-- Statistics Banner -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Admissions</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalStays ?> stays</h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-hospital fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Active Stay</span>
                                <h3 class="fw-bold mb-0 text-danger"><?= $activeAdmissions ?> active</h3>
                            </div>
                            <div class="bg-danger-subtle text-danger p-3 rounded-3">
                                <i class="bi bi-heart-pulse fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List of Admissions -->
            <h3 class="mb-4">Admissions & Hospital Stays</h3>

            <?php if ($admissions->num_rows == 0): ?>
                <div class="card border-0 shadow-sm p-5 text-center rounded-4 bg-white">
                    <div class="fs-1 text-muted mb-3">🏥</div>
                    <h5 class="fw-bold">No Hospital Admissions Found</h5>
                    <p class="text-muted mb-0">There are no recorded in-patient stays associated with your portal profile.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php while ($row = $admissions->fetch_assoc()): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1">Stay #IPD-<?= $row['ipd_id'] ?>: Admitted for <?= htmlspecialchars($row['admission_reason']) ?></h5>
                                        <p class="text-muted small mb-0">Attending Officer: Dr. <?= htmlspecialchars($row['doctor_name']) ?> | Room/Bed: <?= htmlspecialchars($row['bed_number']) ?> (<?= htmlspecialchars($row['bed_type']) ?>)</p>
                                    </div>
                                    <div>
                                        <?php if ($row['status'] === 'Admitted'): ?>
                                            <span class="badge bg-danger px-2.5 py-1.5 rounded-pill"><i class="bi bi-heart-pulse-fill me-1"></i>Currently Admitted</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Discharged</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row bg-light rounded p-3 mb-3 text-secondary small">
                                    <div class="col-md-3"><strong>BP (mmHg):</strong> <?= htmlspecialchars($row['initial_bp']) ?></div>
                                    <div class="col-md-3"><strong>Temp:</strong> <?= htmlspecialchars($row['initial_temp']) ?>F</div>
                                    <div class="col-md-3"><strong>Pulse:</strong> <?= htmlspecialchars($row['initial_pulse']) ?> bpm</div>
                                    <div class="col-md-3"><strong>Weight:</strong> <?= htmlspecialchars($row['initial_weight']) ?> kg</div>
                                </div>

                                <!-- Progress Logs Timeline -->
                                <div class="my-4">
                                    <h6 class="fw-bold mb-3"><i class="bi bi-journal-text"></i> Stay Progress & Vitals Log Timeline:</h6>
                                    <?php 
                                    $logs = $conn->query("SELECT * FROM ipd_progress_logs WHERE ipd_id = {$row['ipd_id']} ORDER BY log_date ASC");
                                    if ($logs && $logs->num_rows > 0): 
                                    ?>
                                        <div class="border rounded p-3 bg-light">
                                            <?php while ($log = $logs->fetch_assoc()): ?>
                                                <div class="timeline-item">
                                                    <div class="d-flex justify-content-between mb-1 small">
                                                        <span class="fw-bold text-dark"><?= date('d M Y, h:i A', strtotime($log['log_date'])) ?></span>
                                                        <span class="text-muted">Logged by: <?= htmlspecialchars($log['logged_by']) ?></span>
                                                    </div>
                                                    <p class="mb-1 text-dark"><?= htmlspecialchars($log['clinical_notes']) ?></p>
                                                    <small class="text-secondary">Vitals: BP: <?= htmlspecialchars($log['blood_pressure']) ?> | Temp: <?= htmlspecialchars($log['temp_f']) ?>F | Pulse: <?= htmlspecialchars($log['pulse_rate']) ?> bpm</small>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted small">No progression checks logged yet for this stay.</p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($row['status'] === 'Discharged'): ?>
                                    <div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center">
                                        <div class="small">
                                            <strong>Discharged:</strong> <?= date('d M Y, h:i A', strtotime($row['discharge_date'])) ?> | 
                                            <strong>Condition:</strong> <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars($row['discharge_status']) ?></span>
                                        </div>
                                        <a href="download_discharge_summary.php?id=<?= $row['ipd_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bi bi-file-earmark-pdf me-1"></i>Download Discharge Summary</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
</script>
</body>
</html>
