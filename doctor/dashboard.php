<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";
include_once "../includes/sms_helper.php";

// Logged-in user's ID
$userID = $_SESSION['doctor_id'];

// Get doctor's email from users table
$userQuery = $conn->query("SELECT email FROM users WHERE id='$userID'");
$user = ($userQuery)->fetch_assoc();
$email = $user['email'];

// Get doctor_id and details from doctors table
$doctorQuery = $conn->query("SELECT * FROM doctors WHERE email='$email'");
$doctor = ($doctorQuery)->fetch_assoc();
$doctorID = $doctor['doctor_id'];

// Queue alerting function
function checkAndAlertQueue($conn, $doctorID, $doctorName) {
    $pendingQuery = $conn->query("
        SELECT p.phone, u.full_name 
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        JOIN users u ON p.user_id = u.id
        WHERE a.doctor_id='$doctorID' 
        AND a.appointment_date=CURRENT_DATE 
        AND a.queue_status='Waiting'
        ORDER BY a.queue_position ASC, a.appointment_id ASC
    ");
    
    $i = 0;
    while ($pat = $pendingQuery->fetch_assoc()) {
        if ($i === 3) {
            SMSHelper::sendQueuePositionAlert($pat['phone'], $pat['full_name'], $doctorName, 3);
            break;
        }
        $i++;
    }
}

// 1. Handle Status Toggle
if (isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $allowedStatuses = ['Available', 'Busy', 'Break', 'Emergency', 'Offline', 'Leave'];
    if (in_array($newStatus, $allowedStatuses)) {
        $conn->query("UPDATE doctors SET status='$newStatus' WHERE doctor_id='$doctorID'");
        
        // If doctor becomes unavailable or delayed, notify all pending patients today
        if (in_array($newStatus, ['Break', 'Emergency', 'Offline', 'Leave'])) {
            $pendingPatients = $conn->query("
                SELECT p.phone, u.full_name 
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN users u ON p.user_id = u.id
                WHERE a.doctor_id='$doctorID' 
                AND a.appointment_date=CURRENT_DATE 
                AND a.queue_status='Waiting'
            ");
            while ($pat = $pendingPatients->fetch_assoc()) {
                SMSHelper::sendDoctorStatusAlert($pat['phone'], $pat['full_name'], $_SESSION['doctor_name'], $newStatus);
            }
        }
        
        header("Location: dashboard.php");
        exit();
    }
}

// 2. Handle Action (Call Next, Skip)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'call_next') {
        // Complete currently called if any
        $conn->query("
            UPDATE appointments 
            SET queue_status='Completed', status='Completed' 
            WHERE doctor_id='$doctorID' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Called'
        ");
        
        // Find next waiting today
        $nextQuery = $conn->query("
            SELECT appointment_id 
            FROM appointments 
            WHERE doctor_id='$doctorID' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Waiting' 
            ORDER BY queue_position ASC LIMIT 1
        ");
        if ($nextQuery && $nextRow = $nextQuery->fetch_assoc()) {
            $nextApptId = $nextRow['appointment_id'];
            $conn->query("UPDATE appointments SET queue_status='Called' WHERE appointment_id='$nextApptId'");
            
            // Trigger alert check for shifting queue
            checkAndAlertQueue($conn, $doctorID, $_SESSION['doctor_name']);
        }
        header("Location: dashboard.php");
        exit();
    }
    
    if ($action === 'skip' && isset($_GET['appt_id'])) {
        $appt_id = intval($_GET['appt_id']);
        $conn->query("
            UPDATE appointments 
            SET queue_status='Skipped', status='Cancelled' 
            WHERE appointment_id='$appt_id' 
            AND doctor_id='$doctorID'
        ");
        
        // Trigger alert check
        checkAndAlertQueue($conn, $doctorID, $_SESSION['doctor_name']);
        
        header("Location: dashboard.php");
        exit();
    }
}

// Get current doctor status
$statusQuery = $conn->query("SELECT status FROM doctors WHERE doctor_id='$doctorID'");
$doctorStatus = $statusQuery ? $statusQuery->fetch_assoc()['status'] : 'Available';

// Fetch current token details
$currentApptQuery = $conn->query("
    SELECT a.appointment_id, u.full_name AS patient_name, a.token_number 
    FROM appointments a
    JOIN patients p ON a.patient_id=p.patient_id
    JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id='$doctorID' 
    AND a.appointment_date=CURRENT_DATE 
    AND a.queue_status='Called'
    LIMIT 1
");
$currentAppt = $currentApptQuery ? $currentApptQuery->fetch_assoc() : null;
$currentTokenId = $currentAppt ? $currentAppt['token_number'] : "-";
$currentPatientName = $currentAppt ? $currentAppt['patient_name'] : "No patient currently called";
$currentApptId = $currentAppt ? $currentAppt['appointment_id'] : null;

// Fetch next token details
$nextApptQuery = $conn->query("
    SELECT a.appointment_id, u.full_name AS patient_name, a.token_number 
    FROM appointments a
    JOIN patients p ON a.patient_id=p.patient_id
    JOIN users u ON p.user_id=u.id
    WHERE a.doctor_id='$doctorID' 
    AND a.appointment_date=CURRENT_DATE 
    AND a.queue_status='Waiting'
    ORDER BY a.queue_position ASC LIMIT 1
");
$nextAppt = $nextApptQuery ? $nextApptQuery->fetch_assoc() : null;
$nextPatientName = $nextAppt ? $nextAppt['patient_name'] : "None (End of queue)";
$nextTokenId = $nextAppt ? $nextAppt['token_number'] : "-";

// Fetch counts for widgets
$todayCompleted = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id='$doctorID' AND appointment_date=CURRENT_DATE AND queue_status='Completed'")->fetch_assoc()['total'];
$todayPending = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id='$doctorID' AND appointment_date=CURRENT_DATE AND queue_status='Waiting'")->fetch_assoc()['total'];
$todayCancelled = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE doctor_id='$doctorID' AND appointment_date=CURRENT_DATE AND queue_status='Skipped'")->fetch_assoc()['total'];
$todayTotal = $todayCompleted + $todayPending + $todayCancelled;
$todayProgress = $todayTotal > 0 ? round(($todayCompleted / $todayTotal) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-4">

    <!-- Premium Enterprise Header Section -->
    <header class="premium-hero-card">
        <!-- Header Top Row -->
        <div class="hero-top-row">
            <div class="hero-brand">
                <span class="brand-icon">🏥</span>
                <div class="brand-text">
                    <span class="brand-name">Narayan Hospital</span>
                    <span class="brand-sub">Doctor Portal</span>
                </div>
            </div>
            
            <div class="hero-search-bar">
                <i class="bi bi-search search-icon"></i>
                <input type="text" placeholder="Search patients, records, schedule..." class="search-input">
            </div>
            
            <div class="hero-right-actions">
                <!-- Status Toggle -->
                <div class="live-clock-widget d-none d-lg-flex" style="padding: 4px 8px;">
                    <span class="text-secondary small me-1 fw-bold">My Status:</span>
                    <form method="POST" class="d-inline-flex align-items-center gap-2 m-0">
                        <select name="status" class="form-select form-select-sm border-0 bg-transparent p-0 fw-bold" style="font-size: 13px !important; outline: none; box-shadow: none; height: auto !important; width: auto !important; color: var(--text-primary) !important;" onchange="this.form.submit()">
                            <option value="Available" <?= $doctorStatus === 'Available' ? 'selected' : '' ?>>🟢 Available</option>
                            <option value="Busy" <?= $doctorStatus === 'Busy' ? 'selected' : '' ?>>🟡 Busy</option>
                            <option value="Break" <?= $doctorStatus === 'Break' ? 'selected' : '' ?>>☕ On Break</option>
                            <option value="Emergency" <?= $doctorStatus === 'Emergency' ? 'selected' : '' ?>>🚨 Emergency</option>
                            <option value="Offline" <?= $doctorStatus === 'Offline' ? 'selected' : '' ?>>⚫ Offline</option>
                            <option value="Leave" <?= $doctorStatus === 'Leave' ? 'selected' : '' ?>>❌ On Leave</option>
                        </select>
                        <input type="hidden" name="update_status" value="1">
                    </form>
                </div>
                
                <div class="notification-widget">
                    <button class="notification-btn position-relative">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle" style="width: 8px; height: 8px; border-radius: 50%;"></span>
                    </button>
                </div>
                
                <div class="user-profile-widget">
                    <div class="avatar-circle">
                        <?php 
                            $nameParts = explode(' ', $_SESSION['doctor_name']);
                            $initials = '';
                            foreach($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div class="user-info-text d-none d-md-block">
                        <strong class="user-name-display">Dr. <?php echo htmlspecialchars($_SESSION['doctor_name']); ?></strong>
                        <span class="badge role-badge role-badge-doctor"><?= htmlspecialchars($doctor['specialization'] ?? 'Clinician') ?></span>
                    </div>
                </div>
                
                <a href="../logout.php" class="btn-logout-header" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <!-- Divider -->
        <div class="hero-divider"></div>
        
        <!-- Header Content Row -->
        <div class="hero-content-row">
            <div class="hero-left-content">
                <div class="hero-breadcrumbs">
                    <span>Narayan Clinician</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="active">Consultation Hub</span>
                </div>
                <h2 class="hero-welcome-title">👋 Good Morning, Dr. <?= htmlspecialchars($_SESSION['doctor_name']); ?></h2>
                <div class="hero-description-block">
                    <span class="hero-subtitle">Today's Schedule</span>
                    <span class="hero-desc-sep">·</span>
                    <span class="hero-description">You have <?= $todayTotal ?> appointments today.</span>
                </div>
            </div>
            
            <div class="hero-right-content">
                <div class="hero-quick-actions">
                    <?php if ($todayPending > 0) { ?>
                        <a href="dashboard.php?action=call_next" class="btn btn-primary" style="background-color: var(--primary-color) !important;">
                            <i class="bi bi-bell-fill"></i> Call Next Patient
                        </a>
                    <?php } ?>
                    <a href="write_prescription.php" class="btn btn-outline-primary">
                        <i class="bi bi-prescription"></i> Write Prescription
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Live Queue Controls & Status Widgets -->
    <div class="row mb-5">
        <!-- Current Patient Card -->
        <div class="col-md-4 mb-3">
            <div class="card-modern h-100 border-primary-subtle">
                <div class="card-body-modern text-center">
                    <div class="widget-icon mx-auto bg-primary-subtle text-primary">
                        <i class="bi bi-person-fill-check"></i>
                    </div>
                    <h6 class="text-secondary uppercase fs-7 fw-semibold">Current Patient Token</h6>
                    <h1 class="display-4 fw-bold text-primary my-1"><?= $currentTokenId ?></h1>
                    <h5 class="text-dark truncate"><?= htmlspecialchars($currentPatientName) ?></h5>
                    
                    <div class="d-flex justify-content-center gap-2 mt-3">
                        <?php if ($todayPending > 0) { ?>
                            <a href="dashboard.php?action=call_next" class="btn btn-sm btn-primary btn-modern px-3 py-2">
                                <i class="bi bi-bell-fill"></i> Call Next
                            </a>
                        <?php } ?>
                        
                        <?php if ($currentApptId) { ?>
                            <a href="write_prescription.php?id=<?= $currentApptId ?>" class="btn btn-sm btn-success btn-modern px-3 py-2">
                                <i class="bi bi-file-earmark-medical"></i> Complete
                            </a>
                            <a href="dashboard.php?action=skip&appt_id=<?= $currentApptId ?>" class="btn btn-sm btn-outline-danger btn-modern px-3 py-2" onclick="return confirm('Skip this patient?')">
                                <i class="bi bi-skip-forward"></i> Skip
                            </a>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Token Card -->
        <div class="col-md-4 mb-3">
            <div class="card-modern h-100">
                <div class="card-body-modern text-center">
                    <div class="widget-icon mx-auto bg-info-subtle text-info">
                        <i class="bi bi-person-fill-up"></i>
                    </div>
                    <h6 class="text-secondary uppercase fs-7 fw-semibold">Next Token In Line</h6>
                    <h1 class="display-4 fw-bold text-info my-1"><?= $nextTokenId ?></h1>
                    <h5 class="text-dark truncate"><?= htmlspecialchars($nextPatientName) ?></h5>
                </div>
            </div>
        </div>

        <!-- Today's Queue Progress Bar -->
        <div class="col-md-4 mb-3">
            <div class="card-modern h-100">
                <div class="card-body-modern">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="widget-icon mb-0 bg-success-subtle text-success">
                            <i class="bi bi-bar-chart-fill"></i>
                        </div>
                        <span class="badge bg-success-subtle text-success badge-modern">OPD Tracker</span>
                    </div>
                    <h6 class="text-secondary fw-semibold mb-1">Today's Progress</h6>
                    <div class="progress my-3" style="height: 10px; border-radius: 8px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $todayProgress ?>%;" aria-valuenow="<?= $todayProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between text-secondary small">
                        <span>Completed: <strong><?= $todayCompleted ?></strong> / <?= $todayTotal ?></span>
                        <span><?= $todayProgress ?>% Done</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- OPD Statistics Cards -->
    <h3 class="mb-4">OPD Queue Statistics</h3>
    <div class="row mb-5">
        <div class="col-md-4 mb-3">
            <div class="widget-stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Remaining in Queue</p>
                        <h3><?= $todayPending ?> Patients</h3>
                    </div>
                    <div class="widget-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="widget-stat-card border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Completed Today</p>
                        <h3><?= $todayCompleted ?> Patients</h3>
                    </div>
                    <div class="widget-icon bg-success-subtle text-success">
                        <i class="bi bi-check2-all"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="widget-stat-card border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Skipped Today</p>
                        <h3><?= $todayCancelled ?> Patients</h3>
                    </div>
                    <div class="widget-icon bg-warning-subtle text-warning">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation / Standard Actions -->
    <h3 class="mb-4">Quick Navigation</h3>
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card-modern text-center p-4">
                <a href="my_appointments.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-primary text-white">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <h5 class="text-dark">All Appointments</h5>
                    <p class="text-secondary small mb-0">View all past & future appointment schedules</p>
                </a>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card-modern text-center p-4">
                <a href="todays_appointments.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-success text-white">
                        <i class="bi bi-calendar2-check"></i>
                    </div>
                    <h5 class="text-dark">Today's Appointments</h5>
                    <p class="text-secondary small mb-0">Manage and execute active OPD queue</p>
                </a>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card-modern text-center p-4">
                <a href="profile.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-info text-white">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <h5 class="text-dark">My Profile</h5>
                    <p class="text-secondary small mb-0">Edit your professional details and timing</p>
                </a>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card-modern text-center p-4">
                <a href="write_prescription.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-warning text-dark">
                        <i class="bi bi-prescription"></i>
                    </div>
                    <h5 class="text-dark">Write Prescription</h5>
                    <p class="text-secondary small mb-0">Issue a new medical prescription slip</p>
                </a>
            </div>
        </div>
    </div>

</div>

<footer class="text-center py-4 mt-5 text-secondary border-top">
    Narayan Hospital OPD Management System &copy; <?= date('Y') ?>
</footer>

</body>
</html>