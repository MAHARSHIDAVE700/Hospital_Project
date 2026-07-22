<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['patient_id'];

// Get patient_id from users session ID
$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patient = $getPatient->fetch_assoc();
$patientID = $patient ? $patient['patient_id'] : 0;

$labRequests = $conn->query("
    SELECT lr.*, lt.test_name, lt.test_code, lt.sample_type, lt.price, d.full_name AS doctor_name
    FROM lab_requests lr
    JOIN lab_tests lt ON lr.test_id = lt.test_id
    LEFT JOIN doctors d ON lr.doctor_id = d.doctor_id
    WHERE lr.patient_id = '$patientID'
    ORDER BY lr.request_date DESC
");

// Stats for patient
$completedCount = $conn->query("SELECT COUNT(*) AS total FROM lab_requests WHERE patient_id='$patientID' AND status='Completed'")->fetch_assoc()['total'];
$pendingCount = $conn->query("SELECT COUNT(*) AS total FROM lab_requests WHERE patient_id='$patientID' AND status IN ('Pending', 'Sample Collected')")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Lab Reports | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .report-card {
            transition: var(--transition);
            border-radius: 16px;
            border: 1px solid var(--border-color);
            background: #ffffff;
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
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
            <a href="my_lab_reports.php" class="hms-sidebar-item active">
                <i class="bi bi-virus2"></i> Lab Reports
            </a>
            <a href="my_pharmacy_bills.php" class="hms-sidebar-item">
                <i class="bi bi-receipt"></i> Pharmacy Bills
            </a>
            <a href="my_bills.php" class="hms-sidebar-item">
                <i class="bi bi-wallet2"></i> Invoices & Bills
            </a>
            <a href="my_admissions.php" class="hms-sidebar-item">
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
                    <span class="hms-breadcrumb-item-active">Diagnostic Lab Reports</span>
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
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Lab Tests</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $labRequests->num_rows ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-virus2 fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Completed Reports</span>
                                <h3 class="fw-bold mb-0 text-success"><?= $completedCount ?></h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-check-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Pending Results</span>
                                <h3 class="fw-bold mb-0 text-warning"><?= $pendingCount ?></h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-clock-history fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- List / Grid of Reports -->
            <h3 class="mb-4">Diagnostic Reports History</h3>
            
            <?php if ($labRequests->num_rows == 0): ?>
                <div class="card border-0 shadow-sm p-5 text-center rounded-4 bg-white">
                    <div class="fs-1 text-muted mb-3">🔬</div>
                    <h5 class="fw-bold">No Laboratory Reports Found</h5>
                    <p class="text-muted mb-0">When your doctor orders diagnostic laboratory tests or logs your results, they will show up here.</p>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php while ($row = $labRequests->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="card report-card p-4 border-0 shadow-sm">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['test_name']) ?></h5>
                                        <span class="text-muted small">Code: <?= htmlspecialchars($row['test_code']) ?> · Sample: <?= htmlspecialchars($row['sample_type']) ?></span>
                                    </div>
                                    <div>
                                        <?php if ($row['status'] === 'Pending'): ?>
                                            <span class="badge bg-warning-subtle text-warning px-2.5 py-1.5 rounded-pill"><i class="bi bi-hourglass-split me-1"></i>Sample Pending</span>
                                        <?php elseif ($row['status'] === 'Sample Collected'): ?>
                                            <span class="badge bg-info-subtle text-info px-2.5 py-1.5 rounded-pill"><i class="bi bi-droplet-fill me-1"></i>Sample Collected</span>
                                        <?php elseif ($row['status'] === 'Completed'): ?>
                                            <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5 rounded-pill">Cancelled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="row bg-light rounded p-3 mb-3 text-secondary small">
                                    <div class="col-6">
                                        <strong>Ordered Date:</strong><br>
                                        <?= date('d M Y, h:i A', strtotime($row['request_date'])) ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Attending Doctor:</strong><br>
                                        <?= $row['doctor_name'] ? 'Dr. ' . htmlspecialchars($row['doctor_name']) : 'Clinical Direct' ?>
                                    </div>
                                </div>

                                <?php if ($row['status'] === 'Completed'): ?>
                                    <div class="mb-3">
                                        <strong class="small text-dark d-block mb-1">Result Diagnostic Summary:</strong>
                                        <div class="p-2 border rounded bg-white text-dark small" style="min-height: 45px;">
                                            <?= htmlspecialchars($row['result_summary']) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">Completed: <?= date('d M Y, h:i A', strtotime($row['result_date'])) ?></span>
                                        <a href="download_lab_report.php?id=<?= $row['request_id'] ?>" target="_blank" class="btn btn-sm btn-primary px-3 rounded-pill text-white fw-semibold"><i class="bi bi-download me-1"></i>Download PDF Report</a>
                                    </div>
                                <?php elseif ($row['status'] === 'Cancelled'): ?>
                                    <div class="text-muted small">This diagnostic request was cancelled by the medical desk.</div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-2 text-muted small">
                                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                                        <span>Sample processing inside lab. Check back soon.</span>
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
