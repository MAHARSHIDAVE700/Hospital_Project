<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// ------------------------------
// POST HANDLERS (Clinical Operations)
// ------------------------------

// 1. Process Patient Admission (IPD Admission check-in)
if (isset($_POST['admit_patient'])) {
    $patient_id = intval($_POST['patient_id']);
    $doctor_id = intval($_POST['doctor_id']);
    $bed_id = intval($_POST['bed_id']);
    $reason = trim($_POST['admission_reason']);
    $bp = trim($_POST['initial_bp']);
    $temp = trim($_POST['initial_temp']);
    $pulse = trim($_POST['initial_pulse']);
    $weight = trim($_POST['initial_weight']);
    $adm_date = empty($_POST['admission_date']) ? date('Y-m-d H:i:s') : $_POST['admission_date'];

    // Check if patient already admitted
    $check = $conn->query("SELECT ipd_id FROM ipd_admissions WHERE patient_id = $patient_id AND status = 'Admitted'");
    if ($check && $check->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> This patient is already admitted in the IPD ward.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $conn->query("BEGIN");
        try {
            // Insert Admission
            $stmt = $conn->prepare("INSERT INTO ipd_admissions (patient_id, doctor_id, bed_id, admission_reason, initial_bp, initial_temp, initial_pulse, initial_weight, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Admitted')");
            $stmt->bind_param("iiissssss", $patient_id, $doctor_id, $bed_id, $reason, $bp, $temp, $pulse, $weight, $adm_date);
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert IPD admission log.");
            }
            $ipd_id = $conn->insert_id;

            // Set Bed status to Occupied
            $updateBed = $conn->query("UPDATE beds SET status = 'Occupied' WHERE bed_id = $bed_id");
            if (!$updateBed) {
                throw new Exception("Failed to lock bed status to Occupied.");
            }

            // Insert Bed Allocation link
            $allocStmt = $conn->prepare("INSERT INTO bed_allocations (bed_id, patient_id, admission_date, status) VALUES (?, ?, ?, 'Active')");
            $allocStmt->bind_param("iis", $bed_id, $patient_id, $adm_date);
            if (!$allocStmt->execute()) {
                throw new Exception("Failed to log Bed Allocation transaction.");
            }

            $conn->query("COMMIT");
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Admit IPD Patient', "Admitted patient ID {$patient_id} to Bed ID {$bed_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Patient admitted and Bed locked successfully.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to admit patient. " . htmlspecialchars($e->getMessage()) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 2. Log Progress / Daily Vitals
if (isset($_POST['log_progress'])) {
    $ipd_id = intval($_POST['ipd_id']);
    $pulse = trim($_POST['pulse_rate']);
    $temp = trim($_POST['temp_f']);
    $bp = trim($_POST['blood_pressure']);
    $notes = trim($_POST['clinical_notes']);
    $by = trim($_POST['logged_by'] ?? 'Staff Nurse');

    $stmt = $conn->prepare("INSERT INTO ipd_progress_logs (ipd_id, pulse_rate, temp_f, blood_pressure, clinical_notes, logged_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $ipd_id, $pulse, $temp, $bp, $notes, $by);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Log IPD Progress', "Logged clinical progress for Admission ID {$ipd_id}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Daily progress vitals and notes logged.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to log progress.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 3. Process Patient Discharge
if (isset($_POST['discharge_patient'])) {
    $ipd_id = intval($_POST['ipd_id']);
    $summary = trim($_POST['discharge_summary']);
    $status = trim($_POST['discharge_status']);
    $date = date('Y-m-d H:i:s');

    // Get bed info
    $query = $conn->query("SELECT bed_id, patient_id FROM ipd_admissions WHERE ipd_id = $ipd_id")->fetch_assoc();
    if ($query) {
        $bed_id = $query['bed_id'];
        $patient_id = $query['patient_id'];

        $conn->query("BEGIN");
        try {
            // Update Admission status
            $stmt = $conn->prepare("UPDATE ipd_admissions SET status = 'Discharged', discharge_date = ?, discharge_summary = ?, discharge_status = ? WHERE ipd_id = ?");
            $stmt->bind_param("sssi", $date, $summary, $status, $ipd_id);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update IPD admission record.");
            }

            // Set Bed status to Available
            $updateBed = $conn->query("UPDATE beds SET status = 'Available' WHERE bed_id = $bed_id");
            if (!$updateBed) {
                throw new Exception("Failed to release bed status to Available.");
            }

            // Discharge active bed allocation
            $updateAlloc = $conn->query("UPDATE bed_allocations SET status = 'Discharged', discharge_date = '$date' WHERE bed_id = $bed_id AND patient_id = $patient_id AND status = 'Active'");
            if (!$updateAlloc) {
                throw new Exception("Failed to close active Bed Allocation link.");
            }

            $conn->query("COMMIT");
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Discharge IPD Patient', "Discharged patient ID {$patient_id} from Bed ID {$bed_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Patient discharged successfully. Bed released.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to discharge patient. " . htmlspecialchars($e->getMessage()) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// ------------------------------
// DATA FETCHING & STATISTICS
// ------------------------------
$admittedList = $conn->query("
    SELECT ipd.*, u.full_name AS patient_name, d.full_name AS doctor_name, b.bed_number, b.bed_type
    FROM ipd_admissions ipd
    JOIN patients p ON ipd.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON ipd.doctor_id = d.doctor_id
    JOIN beds b ON ipd.bed_id = b.bed_id
    WHERE ipd.status = 'Admitted'
    ORDER BY ipd.admission_date DESC
");

$dischargedList = $conn->query("
    SELECT ipd.*, u.full_name AS patient_name, d.full_name AS doctor_name, b.bed_number, b.bed_type
    FROM ipd_admissions ipd
    JOIN patients p ON ipd.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON ipd.doctor_id = d.doctor_id
    JOIN beds b ON ipd.bed_id = b.bed_id
    WHERE ipd.status = 'Discharged'
    ORDER BY ipd.discharge_date DESC
");

$availableBeds = $conn->query("SELECT * FROM beds WHERE status = 'Available' ORDER BY bed_number ASC");

$patients = $conn->query("
    SELECT p.patient_id, u.full_name, p.phone
    FROM patients p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.full_name ASC
");

$doctors = $conn->query("SELECT doctor_id, full_name FROM doctors ORDER BY full_name ASC");

// Statistical widgets
$totalAdmittedCount = $conn->query("SELECT COUNT(*) AS total FROM ipd_admissions WHERE status='Admitted'")->fetch_assoc()['total'];
$totalDischargedCount = $conn->query("SELECT COUNT(*) AS total FROM ipd_admissions WHERE status='Discharged'")->fetch_assoc()['total'];
$icuCount = $conn->query("SELECT COUNT(*) AS total FROM ipd_admissions ip JOIN beds b ON ip.bed_id = b.bed_id WHERE ip.status='Admitted' AND b.bed_type='ICU'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPD Admission & Discharge | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .nav-pills .nav-link {
            border-radius: 12px;
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        .nav-pills .nav-link.active {
            background-color: var(--primary-color) !important;
            border-color: var(--primary-color);
        }
        .nav-pills .nav-link:not(.active) {
            background: #ffffff;
            border-color: var(--border-color);
            color: var(--text-secondary);
        }
        .nav-pills .nav-link:not(.active):hover {
            background: var(--primary-light);
            color: var(--primary-hover);
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
            <div class="hms-sidebar-group-title">Main Dashboard</div>
            <a href="dashboard.php" class="hms-sidebar-item">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            
            <div class="hms-sidebar-group-title">Operations</div>
            <a href="manage_doctors.php" class="hms-sidebar-item">
                <i class="bi bi-person-badge"></i> Doctors
            </a>
            <a href="manage_patients.php" class="hms-sidebar-item">
                <i class="bi bi-people"></i> Patients
            </a>
            <a href="manage_departments.php" class="hms-sidebar-item">
                <i class="bi bi-hospital"></i> Departments
            </a>
            <a href="manage_appointments.php" class="hms-sidebar-item">
                <i class="bi bi-calendar2-check"></i> Appointments
            </a>
            <a href="manage_beds.php" class="hms-sidebar-item">
                <i class="bi bi-bed"></i> Bed Management
            </a>
            <a href="manage_ipd.php" class="hms-sidebar-item active">
                <i class="bi bi-person-workspace"></i> IPD Admissions
            </a>
            <a href="manage_laboratory.php" class="hms-sidebar-item">
                <i class="bi bi-virus2"></i> Laboratory
            </a>
            <a href="manage_pharmacy.php" class="hms-sidebar-item">
                <i class="bi bi-prescription"></i> Pharmacy
            </a>
            <a href="manage_billing.php" class="hms-sidebar-item">
                <i class="bi bi-wallet2"></i> Billing Center
            </a>
            
            <div class="hms-sidebar-group-title">Analytics</div>
            <a href="analytics.php" class="hms-sidebar-item">
                <i class="bi bi-bar-chart-line"></i> Analytics
            </a>
            <a href="view_logs.php" class="hms-sidebar-item">
                <i class="bi bi-shield-check"></i> Audit Logs
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
                    <span>Narayan Administration</span>
                    <span><i class="bi bi-chevron-right text-muted fs-8"></i></span>
                    <span class="hms-breadcrumb-item-active">IPD Admissions & Discharge</span>
                </div>
            </div>
            <div class="hms-topbar-right">
                <div class="live-clock-widget d-none d-lg-flex me-3">
                    <i class="bi bi-clock"></i>
                    <span><?= date('D, M d, Y · h:i A') ?></span>
                </div>
                <div class="hms-topbar-profile">
                    <div class="avatar-circle">
                        <?php 
                            $nameParts = explode(' ', $_SESSION['admin_name']);
                            $initials = (count($nameParts) > 1) ? $nameParts[0][0].$nameParts[1][0] : $nameParts[0][0];
                            echo strtoupper($initials);
                        ?>
                    </div>
                    <span class="d-none d-md-inline fw-semibold"><?= htmlspecialchars($_SESSION['admin_name']) ?></span>
                </div>
            </div>
        </header>

        <div class="p-4">
            <?= $message ?>

            <!-- Premium Statistics Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Admitted Admitted</span>
                                <h3 class="fw-bold mb-0 text-danger"><?= $totalAdmittedCount ?> patients</h3>
                            </div>
                            <div class="bg-danger-subtle text-danger p-3 rounded-3">
                                <i class="bi bi-heart-pulse fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">ICU Patients</span>
                                <h3 class="fw-bold mb-0 text-warning"><?= $icuCount ?> critical</h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-activity fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Formal Discharges</span>
                                <h3 class="fw-bold mb-0 text-success"><?= $totalDischargedCount ?> recovered</h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-check2-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls and Navigation -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <ul class="nav nav-pills" id="ipdTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="admitted-tab" data-bs-toggle="tab" data-bs-target="#admitted" type="button" role="tab"><i class="bi bi-clipboard-pulse me-2"></i>In-Patient Dashboard</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="discharged-tab" data-bs-toggle="tab" data-bs-target="#discharged" type="button" role="tab"><i class="bi bi-archive me-2"></i>Discharge History</button>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#admitPatientModal"><i class="bi bi-person-plus me-2"></i>Admit New Patient</button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="ipdTabContent">
                <!-- Tab 1: Current In-Patients -->
                <div class="tab-pane fade show active" id="admitted" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Currently Admitted IPD Patients</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">IPD ID</th>
                                        <th>Patient Name</th>
                                        <th>Bed Number</th>
                                        <th>Attending Doctor</th>
                                        <th>Admission Vitals</th>
                                        <th>Date Admitted</th>
                                        <th class="text-end pe-4">Clinical Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($admittedList->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">No patients currently admitted.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $admittedList->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#IPD-<?= $row['ipd_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5 rounded-pill"><?= htmlspecialchars($row['bed_number']) ?> (<?= htmlspecialchars($row['bed_type']) ?>)</span></td>
                                                <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                                                <td class="small text-secondary">
                                                    BP: <?= htmlspecialchars($row['initial_bp']) ?> |
                                                    Temp: <?= htmlspecialchars($row['initial_temp']) ?>F<br>
                                                    Pulse: <?= htmlspecialchars($row['initial_pulse']) ?> bpm |
                                                    Wt: <?= htmlspecialchars($row['initial_weight']) ?>kg
                                                </td>
                                                <td><?= date('d M Y, h:i A', strtotime($row['admission_date'])) ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-info rounded-pill px-3 me-2" onclick="openProgressModal(<?= $row['ipd_id'] ?>, '<?= htmlspecialchars($row['patient_name']) ?>')"><i class="bi bi-plus-lg"></i> Vitals Notes</button>
                                                    <button class="btn btn-sm btn-danger text-white rounded-pill px-3" onclick="openDischargeModal(<?= $row['ipd_id'] ?>, '<?= htmlspecialchars($row['patient_name']) ?>')"><i class="bi bi-box-arrow-right"></i> Discharge</button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Discharge History -->
                <div class="tab-pane fade" id="discharged" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Discharged IPD Patient Records</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">IPD ID</th>
                                        <th>Patient Name</th>
                                        <th>Attending Doctor</th>
                                        <th>Bed Number</th>
                                        <th>Admission Reason</th>
                                        <th>Discharge Date</th>
                                        <th>Exit Condition</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($dischargedList->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No discharged patient histories found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $dischargedList->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#IPD-<?= $row['ipd_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td>Dr. <?= htmlspecialchars($row['doctor_name']) ?></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary px-2 py-1"><?= htmlspecialchars($row['bed_number']) ?></span></td>
                                                <td><span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?= htmlspecialchars($row['admission_reason']) ?>"><?= htmlspecialchars($row['admission_reason']) ?></span></td>
                                                <td><?= date('d M Y, h:i A', strtotime($row['discharge_date'])) ?></td>
                                                <td>
                                                    <?php if ($row['discharge_status'] === 'Recovered'): ?>
                                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1">Recovered</span>
                                                    <?php elseif ($row['discharge_status'] === 'Stable'): ?>
                                                        <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1">Stable</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-2.5 py-1"><?= htmlspecialchars($row['discharge_status'] ?? 'Stable') ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <a href="../patient/download_discharge_summary.php?id=<?= $row['ipd_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bi bi-file-earmark-pdf me-1"></i>Summary</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Admit Patient Modal -->
<div class="modal fade" id="admitPatientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">IPD Clinical Patient Admission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Select Patient</label>
                            <select name="patient_id" class="form-select rounded-3" required>
                                <option value="">Choose Patient...</option>
                                <?php 
                                if ($patients->num_rows > 0) {
                                    $patients->data_seek(0);
                                    while ($p = $patients->fetch_assoc()) {
                                        echo "<option value='{$p['patient_id']}'>{$p['full_name']} (Phone: {$p['phone']})</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Attending Medical Officer</label>
                            <select name="doctor_id" class="form-select rounded-3" required>
                                <option value="">Choose Doctor...</option>
                                <?php 
                                if ($doctors->num_rows > 0) {
                                    $doctors->data_seek(0);
                                    while ($d = $doctors->fetch_assoc()) {
                                        echo "<option value='{$d['doctor_id']}'>Dr. {$d['full_name']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Select Available Bed</label>
                            <select name="bed_id" class="form-select rounded-3" required>
                                <option value="">Choose Bed...</option>
                                <?php 
                                if ($availableBeds->num_rows > 0) {
                                    $availableBeds->data_seek(0);
                                    while ($b = $availableBeds->fetch_assoc()) {
                                        echo "<option value='{$b['bed_id']}'>{$b['bed_number']} - {$b['bed_type']} (INR " . number_format($b['price_per_day'], 0) . "/day)</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-semibold">Admission Timestamp</label>
                            <input type="datetime-local" name="admission_date" class="form-control rounded-3" value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason for Admission / Initial Diagnosis</label>
                        <textarea name="admission_reason" class="form-control rounded-3" rows="3" placeholder="Clinical reasons, symptoms, or acute findings..." required></textarea>
                    </div>

                    <h6 class="fw-bold border-bottom pb-2 mb-3 mt-4">Check-In Clinical Vitals:</h6>
                    
                    <div class="row">
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label fw-semibold">Blood Pressure</label>
                            <input type="text" name="initial_bp" class="form-control rounded-3" placeholder="e.g. 120/80" required>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label fw-semibold">Temperature (F)</label>
                            <input type="text" name="initial_temp" class="form-control rounded-3" placeholder="e.g. 98.6" required>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label fw-semibold">Pulse Rate (bpm)</label>
                            <input type="text" name="initial_pulse" class="form-control rounded-3" placeholder="e.g. 72" required>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label fw-semibold">Weight (kg)</label>
                            <input type="text" name="initial_weight" class="form-control rounded-3" placeholder="e.g. 68" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="admit_patient" class="btn btn-primary rounded-3 px-4">Admit Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Daily Progress Notes Modal -->
<div class="modal fade" id="progressModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Log daily Clinical Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="ipd_id" id="progress_ipd_id">
                    <div class="alert alert-light border small py-2 mb-3">
                        <strong>Patient:</strong> <span id="progress_patient_name"></span>
                    </div>
                    
                    <div class="row">
                        <div class="col-4 mb-3">
                            <label class="form-label fw-semibold">BP (mmHg)</label>
                            <input type="text" name="blood_pressure" class="form-control rounded-3" placeholder="120/80" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label fw-semibold">Temp (F)</label>
                            <input type="text" name="temp_f" class="form-control rounded-3" placeholder="98.6" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label class="form-label fw-semibold">Pulse (bpm)</label>
                            <input type="text" name="pulse_rate" class="form-control rounded-3" placeholder="72" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Daily Clinical Notes / Observations</label>
                        <textarea name="clinical_notes" class="form-control rounded-3" rows="4" placeholder="Log patient recovery course, medication updates, or complaints..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Staff Logger</label>
                        <input type="text" name="logged_by" class="form-control rounded-3" value="Staff Nurse" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="log_progress" class="btn btn-info text-white rounded-3 px-4">Save Progress Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Discharge Summary Modal -->
<div class="modal fade" id="dischargeModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Compile IPD Patient Discharge</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="ipd_id" id="discharge_ipd_id">
                    <div class="alert alert-light border small py-2 mb-3">
                        <strong>Patient:</strong> <span id="discharge_patient_name"></span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Condition at Discharge</label>
                        <select name="discharge_status" class="form-select rounded-3" required>
                            <option value="Recovered">Recovered / Cured</option>
                            <option value="Stable">Stable & Discharged</option>
                            <option value="Referred">Referred to another facility</option>
                            <option value="Deceased">Deceased</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Discharge Summary & Treatment course</label>
                        <textarea name="discharge_summary" class="form-control rounded-3" rows="6" placeholder="Final diagnosis, treatment course administered, discharge instructions, and follow-up advice..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="discharge_patient" class="btn btn-danger text-white rounded-3 px-4">Complete Discharge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    function openProgressModal(ipdId, patientName) {
        document.getElementById('progress_ipd_id').value = ipdId;
        document.getElementById('progress_patient_name').innerText = patientName;
        
        var progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
        progressModal.show();
    }

    function openDischargeModal(ipdId, patientName) {
        document.getElementById('discharge_ipd_id').value = ipdId;
        document.getElementById('discharge_patient_name').innerText = patientName;
        
        var dischargeModal = new bootstrap.Modal(document.getElementById('dischargeModal'));
        dischargeModal.show();
    }
</script>
</body>
</html>
