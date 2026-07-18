<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

/* ------------------------------
   OPD Dynamic Statistics (Today)
   ------------------------------ */
$todayTotal = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE appointment_date=CURRENT_DATE AND token_number IS NOT NULL")->fetch_assoc()['total'];
$todayCompleted = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE appointment_date=CURRENT_DATE AND queue_status='Completed'")->fetch_assoc()['total'];
$todayPending = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE appointment_date=CURRENT_DATE AND queue_status='Waiting'")->fetch_assoc()['total'];
$todayCancelled = $conn->query("SELECT COUNT(*) AS total FROM appointments WHERE appointment_date=CURRENT_DATE AND queue_status='Skipped'")->fetch_assoc()['total'];

$waitingPatients = $todayPending;
$avgWaitTime = ($todayPending * 12) . " mins"; // Average 12 mins per patient

// Doctor Availability
$docsAvailable = $conn->query("SELECT COUNT(*) AS total FROM doctors WHERE status='Available' OR status='Busy'")->fetch_assoc()['total'];
$docsBreak = $conn->query("SELECT COUNT(*) AS total FROM doctors WHERE status='Break'")->fetch_assoc()['total'];
$docsLeave = $conn->query("SELECT COUNT(*) AS total FROM doctors WHERE status='Leave' OR status='Offline'")->fetch_assoc()['total'];

// General Statistics (All Time)
$totalPatients = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='patient'")->fetch_assoc()['total'];
$totalDoctors = $conn->query("SELECT COUNT(*) AS total FROM doctors")->fetch_assoc()['total'];
$totalDepartments = $conn->query("SELECT COUNT(*) AS total FROM departments")->fetch_assoc()['total'];
$totalAppointments = $conn->query("SELECT COUNT(*) AS total FROM appointments")->fetch_assoc()['total'];

