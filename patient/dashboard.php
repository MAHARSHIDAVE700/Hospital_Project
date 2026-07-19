<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// Logged-in user's ID
$userID = $_SESSION['patient_id'];

// Get patient's record details
$patientQuery = $conn->query("SELECT * FROM patients WHERE user_id='$userID'");
$patient = ($patientQuery)->fetch_assoc();
$patientID = $patient ? $patient['patient_id'] : null;

// Default values for queue widgets
$myToken = "-";
$myPosition = "-";
$liveToken = "-";
$patientsAhead = 0;
$estimatedWait = "-";
$queueProgress = 0;
$doctorAvailability = "No active doctor session";
$activeDoctorName = "";
$hasAppointmentToday = false;
$doctorStatus = 'Offline';

if ($patientID) {
    // Get the patient's earliest pending/confirmed appointment today
    $apptQuery = $conn->query("
        SELECT a.appointment_id, a.doctor_id, d.full_name AS doctor_name, a.appointment_time, d.status AS doctor_status, a.token_number, a.queue_position
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.patient_id='$patientID' 
        AND a.status IN ('Pending', 'Confirmed') 
        AND a.appointment_date=CURRENT_DATE
        ORDER BY a.appointment_time ASC LIMIT 1
    ");
    
    if ($apptQuery && $appt = $apptQuery->fetch_assoc()) {
        $hasAppointmentToday = true;
        $myApptID = $appt['appointment_id'];
        $activeDoctorName = $appt['doctor_name'];
        $doctorStatus = $appt['doctor_status'];
        $doctorAvailability = "Dr. " . $activeDoctorName . " is currently " . $doctorStatus;
        
        $myToken = $appt['token_number'] ?: 'Pending Confirmation';
        $myPosition = $appt['queue_position'] ?: '-';
        
        // Find current live serving token number (queue_status = 'Called' today)
        $liveQuery = $conn->query("
            SELECT token_number 
            FROM appointments 
            WHERE doctor_id='{$appt['doctor_id']}' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Called'
            LIMIT 1
        ");
        $liveAppt = $liveQuery ? $liveQuery->fetch_assoc() : null;
        
        if ($liveAppt) {
            $liveToken = $liveAppt['token_number'];
        } else {
            // Find first waiting
            $waitingQuery = $conn->query("
                SELECT token_number 
                FROM appointments 
                WHERE doctor_id='{$appt['doctor_id']}' 
                AND appointment_date=CURRENT_DATE 
                AND queue_status='Waiting'
                ORDER BY queue_position ASC LIMIT 1
            ");
            $waitAppt = $waitingQuery ? $waitingQuery->fetch_assoc() : null;
            if ($waitAppt) {
                $liveToken = $waitAppt['token_number'];
            } else {
                // Last completed today
                $lastCompletedQuery = $conn->query("
                    SELECT token_number 
                    FROM appointments 
                    WHERE doctor_id='{$appt['doctor_id']}' 
                    AND appointment_date=CURRENT_DATE 
                    AND queue_status='Completed'
                    ORDER BY queue_position DESC LIMIT 1
                ");
                $lastComp = $lastCompletedQuery ? $lastCompletedQuery->fetch_assoc() : null;
                $liveToken = $lastComp ? $lastComp['token_number'] : '-';
            }
        }
        
        // Count how many pending appointments are ahead in the queue
        if ($myPosition !== '-') {
            $aheadQuery = $conn->query("
                SELECT COUNT(*) AS count 
                FROM appointments 
                WHERE doctor_id='{$appt['doctor_id']}' 
                AND appointment_date=CURRENT_DATE 
                AND queue_status='Waiting' 
                AND queue_position < '$myPosition'
            ");
            $patientsAhead = $aheadQuery ? $aheadQuery->fetch_assoc()['count'] : 0;
        } else {
            $patientsAhead = 0;
        }
        
        // Calculate Estimated Waiting Time based on Doctor Status
        if (in_array($doctorStatus, ['Break', 'Leave', 'Emergency', 'Offline'])) {
            if ($doctorStatus === 'Break') {
                $estimatedWait = 'Delayed (Doctor on Break)';
            } elseif ($doctorStatus === 'Leave') {
                $estimatedWait = 'Paused (Doctor on Leave)';
            } elseif ($doctorStatus === 'Emergency') {
                $estimatedWait = 'Delayed (Emergency Case)';
            } else {
                $estimatedWait = 'Paused (Doctor Offline)';
            }
        } else {
            $estimatedWait = ($patientsAhead * 12) . " mins";
        }
        
        // Calculate queue progress (Completed / Total Today)
        $completedCountQuery = $conn->query("
            SELECT COUNT(*) AS count 
            FROM appointments 
            WHERE doctor_id='{$appt['doctor_id']}' 
            AND appointment_date=CURRENT_DATE 
            AND queue_status='Completed'
        ");
        $completedCount = $completedCountQuery ? $completedCountQuery->fetch_assoc()['count'] : 0;
        
        $totalTodayQuery = $conn->query("
            SELECT COUNT(*) AS count 
            FROM appointments 
            WHERE doctor_id='{$appt['doctor_id']}' 
            AND appointment_date=CURRENT_DATE
            AND token_number IS NOT NULL
        ");
        $totalToday = $totalTodayQuery->fetch_assoc()['count'];
        if ($totalToday > 0) {
            $queueProgress = round(($completedCount / $totalToday) * 100);
        }
    }
}

// Count active doctors online
$activeDoctorsQuery = $conn->query("SELECT COUNT(*) AS count FROM doctors WHERE status='Available' OR status='Busy'");
$activeDoctorsCount = $activeDoctorsQuery ? $activeDoctorsQuery->fetch_assoc()['count'] : 0;

// Fetch last prescription as notification
$notificationText = "Welcome to your Patient Portal! You can book appointments and view prescriptions here.";
if ($patientID) {
    $prescriptionQuery = $conn->query("
        SELECT p.created_at, d.full_name AS doctor_name 
        FROM prescriptions p
        JOIN doctors d ON p.doctor_id = d.doctor_id
        WHERE p.patient_id='$patientID'
        ORDER BY p.created_at DESC LIMIT 1
    ");
    if ($prescriptionQuery && $presc = $prescriptionQuery->fetch_assoc()) {
        $notificationText = "New prescription has been added by Dr. " . $presc['doctor_name'] . " on " . date('M d, Y', strtotime($presc['created_at'])) . ".";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard | Smart Hospital</title>
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
                    <span class="brand-sub">Patient Portal</span>
                </div>
            </div>
            
            <div class="hero-search-bar">
                <i class="bi bi-search search-icon"></i>
                <input type="text" placeholder="Search my records, prescriptions..." class="search-input">
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
                            $nameParts = explode(' ', $_SESSION['patient_name']);
                            $initials = '';
                            foreach($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            echo htmlspecialchars(substr($initials, 0, 2));
                        ?>
                    </div>
                    <div class="user-info-text d-none d-md-block">
                        <strong class="user-name-display"><?php echo htmlspecialchars($_SESSION['patient_name']); ?></strong>
                        <span class="badge role-badge role-badge-patient">Patient</span>
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
                    <span>Patient Portal</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="active">My Dashboard</span>
                </div>
                <h2 class="hero-welcome-title">👋 Welcome Back, <?= htmlspecialchars($_SESSION['patient_name']); ?></h2>
                <div class="hero-description-block">
                    <span class="hero-subtitle">Your next appointment:</span>
                    <span class="hero-desc-sep">·</span>
                    <span class="hero-description">
                        <?php if ($hasAppointmentToday) { ?>
                            Dr. <?= htmlspecialchars($activeDoctorName) ?> · Token: <?= htmlspecialchars($myToken) ?> · Today
                        <?php } else { ?>
                            No appointments scheduled today
                        <?php } ?>
                    </span>
                </div>
            </div>
            
            <div class="hero-right-content">
                <div class="hero-quick-actions">
                    <a href="../appointment.php" class="btn btn-primary" style="background-color: var(--primary-color) !important;">
                        <i class="bi bi-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="my_prescriptions.php" class="btn btn-outline-primary">
                        <i class="bi bi-prescription"></i> View Prescription
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Smart Healthcare Widgets Section -->
    <div class="row mb-5">
        <!-- Live Token Widget -->
        <div class="col-md-4 mb-3">
            <div class="card-modern h-100">
                <div class="card-body-modern text-center">
                    <div class="widget-icon mx-auto">
                        <i class="bi bi-ticket-perforated-fill"></i>
                    </div>
                    <h6 class="text-secondary uppercase fs-7 fw-semibold tracking-wider">Live OPD Token Serving</h6>
                    <h1 id="live-token-val" class="display-3 fw-bold text-primary my-2"><?= $liveToken ?></h1>
                    
                    <p id="doctor-info-val" class="small mb-0 <?= $hasAppointmentToday ? 'text-success' : 'text-secondary' ?>">
                        <?php if ($hasAppointmentToday) { ?>
                            <i class="bi bi-check-circle-fill"></i> Your Doctor: Dr. <?= htmlspecialchars($activeDoctorName) ?>
                        <?php } else { ?>
                            No active appointment today
                        <?php } ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- My Token Widget -->
        <div class="col-md-4 mb-3">
            <div class="card-modern h-100 border-primary-subtle">
                <div class="card-body-modern text-center">
                    <div class="widget-icon mx-auto bg-primary-subtle text-primary">
                        <i class="bi bi-person-badge-fill"></i>
                    </div>
                    <h6 class="text-secondary uppercase fs-7 fw-semibold tracking-wider">My Daily OPD Token</h6>
                    <h1 id="my-token-val" class="display-4 fw-bold text-success my-2"><?= $myToken ?></h1>
                    <div class="d-flex justify-content-around text-secondary small mt-2">
                        <span>Queue Position: <strong id="queue-pos-val" class="text-dark"><?= $myPosition ?></strong></span>
                        <span>People Ahead: <strong id="people-ahead-val" class="text-dark"><?= $patientsAhead ?></strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Queue Status Widget -->
        <div class="col-md-4 mb-3">
            <div class="card-modern h-100">
                <div class="card-body-modern">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="widget-icon mb-0 bg-warning-subtle text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <span id="doctor-status-text" class="badge bg-success-subtle text-success badge-modern">
                            <?php if ($hasAppointmentToday) { ?>
                                Dr. Status: <?= $doctorStatus ?>
                            <?php } else { ?>
                                Online
                            <?php } ?>
                        </span>
                    </div>
                    <h6 class="text-secondary fw-semibold mb-1">Estimated Wait Time</h6>
                    <h3 id="wait-time-val" class="text-dark fw-bold my-2"><?= $estimatedWait ?></h3>
                    
                    <div class="progress my-2" style="height: 6px; border-radius: 8px;">
                        <div id="progress-bar-val" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $queueProgress ?>%;" aria-valuenow="<?= $queueProgress ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <span id="progress-percent-val" class="text-secondary small"><?= $queueProgress ?>% Completed</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Navigation / Standard Actions -->
    <h3 class="mb-4">Quick Actions</h3>
    <div class="row">
        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="../appointment.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-primary text-white">
                        <i class="bi bi-calendar-plus"></i>
                    </div>
                    <h5 class="text-dark">Book Appt</h5>
                    <p class="text-secondary small mb-0">Schedule a new visit</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="my_appointments.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-success text-white">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h5 class="text-dark">Appointments</h5>
                    <p class="text-secondary small mb-0">Check your status</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="my_prescriptions.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-warning text-dark">
                        <i class="bi bi-prescription"></i>
                    </div>
                    <h5 class="text-dark">Prescriptions</h5>
                    <p class="text-secondary small mb-0">Medications list</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="live_queue.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-danger text-white">
                        <i class="bi bi-broadcast"></i>
                    </div>
                    <h5 class="text-dark">Live Queue</h5>
                    <p class="text-secondary small mb-0">View clinic boards</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="profile.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-info text-white">
                        <i class="bi bi-person-fill-gear"></i>
                    </div>
                    <h5 class="text-dark">My Profile</h5>
                    <p class="text-secondary small mb-0">Edit personal details</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="symptom_checker.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-purple text-white" style="background-color: #6f42c1;">
                        <i class="bi bi-robot"></i>
                    </div>
                    <h5 class="text-dark">AI Symptoms</h5>
                    <p class="text-secondary small mb-0">Smart doctor suggest</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="feedback.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-warning text-dark">
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <h5 class="text-dark">Feedback</h5>
                    <p class="text-secondary small mb-0">Rate your doctor</p>
                </a>
            </div>
        </div>

        <div class="col-lg-2.4 col-md-4 col-sm-6 mb-4">
            <div class="card-modern text-center p-4 h-100">
                <a href="ambulance.php" class="text-decoration-none d-block">
                    <div class="widget-icon mx-auto bg-danger text-white">
                        <i class="bi bi-truck-front-fill"></i>
                    </div>
                    <h5 class="text-dark">Ambulance</h5>
                    <p class="text-secondary small mb-0">24x7 Emergency</p>
                </a>
            </div>
        </div>
    </div>

</div>

<footer class="text-center py-4 mt-5 text-secondary border-top">
    Narayan Hospital OPD Management System &copy; <?= date('Y') ?>
</footer>

<script>
function refreshQueueStatus() {
    fetch('get_queue_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }
            
            // Update Live Token Number
            document.getElementById('live-token-val').textContent = data.live_token;
            
            // Update Doctor Info Text below token
            const docInfoEl = document.getElementById('doctor-info-val');
            if (data.has_appointment) {
                docInfoEl.className = 'small mb-0 text-success';
                docInfoEl.innerHTML = `<i class="bi bi-check-circle-fill"></i> Your Doctor: Dr. ${escapeHTML(data.doctor_name)}`;
            } else {
                docInfoEl.className = 'small mb-0 text-secondary';
                docInfoEl.textContent = 'No active appointment today';
            }
            
            // Update My Token
            document.getElementById('my-token-val').textContent = data.my_token;
            // Update Queue Position
            document.getElementById('queue-pos-val').textContent = data.queue_position;
            // Update People Ahead
            document.getElementById('people-ahead-val').textContent = data.patients_ahead;
            
            // Update Estimated Wait Time
            document.getElementById('wait-time-val').textContent = data.estimated_wait;
            
            // Update Queue Progress Bar & percentage
            const progressBar = document.getElementById('progress-bar-val');
            progressBar.style.width = data.queue_progress + '%';
            progressBar.setAttribute('aria-valuenow', data.queue_progress);
            document.getElementById('progress-percent-val').textContent = data.queue_progress + '% Completed';
            
            // Update Doctor Availability status
            const docStatusEl = document.getElementById('doctor-status-text');
            if (data.has_appointment) {
                docStatusEl.innerHTML = `Dr. Status: <strong class="text-primary">${data.doctor_status}</strong>`;
                docStatusEl.className = 'badge bg-primary-subtle text-primary badge-modern';
            }
        })
        .catch(error => {
            console.error('Error refreshing queue status:', error);
        });
}

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, 
        tag => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            "'": '&#39;',
            '"': '&quot;'
        }[tag] || tag)
    );
}

// Set up interval to refresh queue status every 5 seconds without page reload
setInterval(refreshQueueStatus, 5000);
</script>

</body>
</html>