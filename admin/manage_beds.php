<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// ------------------------------
// POST HANDLERS (CRUD Operations)
// ------------------------------

// 1. Add Bed
if (isset($_POST['add_bed'])) {
    $bed_number = trim($_POST['bed_number']);
    $bed_type = trim($_POST['bed_type']);
    $price_per_day = floatval($_POST['price_per_day']);
    $status = trim($_POST['status']);

    // Check if bed number already exists
    $check = $conn->prepare("SELECT bed_id FROM beds WHERE bed_number = ?");
    $check->bind_param("s", $bed_number);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Bed number already exists.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO beds (bed_number, bed_type, price_per_day, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssds", $bed_number, $bed_type, $price_per_day, $status);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Add Bed', "Added bed number {$bed_number}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Bed added successfully.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to add bed.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 2. Edit Bed
if (isset($_POST['edit_bed'])) {
    $bed_id = intval($_POST['bed_id']);
    $bed_number = trim($_POST['bed_number']);
    $bed_type = trim($_POST['bed_type']);
    $price_per_day = floatval($_POST['price_per_day']);
    $status = trim($_POST['status']);

    // Check if duplicate bed number exists for other beds
    $check = $conn->prepare("SELECT bed_id FROM beds WHERE bed_number = ? AND bed_id != ?");
    $check->bind_param("si", $bed_number, $bed_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Another bed already uses this number.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $stmt = $conn->prepare("UPDATE beds SET bed_number = ?, bed_type = ?, price_per_day = ?, status = ? WHERE bed_id = ?");
        $stmt->bind_param("ssdsi", $bed_number, $bed_type, $price_per_day, $status, $bed_id);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Edit Bed', "Updated bed ID {$bed_id} details.");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Bed updated successfully.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to update bed.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 3. Delete Bed
if (isset($_GET['delete_bed_id'])) {
    $bed_id = intval($_GET['delete_bed_id']);
    
    // Check if occupied
    $check = $conn->query("SELECT status FROM beds WHERE bed_id = $bed_id")->fetch_assoc();
    if ($check && $check['status'] === 'Occupied') {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Cannot delete an occupied bed. Discharge the patient first.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $stmt = $conn->prepare("DELETE FROM beds WHERE bed_id = ?");
        $stmt->bind_param("i", $bed_id);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Delete Bed', "Deleted bed ID {$bed_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Bed deleted successfully.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to delete bed.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 4. Allocate Bed
if (isset($_POST['allocate_bed'])) {
    $bed_id = intval($_POST['bed_id']);
    $patient_id = intval($_POST['patient_id']);
    $admission_date = empty($_POST['admission_date']) ? date('Y-m-d H:i:s') : $_POST['admission_date'];

    // Check if patient already has active bed allocation
    $check = $conn->query("SELECT allocation_id FROM bed_allocations WHERE patient_id = $patient_id AND status = 'Active'");
    if ($check && $check->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> This patient is already admitted to another bed.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        // Prepare transaction
        $conn->query("BEGIN");
        try {
            $stmt = $conn->prepare("INSERT INTO bed_allocations (bed_id, patient_id, admission_date, status) VALUES (?, ?, ?, 'Active')");
            $stmt->bind_param("iis", $bed_id, $patient_id, $admission_date);
            if (!$stmt->execute()) {
                throw new Exception("Failed to insert bed allocation record.");
            }
            
            $updateBed = $conn->query("UPDATE beds SET status = 'Occupied' WHERE bed_id = $bed_id");
            if (!$updateBed) {
                throw new Exception("Failed to update bed status to Occupied.");
            }
            
            $conn->query("COMMIT");
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Allocate Bed', "Allocated bed ID {$bed_id} to patient ID {$patient_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Bed allocated successfully.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 5. Discharge Patient
if (isset($_GET['discharge_allocation_id'])) {
    $allocation_id = intval($_GET['discharge_allocation_id']);
    
    // Get details
    $alloc = $conn->query("SELECT bed_id, patient_id FROM bed_allocations WHERE allocation_id = $allocation_id")->fetch_assoc();
    if ($alloc) {
        $bed_id = $alloc['bed_id'];
        
        $conn->query("BEGIN");
        try {
            $updateAlloc = $conn->query("UPDATE bed_allocations SET status = 'Discharged', discharge_date = CURRENT_TIMESTAMP WHERE allocation_id = $allocation_id");
            if (!$updateAlloc) {
                throw new Exception("Failed to update bed allocation status.");
            }
            
            $updateBed = $conn->query("UPDATE beds SET status = 'Available' WHERE bed_id = $bed_id");
            if (!$updateBed) {
                throw new Exception("Failed to update bed status to Available.");
            }
            
            $conn->query("COMMIT");
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Discharge Bed', "Discharged patient from bed ID {$bed_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Patient discharged and bed set to Available.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// ------------------------------
// READ QUERIES (Data Fetching)
// ------------------------------
$beds = $conn->query("SELECT * FROM beds ORDER BY bed_number ASC");

$allocations = $conn->query("
    SELECT ba.*, b.bed_number, b.bed_type, u.full_name AS patient_name
    FROM bed_allocations ba
    JOIN beds b ON ba.bed_id = b.bed_id
    JOIN patients p ON ba.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    ORDER BY ba.status ASC, ba.admission_date DESC
");

$availableBeds = $conn->query("SELECT * FROM beds WHERE status = 'Available' ORDER BY bed_number ASC");

$patients = $conn->query("
    SELECT p.patient_id, u.full_name, p.phone
    FROM patients p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.full_name ASC
");

// Counts for Statistics
$totalBedsCount = $conn->query("SELECT COUNT(*) AS total FROM beds")->fetch_assoc()['total'];
$occupiedBedsCount = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status = 'Occupied'")->fetch_assoc()['total'];
$maintenanceBedsCount = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status = 'Maintenance'")->fetch_assoc()['total'];
$availableBedsCount = $conn->query("SELECT COUNT(*) AS total FROM beds WHERE status = 'Available'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bed Management | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .bed-grid-card {
            transition: var(--transition);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            position: relative;
        }
        .bed-grid-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .status-badge-floating {
            position: absolute;
            top: 12px;
            right: 12px;
        }
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
            <a href="manage_beds.php" class="hms-sidebar-item active">
                <i class="bi bi-bed"></i> Bed Management
            </a>
            <a href="manage_ipd.php" class="hms-sidebar-item">
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
                    <span class="hms-breadcrumb-item-active">Bed Control Centre</span>
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
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Beds</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalBedsCount ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-door-closed fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Available</span>
                                <h3 class="fw-bold mb-0 text-success"><?= $availableBedsCount ?></h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-check-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Occupied</span>
                                <h3 class="fw-bold mb-0 text-danger"><?= $occupiedBedsCount ?></h3>
                            </div>
                            <div class="bg-danger-subtle text-danger p-3 rounded-3">
                                <i class="bi bi-person-fill-lock fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Maintenance</span>
                                <h3 class="fw-bold mb-0 text-warning"><?= $maintenanceBedsCount ?></h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-wrench-adjustable-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls and Navigation -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <ul class="nav nav-pills" id="bedTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab"><i class="bi bi-list-ul me-2"></i>Bed Inventory</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="allocations-tab" data-bs-toggle="tab" data-bs-target="#allocations" type="button" role="tab"><i class="bi bi-person-add me-2"></i>Bed Allocations</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="board-tab" data-bs-toggle="tab" data-bs-target="#board" type="button" role="tab"><i class="bi bi-grid-3x3-gap me-2"></i>Visual Bed Board</button>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#allocateBedModal"><i class="bi bi-link-45deg me-2"></i>Allocate Bed</button>
                    <button class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#addBedModal"><i class="bi bi-plus-lg me-2"></i>Add New Bed</button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="bedTabContent">
                <!-- Tab 1: Inventory -->
                <div class="tab-pane fade show active" id="inventory" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Hospital Bed Register</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Bed ID</th>
                                        <th>Bed Number</th>
                                        <th>Type</th>
                                        <th>Price Per Day</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($beds->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No beds configured. Add a new bed to start.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $beds->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#<?= $row['bed_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['bed_number']) ?></span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1.5"><?= htmlspecialchars($row['bed_type']) ?></span></td>
                                                <td class="fw-semibold text-teal">INR <?= number_format($row['price_per_day'], 2) ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'Available'): ?>
                                                        <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Available</span>
                                                    <?php elseif ($row['status'] === 'Occupied'): ?>
                                                        <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5 rounded-pill"><i class="bi bi-lock-fill me-1"></i>Occupied</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning px-2.5 py-1.5 rounded-pill"><i class="bi bi-exclamation-triangle-fill me-1"></i>Maintenance</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-secondary me-2 rounded-2" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil"></i></button>
                                                    <a href="?delete_bed_id=<?= $row['bed_id'] ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Are you sure you want to delete this bed?')"><i class="bi bi-trash"></i></a>
                                                                              </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Allocations -->
                <div class="tab-pane fade" id="allocations" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Patient Bed Allocations Log</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Allocation ID</th>
                                        <th>Patient Name</th>
                                        <th>Bed Number</th>
                                        <th>Bed Type</th>
                                        <th>Admission Date</th>
                                        <th>Discharge Date</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($allocations->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No patient bed allocations found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $allocations->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#<?= $row['allocation_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary px-2 py-1"><?= htmlspecialchars($row['bed_number']) ?></span></td>
                                                <td><?= htmlspecialchars($row['bed_type']) ?></td>
                                                <td><?= date('d M Y, h:i A', strtotime($row['admission_date'])) ?></td>
                                                <td><?= $row['discharge_date'] ? date('d M Y, h:i A', strtotime($row['discharge_date'])) : '-' ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'Active'): ?>
                                                        <span class="badge bg-success px-2.5 py-1.5 rounded-pill">Active Admitted</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5 rounded-pill">Discharged</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($row['status'] === 'Active'): ?>
                                                        <a href="?discharge_allocation_id=<?= $row['allocation_id'] ?>" class="btn btn-sm btn-outline-danger px-3 py-1 fw-semibold rounded-pill" onclick="return confirm('Are you sure you want to discharge this patient?')">Discharge</a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-secondary disabled rounded-pill px-3 py-1">Complete</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Visual Bed Board -->
                <div class="tab-pane fade" id="board" role="tabpanel">
                    <div class="row g-4">
                        <?php 
                        // Reset pointer
                        if ($beds->num_rows > 0) {
                            $beds->data_seek(0);
                            while ($bed = $beds->fetch_assoc()): 
                        ?>
                            <div class="col-md-3">
                                <div class="card bed-grid-card border-0 shadow-sm p-4 bg-white">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="fs-1">🛏️</div>
                                        <div>
                                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($bed['bed_number']) ?></h5>
                                            <span class="text-muted fs-8 fw-semibold uppercase"><?= htmlspecialchars($bed['bed_type']) ?></span>
                                            <div class="mt-2 text-teal fw-semibold fs-7">INR <?= number_format($bed['price_per_day'], 2) ?>/day</div>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-top">
                                        <?php if ($bed['status'] === 'Available'): ?>
                                            <span class="badge bg-success-subtle text-success d-block text-center py-2 rounded-3 fs-7 fw-semibold mb-2"><i class="bi bi-check-circle-fill me-1"></i>Available</span>
                                            <button class="btn btn-sm btn-outline-primary w-100 rounded-3" onclick="allocateBedFromBoard(<?= $bed['bed_id'] ?>, '<?= htmlspecialchars($bed['bed_number']) ?>')">Allocate</button>
                                        <?php elseif ($bed['status'] === 'Occupied'): 
                                            // Get patient info
                                            $activeAlloc = $conn->query("
                                                SELECT ba.allocation_id, u.full_name, ba.admission_date 
                                                FROM bed_allocations ba 
                                                JOIN patients p ON ba.patient_id = p.patient_id 
                                                JOIN users u ON p.user_id = u.id 
                                                WHERE ba.bed_id = {$bed['bed_id']} AND ba.status = 'Active' LIMIT 1
                                            ")->fetch_assoc();
                                        ?>
                                            <span class="badge bg-danger-subtle text-danger d-block text-center py-2 rounded-3 fs-7 fw-semibold mb-2"><i class="bi bi-lock-fill me-1"></i>Occupied</span>
                                            <?php if ($activeAlloc): ?>
                                                <div class="small text-dark mb-1"><strong>Patient:</strong> <?= htmlspecialchars($activeAlloc['full_name']) ?></div>
                                                <div class="small text-muted mb-3"><strong>Admitted:</strong> <?= date('d M, h:i A', strtotime($activeAlloc['admission_date'])) ?></div>
                                                <a href="?discharge_allocation_id=<?= $activeAlloc['allocation_id'] ?>" class="btn btn-sm btn-danger w-100 rounded-3 text-white">Discharge</a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary w-100 rounded-3 disabled">Occupied</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning d-block text-center py-2 rounded-3 fs-7 fw-semibold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i>Maintenance</span>
                                            <form method="POST" action="">
                                                <input type="hidden" name="bed_id" value="<?= $bed['bed_id'] ?>">
                                                <input type="hidden" name="bed_number" value="<?= htmlspecialchars($bed['bed_number']) ?>">
                                                <input type="hidden" name="bed_type" value="<?= htmlspecialchars($bed['bed_type']) ?>">
                                                <input type="hidden" name="price_per_day" value="<?= $bed['price_per_day'] ?>">
                                                <input type="hidden" name="status" value="Available">
                                                <button type="submit" name="edit_bed" class="btn btn-sm btn-outline-success w-100 rounded-3">Make Available</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            endwhile;
                        } 
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add Bed Modal -->
<div class="modal fade" id="addBedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Add New Bed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Number / Name</label>
                        <input type="text" name="bed_number" class="form-control rounded-3" placeholder="e.g., WARD-A-101" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Type</label>
                        <select name="bed_type" class="form-select rounded-3" required>
                            <option value="General Ward">General Ward</option>
                            <option value="Semi-Private">Semi-Private</option>
                            <option value="Private Suite">Private Suite</option>
                            <option value="ICU">ICU</option>
                            <option value="NICU">NICU</option>
                            <option value="Emergency Room">Emergency Room</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Price Per Day (INR)</label>
                        <input type="number" step="0.01" name="price_per_day" class="form-control rounded-3" value="300.00" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Initial Status</label>
                        <select name="status" class="form-select rounded-3" required>
                            <option value="Available">Available</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_bed" class="btn btn-primary rounded-3 px-4">Add Bed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bed Modal -->
<div class="modal fade" id="editBedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Edit Bed Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="bed_id" id="edit_bed_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Number / Name</label>
                        <input type="text" name="bed_number" id="edit_bed_number" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bed Type</label>
                        <select name="bed_type" id="edit_bed_type" class="form-select rounded-3" required>
                            <option value="General Ward">General Ward</option>
                            <option value="Semi-Private">Semi-Private</option>
                            <option value="Private Suite">Private Suite</option>
                            <option value="ICU">ICU</option>
                            <option value="NICU">NICU</option>
                            <option value="Emergency Room">Emergency Room</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Price Per Day (INR)</label>
                        <input type="number" step="0.01" name="price_per_day" id="edit_price_per_day" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" id="edit_status" class="form-select rounded-3" required>
                            <option value="Available">Available</option>
                            <option value="Occupied">Occupied</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_bed" class="btn btn-success rounded-3 px-4 text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Allocate Bed Modal -->
<div class="modal fade" id="allocateBedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Allocate Bed to Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Available Bed</label>
                        <select name="bed_id" id="allocate_bed_select" class="form-select rounded-3" required>
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
                    <div class="mb-3">
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
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admission Date & Time</label>
                        <input type="datetime-local" name="admission_date" class="form-control rounded-3" value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="allocate_bed" class="btn btn-primary rounded-3 px-4">Allocate Bed</button>
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

    function openEditModal(bed) {
        document.getElementById('edit_bed_id').value = bed.bed_id;
        document.getElementById('edit_bed_number').value = bed.bed_number;
        document.getElementById('edit_bed_type').value = bed.bed_type;
        document.getElementById('edit_price_per_day').value = bed.price_per_day;
        document.getElementById('edit_status').value = bed.status;
        
        var editModal = new bootstrap.Modal(document.getElementById('editBedModal'));
        editModal.show();
    }

    function allocateBedFromBoard(bedId, bedNumber) {
        document.getElementById('allocate_bed_select').value = bedId;
        var allocateModal = new bootstrap.Modal(document.getElementById('allocateBedModal'));
        allocateModal.show();
    }
</script>
</body>
</html>
