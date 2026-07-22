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

// 1. Add Test
if (isset($_POST['add_test'])) {
    $test_name = trim($_POST['test_name']);
    $test_code = trim($_POST['test_code']);
    $sample_type = trim($_POST['sample_type']);
    $price = floatval($_POST['price']);

    // Check if code exists
    $check = $conn->prepare("SELECT test_id FROM lab_tests WHERE test_code = ?");
    $check->bind_param("s", $test_code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Test code already exists.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $stmt = $conn->prepare("INSERT INTO lab_tests (test_name, test_code, sample_type, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $test_name, $test_code, $sample_type, $price);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Add Lab Test', "Added lab test {$test_name}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Lab test added to directory.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to add lab test.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 2. Edit Test
if (isset($_POST['edit_test'])) {
    $test_id = intval($_POST['test_id']);
    $test_name = trim($_POST['test_name']);
    $test_code = trim($_POST['test_code']);
    $sample_type = trim($_POST['sample_type']);
    $price = floatval($_POST['price']);

    // Check duplicate code
    $check = $conn->prepare("SELECT test_id FROM lab_tests WHERE test_code = ? AND test_id != ?");
    $check->bind_param("si", $test_code, $test_id);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Another test already uses this code.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $stmt = $conn->prepare("UPDATE lab_tests SET test_name = ?, test_code = ?, sample_type = ?, price = ? WHERE test_id = ?");
        $stmt->bind_param("sssdi", $test_name, $test_code, $sample_type, $price, $test_id);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Edit Lab Test', "Updated details for test ID {$test_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Lab test updated successfully.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } else {
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> Failed to update lab test.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 3. Delete Test
if (isset($_GET['delete_test_id'])) {
    $test_id = intval($_GET['delete_test_id']);
    $stmt = $conn->prepare("DELETE FROM lab_tests WHERE test_id = ?");
    $stmt->bind_param("i", $test_id);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Delete Lab Test', "Deleted lab test ID {$test_id}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Lab test removed from directory.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to delete lab test.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 4. Create Lab Request / Order
if (isset($_POST['create_request'])) {
    $patient_id = intval($_POST['patient_id']);
    $test_id = intval($_POST['test_id']);
    $doctor_id = empty($_POST['doctor_id']) ? null : intval($_POST['doctor_id']);

    $stmt = $conn->prepare("INSERT INTO lab_requests (patient_id, doctor_id, test_id, status) VALUES (?, ?, ?, 'Pending')");
    $stmt->bind_param("iii", $patient_id, $doctor_id, $test_id);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Create Lab Request', "Ordered test ID {$test_id} for patient ID {$patient_id}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Laboratory test requested successfully.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to order laboratory test.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 5. Update Request Status (Sample Collected / Cancelled)
if (isset($_GET['update_request_id']) && isset($_GET['status'])) {
    $request_id = intval($_GET['update_request_id']);
    $status = trim($_GET['status']);

    $allowedStatus = ['Sample Collected', 'Cancelled'];
    if (in_array($status, $allowedStatus)) {
        $stmt = $conn->prepare("UPDATE lab_requests SET status = ? WHERE request_id = ?");
        $stmt->bind_param("si", $status, $request_id);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Update Lab Request Status', "Set request ID {$request_id} status to {$status}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Order status updated to {$status}.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// 6. Enter Results
if (isset($_POST['enter_results'])) {
    $request_id = intval($_POST['request_id']);
    $result_summary = trim($_POST['result_summary']);
    $result_details = trim($_POST['result_details']);

    $stmt = $conn->prepare("UPDATE lab_requests SET status = 'Completed', result_summary = ?, result_details = ?, result_date = CURRENT_TIMESTAMP WHERE request_id = ?");
    $stmt->bind_param("ssi", $result_summary, $result_details, $request_id);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Log Lab Results', "Logged diagnostic results for request ID {$request_id}");
        
        // Trigger verification email to patient with PDF report if available (Step 5 style EMR attachments)
        try {
            $detailsQuery = $conn->query("
                SELECT lr.*, lt.test_name, lt.test_code, lt.sample_type, lt.price,
                       d.full_name AS doctor_name, dep.department_name,
                       u.email, u.full_name AS patient_name, pat.phone, pat.age, pat.gender
                FROM lab_requests lr
                JOIN lab_tests lt ON lr.test_id = lt.test_id
                LEFT JOIN doctors d ON lr.doctor_id = d.doctor_id
                LEFT JOIN departments dep ON d.department_id = dep.department_id
                JOIN patients pat ON lr.patient_id = pat.patient_id
                JOIN users u ON pat.user_id = u.id
                WHERE lr.request_id = $request_id
            ");
            if ($detailsQuery && $row = $detailsQuery->fetch_assoc()) {
                $patient_email = trim($row['email']);
                if (!empty($patient_email)) {
                    include_once "../includes/email_helper.php";
                    include_once "../includes/pdf_helper.php";
                    
                    // Generate PDF Report in memory
                    $pdfContent = PDFHelper::generateLabReportPDF($row);
                    $attachments = [
                        [
                            'content'  => base64_encode($pdfContent),
                            'filename' => 'Lab_Report_' . $request_id . '.pdf',
                            'type'     => 'application/pdf'
                        ]
                    ];
                    
                    $subject = "Laboratory Diagnostic Report Complete - Narayan Hospital";
                    $body = "
                        <p>Dear <strong>" . htmlspecialchars($row['patient_name']) . "</strong>,</p>
                        <p>Your diagnostic laboratory test for <strong>" . htmlspecialchars($row['test_name']) . "</strong> is complete.</p>
                        <p>We have attached your official PDF laboratory report to this email for your medical records.</p>
                        <p>Result Summary: <em>" . htmlspecialchars($result_summary) . "</em></p>
                        <p>Thank you for choosing Narayan Hospital.</p>
                    ";
                    EmailHelper::sendEmail($patient_email, $subject, EmailHelper::getTemplate("Laboratory Report Available", $row['patient_name'], $body), $attachments);
                }
            }
        } catch (Exception $e) { /* ignore mail errors */ }

        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Diagnostic reports and results logged. Patient has been notified.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to save lab results.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// ------------------------------
// DATA FETCHING (Directory & Active logs)
// ------------------------------
$tests = $conn->query("SELECT * FROM lab_tests ORDER BY test_name ASC");

$activeOrders = $conn->query("
    SELECT lr.*, lt.test_name, lt.test_code, lt.sample_type, u.full_name AS patient_name, d.full_name AS doctor_name
    FROM lab_requests lr
    JOIN lab_tests lt ON lr.test_id = lt.test_id
    JOIN patients p ON lr.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN doctors d ON lr.doctor_id = d.doctor_id
    WHERE lr.status IN ('Pending', 'Sample Collected')
    ORDER BY lr.request_date DESC
");

$completedOrders = $conn->query("
    SELECT lr.*, lt.test_name, lt.test_code, lt.sample_type, u.full_name AS patient_name, d.full_name AS doctor_name
    FROM lab_requests lr
    JOIN lab_tests lt ON lr.test_id = lt.test_id
    JOIN patients p ON lr.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN doctors d ON lr.doctor_id = d.doctor_id
    WHERE lr.status IN ('Completed', 'Cancelled')
    ORDER BY lr.result_date DESC, lr.request_date DESC
");

$patients = $conn->query("
    SELECT p.patient_id, u.full_name, p.phone
    FROM patients p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.full_name ASC
");

$doctors = $conn->query("SELECT doctor_id, full_name FROM doctors ORDER BY full_name ASC");

// Statistical widgets
$totalRequestsCount = $conn->query("SELECT COUNT(*) AS total FROM lab_requests")->fetch_assoc()['total'];
$pendingRequestsCount = $conn->query("SELECT COUNT(*) AS total FROM lab_requests WHERE status='Pending'")->fetch_assoc()['total'];
$collectedSamplesCount = $conn->query("SELECT COUNT(*) AS total FROM lab_requests WHERE status='Sample Collected'")->fetch_assoc()['total'];
$completedTestsCount = $conn->query("SELECT COUNT(*) AS total FROM lab_requests WHERE status='Completed'")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Management | Smart Hospital</title>
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
            <a href="manage_ipd.php" class="hms-sidebar-item">
                <i class="bi bi-person-workspace"></i> IPD Admissions
            </a>
            <a href="manage_laboratory.php" class="hms-sidebar-item active">
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
                    <span class="hms-breadcrumb-item-active">Laboratory Operations</span>
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
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Requests</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalRequestsCount ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-file-earmark-medical fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Pending Samples</span>
                                <h3 class="fw-bold mb-0 text-warning"><?= $pendingRequestsCount ?></h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-droplet-half fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">In Processing</span>
                                <h3 class="fw-bold mb-0 text-info"><?= $collectedSamplesCount ?></h3>
                            </div>
                            <div class="bg-info-subtle text-info p-3 rounded-3">
                                <i class="bi bi-gear-wide-connected fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Completed Reports</span>
                                <h3 class="fw-bold mb-0 text-success"><?= $completedTestsCount ?></h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-check2-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Controls -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <ul class="nav nav-pills" id="labTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab"><i class="bi bi-activity me-2"></i>Active Orders</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab"><i class="bi bi-archive me-2"></i>Completed Log</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="directory-tab" data-bs-toggle="tab" data-bs-target="#directory" type="button" role="tab"><i class="bi bi-journals me-2"></i>Test Directory</button>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#createRequestModal"><i class="bi bi-prescription2 me-2"></i>Request Test</button>
                    <button class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#addTestModal"><i class="bi bi-plus-lg me-2"></i>Add Lab Test</button>
                </div>
            </div>

            <!-- Tabs Content -->
            <div class="tab-content" id="labTabContent">
                <!-- Tab 1: Active Orders -->
                <div class="tab-pane fade show active" id="orders" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Active Lab Orders Queue</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Order ID</th>
                                        <th>Patient Name</th>
                                        <th>Ordered Test</th>
                                        <th>Sample Type</th>
                                        <th>Doctor</th>
                                        <th>Date Requested</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($activeOrders->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No active lab orders in queue.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $activeOrders->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#<?= $row['request_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['test_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($row['test_code']) ?></small>
                                                </td>
                                                <td><span class="badge bg-purple-subtle text-purple px-2.5 py-1.5 rounded-pill"><?= htmlspecialchars($row['sample_type']) ?></span></td>
                                                <td><?= $row['doctor_name'] ? 'Dr. ' . htmlspecialchars($row['doctor_name']) : 'Admin Direct' ?></td>
                                                <td><?= date('d M Y, h:i A', strtotime($row['request_date'])) ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'Pending'): ?>
                                                        <span class="badge bg-warning-subtle text-warning px-2.5 py-1.5 rounded-pill"><i class="bi bi-clock-history me-1"></i>Pending Sample</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info-subtle text-info px-2.5 py-1.5 rounded-pill"><i class="bi bi-shield-shaded me-1"></i>Sample Collected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($row['status'] === 'Pending'): ?>
                                                        <a href="?update_request_id=<?= $row['request_id'] ?>&status=Sample+Collected" class="btn btn-sm btn-outline-info rounded-pill px-3 me-1">Collect Sample</a>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-success text-white rounded-pill px-3 me-1" onclick="openResultsModal(<?= $row['request_id'] ?>, '<?= htmlspecialchars($row['patient_name']) ?>', '<?= htmlspecialchars($row['test_name']) ?>')">Enter Results</button>
                                                    <?php endif; ?>
                                                    <a href="?update_request_id=<?= $row['request_id'] ?>&status=Cancelled" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Are you sure you want to cancel this order?')">Cancel</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Completed Log -->
                <div class="tab-pane fade" id="completed" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Diagnostic Report History Archive</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Order ID</th>
                                        <th>Patient Name</th>
                                        <th>Test Details</th>
                                        <th>Doctor</th>
                                        <th>Requested Date</th>
                                        <th>Result Date</th>
                                        <th>Status</th>
                                        <th>Result Summary</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($completedOrders->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">No completed laboratory records found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $completedOrders->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#<?= $row['request_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($row['test_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($row['test_code']) ?> · <?= htmlspecialchars($row['sample_type']) ?></small>
                                                </td>
                                                <td><?= $row['doctor_name'] ? 'Dr. ' . htmlspecialchars($row['doctor_name']) : 'Admin Direct' ?></td>
                                                <td><?= date('d M Y', strtotime($row['request_date'])) ?></td>
                                                <td><?= $row['result_date'] ? date('d M Y, h:i A', strtotime($row['result_date'])) : '-' ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'Completed'): ?>
                                                        <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check2-circle me-1"></i>Completed</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary px-2.5 py-1.5 rounded-pill">Cancelled</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="text-truncate d-inline-block" style="max-width: 150px;" title="<?= htmlspecialchars($row['result_summary'] ?? '') ?>"><?= htmlspecialchars($row['result_summary'] ?? '-') ?></span></td>
                                                <td class="text-end pe-4">
                                                    <?php if ($row['status'] === 'Completed'): ?>
                                                        <a href="../patient/download_lab_report.php?id=<?= $row['request_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bi bi-download me-1"></i>Report</a>
                                                    <?php else: ?>
                                                        -
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

                <!-- Tab 3: Directory -->
                <div class="tab-pane fade" id="directory" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Configure Laboratory Diagnostics</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Test ID</th>
                                        <th>Test Name</th>
                                        <th>Code</th>
                                        <th>Sample Type</th>
                                        <th>Price</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($tests->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No diagnostic tests in directory. Add a test to begin.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $tests->data_seek(0);
                                        while ($row = $tests->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#<?= $row['test_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['test_name']) ?></span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary px-2.5 py-1"><?= htmlspecialchars($row['test_code']) ?></span></td>
                                                <td><?= htmlspecialchars($row['sample_type']) ?></td>
                                                <td class="fw-semibold text-teal">INR <?= number_format($row['price'], 2) ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-secondary me-2 rounded-2" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil"></i></button>
                                                    <a href="?delete_test_id=<?= $row['test_id'] ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Are you sure you want to delete this test type?')"><i class="bi bi-trash"></i></a>
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

<!-- Add Test Modal -->
<div class="modal fade" id="addTestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Configure Diagnostic Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Name</label>
                        <input type="text" name="test_name" class="form-control rounded-3" placeholder="e.g., Complete Blood Count" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Code (Unique)</label>
                        <input type="text" name="test_code" class="form-control rounded-3" placeholder="e.g., LAB-CBC" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sample Required</label>
                        <select name="sample_type" class="form-select rounded-3" required>
                            <option value="Blood">Blood</option>
                            <option value="Urine">Urine</option>
                            <option value="Saliva">Saliva</option>
                            <option value="Swab">Swab / Culture</option>
                            <option value="Stool">Stool</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Price (INR)</label>
                        <input type="number" step="0.01" name="price" class="form-control rounded-3" value="300.00" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_test" class="btn btn-primary rounded-3 px-4">Add Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Test Modal -->
<div class="modal fade" id="editTestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Edit Diagnostic Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="test_id" id="edit_test_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Name</label>
                        <input type="text" name="test_name" id="edit_test_name" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Test Code</label>
                        <input type="text" name="test_code" id="edit_test_code" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Sample Required</label>
                        <select name="sample_type" id="edit_sample_type" class="form-select rounded-3" required>
                            <option value="Blood">Blood</option>
                            <option value="Urine">Urine</option>
                            <option value="Saliva">Saliva</option>
                            <option value="Swab">Swab / Culture</option>
                            <option value="Stool">Stool</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Price (INR)</label>
                        <input type="number" step="0.01" name="price" id="edit_price" class="form-control rounded-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_test" class="btn btn-success rounded-3 px-4 text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Request Test Modal -->
<div class="modal fade" id="createRequestModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Order Lab Test</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
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
                        <label class="form-label fw-semibold">Select Lab Test</label>
                        <select name="test_id" class="form-select rounded-3" required>
                            <option value="">Choose Test Type...</option>
                            <?php 
                            if ($tests->num_rows > 0) {
                                $tests->data_seek(0);
                                while ($t = $tests->fetch_assoc()) {
                                    echo "<option value='{$t['test_id']}'>{$t['test_name']} [{$t['test_code']}] (INR " . number_format($t['price'], 0) . ")</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Requesting Doctor (Optional)</label>
                        <select name="doctor_id" class="form-select rounded-3">
                            <option value="">Choose Doctor (If referred)...</option>
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
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_request" class="btn btn-primary rounded-3 px-4">Request Test</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Enter Results Modal -->
<div class="modal fade" id="resultsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Log Diagnostic Report Results</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="request_id" id="result_request_id">
                    <div class="row mb-3 bg-light p-3 rounded mx-1">
                        <div class="col-md-6"><strong>Patient:</strong> <span id="result_patient_name"></span></div>
                        <div class="col-md-6"><strong>Test Ordered:</strong> <span id="result_test_name"></span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Result Summary (Overall diagnosis / Interpretation)</label>
                        <input type="text" name="result_summary" class="form-control rounded-3" placeholder="e.g. Mild Anemia / Normal lipid counts" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Result Details (Quantitative parameters and values)</label>
                        <textarea name="result_details" class="form-control rounded-3" rows="8" placeholder="e.g.&#10;Hemoglobin: 11.8 g/dL (Normal: 12.0 - 16.0)&#10;Total RBC: 4.1 million/uL (Normal: 4.0 - 5.2)&#10;WBC Count: 6,400 /uL (Normal: 4,000 - 11,000)&#10;Platelets: 240,000 /uL (Normal: 150,000 - 450,000)" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="enter_results" class="btn btn-success rounded-3 px-4 text-white">Submit Lab Report</button>
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

    function openEditModal(test) {
        document.getElementById('edit_test_id').value = test.test_id;
        document.getElementById('edit_test_name').value = test.test_name;
        document.getElementById('edit_test_code').value = test.test_code;
        document.getElementById('edit_sample_type').value = test.sample_type;
        document.getElementById('edit_price').value = test.price;
        
        var editModal = new bootstrap.Modal(document.getElementById('editTestModal'));
        editModal.show();
    }

    function openResultsModal(requestId, patientName, testName) {
        document.getElementById('result_request_id').value = requestId;
        document.getElementById('result_patient_name').innerText = patientName;
        document.getElementById('result_test_name').innerText = testName;
        
        var resultsModal = new bootstrap.Modal(document.getElementById('resultsModal'));
        resultsModal.show();
    }
</script>
</body>
</html>