// Live Queue Monitor (Today's Active Queue - Max 5)
$liveQueue = $conn->query("
    SELECT a.appointment_id, u.full_name AS patient_name, d.full_name AS doctor_name, a.appointment_time, a.status 
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_date=CURRENT_DATE
    ORDER BY a.appointment_time ASC LIMIT 5
");

// Doctor Availability Overview List
$doctorStatusList = $conn->query("
    SELECT d.doctor_id, d.full_name, dep.department_name, d.status 
    FROM doctors d
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    ORDER BY d.full_name ASC LIMIT 5
");

// Recent Notifications
$notifications = $conn->query("
    SELECT u.full_name AS patient_name, d.full_name AS doctor_name, a.appointment_date, a.appointment_time
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    ORDER BY a.appointment_id DESC LIMIT 3
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Smart Hospital</title>
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
                    <span class="brand-sub">OPD Dashboard</span>
                </div>
            </div>
            
            <div class="hero-search-bar">
                <i class="bi bi-search search-icon"></i>
                <input type="text" placeholder="Search patients, doctors, records..." class="search-input">
            </div>
            
            <div class="hero-right-actions">
                <div class="live-clock-widget d-none d-lg-flex">
                    <i class="bi bi-clock"></i>
                    <span><?= date('D, M d, Y · h:i A') ?></span>
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
                            $nameParts = explode(' ', $_SESSION['admin_name']);
                            $initials = '';
                            foreach($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div class="user-info-text d-none d-md-block">
                        <strong class="user-name-display"><?php echo htmlspecialchars($_SESSION['admin_name']); ?></strong>
                        <span class="badge role-badge role-badge-admin">Admin</span>
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
                    <span>Narayan Administration</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="active">OPD Control Centre</span>
                </div>
                <h2 class="hero-welcome-title">👋 Good Morning, System Administrator</h2>
                <div class="hero-description-block">
                    <span class="hero-subtitle">Today's Overview</span>
                    <span class="hero-desc-sep">·</span>
                    <span class="hero-description">Manage doctors, patients, appointments and hospital operations.</span>
                </div>
            </div>
            
            <div class="hero-right-content">
                <div class="hero-quick-actions">
                    <a href="manage_patients.php" class="btn btn-primary" style="background-color: var(--primary-color) !important;">
                        <i class="bi bi-person-plus"></i> Add Patient
                    </a>
                    <a href="manage_appointments.php" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-event"></i> Create Appointment
                    </a>
                    <a href="#" class="btn btn-light border border-light-subtle bg-white">
                        <i class="bi bi-file-earmark-bar-graph"></i> Generate Report
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Smart Hospital Admin Widgets -->
    <div class="row mb-5">
        <!-- Waiting Patient Counter -->
        <div class="col-md-3 mb-3">
            <div class="widget-stat-card border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Waiting Patients Today</p>
                        <h3 class="text-warning"><?= $waitingPatients ?> Patients</h3>
                    </div>
                    <div class="widget-icon bg-warning-subtle text-warning">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Average Waiting Time -->
        <div class="col-md-3 mb-3">
            <div class="widget-stat-card border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Avg waiting time</p>
                        <h3 class="text-primary"><?= $avgWaitTime ?></h3>
                    </div>
                    <div class="widget-icon bg-primary-subtle text-primary">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctor Status Overview -->
        <div class="col-md-3 mb-3">
            <div class="widget-stat-card border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Doctors Online</p>
                        <h3 class="text-success"><?= $docsAvailable ?> Active</h3>
                    </div>
                    <div class="widget-icon bg-success-subtle text-success">
                        <i class="bi bi-shield-check"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Queue Status -->
        <div class="col-md-3 mb-3">
            <div class="widget-stat-card border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary small mb-1">Cancelled Today</p>
                        <h3 class="text-danger"><?= $todayCancelled ?> Appts</h3>
                    </div>
                    <div class="widget-icon bg-danger-subtle text-danger">
                        <i class="bi bi-slash-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row mb-5">
        <!-- Live Queue Monitor Table -->
        <div class="col-lg-8 mb-4">
            <div class="card-modern h-100">
                <div class="card-header-modern d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-broadcast text-danger"></i> Live Queue Monitor</h5>
                    <span class="badge bg-danger-subtle text-danger badge-modern"><i class="bi bi-record-fill"></i> Realtime</span>
                </div>
                <div class="card-body-modern p-0">
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Token ID</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($liveQueue && $liveQueue->num_rows > 0) { ?>
                                    <?php while ($row = $liveQueue->fetch_assoc()) { 
                                        $badgeClass = "badge-pending-modern";
                                        if ($row['status'] === 'Completed') $badgeClass = "badge-success-modern";
                                        if ($row['status'] === 'Cancelled') $badgeClass = "badge-danger-modern";
                                    ?>
                                        <tr>
                                            <td><strong>#<?= $row['appointment_id'] ?></strong></td>
                                            <td><?= htmlspecialchars($row['patient_name']) ?></td>
                                            <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                                            <td><?= date('h:i A', strtotime($row['appointment_time'])) ?></td>
                                            <td><span class="badge-modern <?= $badgeClass ?>"><?= $row['status'] ?></span></td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-secondary py-5">
                                            <i class="bi bi-calendar2-x display-6 d-block mb-3 text-muted"></i>
                                            No active appointments scheduled for today.
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Doctor Availability Sidepanel -->
        <div class="col-lg-4 mb-4">
            <div class="card-modern h-100">
                <div class="card-header-modern">
                    <h5 class="mb-0"><i class="bi bi-heart-pulse-fill text-primary"></i> Doctor Status Overview</h5>
                </div>
                <div class="card-body-modern">
                    <ul class="list-group list-group-flush">
                        <?php if ($doctorStatusList && $doctorStatusList->num_rows > 0) { ?>
                            <?php while ($doc = $doctorStatusList->fetch_assoc()) { 
                                $status = $doc['status'];
                                $docID = $doc['doctor_id'];
                                $statusBadge = '<span class="badge bg-success-subtle text-success badge-modern">Available</span>';
                                if ($status === 'Busy') $statusBadge = '<span class="badge bg-warning-subtle text-warning badge-modern">Busy</span>';
                                if ($status === 'Break') $statusBadge = '<span class="badge bg-secondary-subtle text-secondary badge-modern">On Break</span>';
                                if ($status === 'Emergency') $statusBadge = '<span class="badge bg-danger-subtle text-danger badge-modern">Emergency</span>';
                                if ($status === 'Offline') $statusBadge = '<span class="badge bg-dark-subtle text-dark badge-modern">Offline</span>';
                                if ($status === 'Leave') $statusBadge = '<span class="badge bg-danger-subtle text-danger badge-modern">On Leave</span>';
                                
                                // Fetch serving token
                                $runQuery = $conn->query("SELECT token_number FROM appointments WHERE doctor_id='$docID' AND appointment_date=CURRENT_DATE AND queue_status='Called' LIMIT 1");
                                $runRow = $runQuery ? $runQuery->fetch_assoc() : null;
                                $currentRunningToken = $runRow ? $runRow['token_number'] : '-';
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-3 border-bottom border-light">
                                    <div>
                                        <strong class="d-block text-dark">Dr. <?= htmlspecialchars($doc['full_name']) ?></strong>
                                        <span class="text-secondary small"><?= htmlspecialchars($doc['department_name']) ?> | Serving: <strong class="text-primary"><?= $currentRunningToken ?></strong></span>
                                    </div>
                                    <?= $statusBadge ?>
                                </li>
                            <?php } ?>
                        <?php } else { ?>
                            <li class="list-group-item text-center text-secondary py-5 px-0">No doctors registered yet.</li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Logs & System Notifications -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card-modern">
                <div class="card-header-modern">
                    <h5 class="mb-0"><i class="bi bi-bell-fill text-warning"></i> Recent System Activity</h5>
                </div>
                <div class="card-body-modern">
                    <div class="row">
                        <?php if ($notifications && $notifications->num_rows > 0) { ?>
                            <?php while ($notif = $notifications->fetch_assoc()) { ?>
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <div class="p-3 border rounded" style="background-color: var(--light-color); border-color: var(--border-color) !important;">
                                        <p class="mb-1 text-secondary small"><i class="bi bi-clock-history"></i> Just Now</p>
                                        <strong class="d-block text-dark"><?= htmlspecialchars($notif['patient_name']) ?></strong>
                                        <span class="text-secondary small">Booked appointment with Dr. <?= htmlspecialchars($notif['doctor_name']) ?> at <?= date('h:i A', strtotime($notif['appointment_time'])) ?></span>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="col-12 text-center text-secondary py-3">No recent logs recorded.</div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Total Analytics Summary (Original counts styled premium) -->
    <h3 class="mb-4">Narayan Clinic Overview</h3>
    <div class="row mb-5">
        <div class="col-md-3 mb-4">
            <div class="card-modern bg-primary text-white p-4 text-center">
                <div class="widget-icon mx-auto bg-white text-primary mb-3">
                    <i class="bi bi-people"></i>
                </div>
                <h5>Total Patients</h5>
                <h2 class="fw-bold mb-0"><?php echo $totalPatients; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card-modern bg-success text-white p-4 text-center">
                <div class="widget-icon mx-auto bg-white text-success mb-3">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h5>Total Doctors</h5>
                <h2 class="fw-bold mb-0"><?php echo $totalDoctors; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card-modern bg-warning text-dark p-4 text-center">
                <div class="widget-icon mx-auto bg-white text-warning mb-3">
                    <i class="bi bi-tags"></i>
                </div>
                <h5>Total Departments</h5>
                <h2 class="fw-bold mb-0"><?php echo $totalDepartments; ?></h2>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card-modern bg-danger text-white p-4 text-center">
                <div class="widget-icon mx-auto bg-white text-danger mb-3">
                    <i class="bi bi-calendar2-check"></i>
                </div>
                <h5>Total Appointments</h5>
                <h2 class="fw-bold mb-0"><?php echo $totalAppointments; ?></h2>
            </div>
        </div>
    </div>

    <!-- Quick Actions Panel -->
    <h3 class="mb-4">System Management</h3>
    <div class="row">
        <div class="col-md-4 mb-3">
            <a href="manage_doctors.php" class="btn btn-modern btn-primary-modern w-100 py-3 d-flex justify-content-center">
                👨‍⚕️ Manage Doctors
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="manage_patients.php" class="btn btn-modern btn-primary-modern w-100 py-3 d-flex justify-content-center" style="background-color: var(--secondary-color);">
                👥 Manage Patients
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="manage_departments.php" class="btn btn-modern btn-primary-modern w-100 py-3 d-flex justify-content-center" style="background-color: var(--success-color);">
                🏥 Manage Departments
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="manage_appointments.php" class="btn btn-modern btn-primary-modern w-100 py-3 d-flex justify-content-center" style="background-color: var(--warning-color); color: var(--dark-color);">
                📅 Manage Appointments
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="#" class="btn btn-modern btn-primary-modern w-100 py-3 d-flex justify-content-center" style="background-color: var(--dark-color);">
                📝 Medical Records
            </a>
        </div>
        <div class="col-md-4 mb-3">
            <a href="#" class="btn btn-modern btn-primary-modern w-100 py-3 d-flex justify-content-center" style="background-color: var(--text-secondary);">
                📊 Reports & Logs
            </a>
        </div>
    </div>

</div>

<footer class="text-center py-4 mt-5 text-secondary border-top">
    Narayan Hospital OPD Management System &copy; <?= date('Y') ?>
</footer>

</body>
</html>