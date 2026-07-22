<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// ------------------------------
// JSON API: Fetch Unbilled Charges for Patient
// ------------------------------
if (isset($_GET['fetch_unbilled_patient_id'])) {
    $patient_id = intval($_GET['fetch_unbilled_patient_id']);
    
    // 1. OPD Appointments (Unbilled)
    $opdQuery = $conn->query("
        SELECT a.appointment_id, a.appointment_date, d.full_name AS doctor_name
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.patient_id = $patient_id AND a.invoice_id IS NULL AND a.fee_status != 'Paid Online'
    ");
    $opd_items = [];
    $opd_total = 0.00;
    while ($row = $opdQuery->fetch_assoc()) {
        $opd_total += 200.00; // standard fee
        $opd_items[] = [
            'id' => $row['appointment_id'],
            'description' => "OPD Consultation - Dr. {$row['doctor_name']} ({$row['appointment_date']})",
            'amount' => 200.00
        ];
    }
    
    // 2. IPD Bed Allocations (Unbilled)
    $bedQuery = $conn->query("
        SELECT ba.allocation_id, ba.admission_date, ba.discharge_date, b.bed_number, b.bed_type, b.price_per_day
        FROM bed_allocations ba
        JOIN beds b ON ba.bed_id = b.bed_id
        WHERE ba.patient_id = $patient_id AND ba.invoice_id IS NULL
    ");
    $bed_items = [];
    $bed_total = 0.00;
    while ($row = $bedQuery->fetch_assoc()) {
        $in = strtotime($row['admission_date']);
        $out = $row['discharge_date'] ? strtotime($row['discharge_date']) : time();
        $diff = max(1, ceil(($out - $in) / (60 * 60 * 24))); // Minimum 1 day
        $cost = $diff * floatval($row['price_per_day']);
        $bed_total += $cost;
        
        $status_lbl = $row['discharge_date'] ? "Discharged" : "Admitted";
        $bed_items[] = [
            'id' => $row['allocation_id'],
            'description' => "IPD Bed: {$row['bed_number']} ({$row['bed_type']}) - {$diff} days [{$status_lbl}]",
            'amount' => $cost
        ];
    }
    
    // 3. Lab Diagnostic requests (Unbilled)
    $labQuery = $conn->query("
        SELECT lr.request_id, lt.test_name, lt.price, lr.request_date
        FROM lab_requests lr
        JOIN lab_tests lt ON lr.test_id = lt.test_id
        WHERE lr.patient_id = $patient_id AND lr.invoice_id IS NULL AND lr.status = 'Completed'
    ");
    $lab_items = [];
    $lab_total = 0.00;
    while ($row = $labQuery->fetch_assoc()) {
        $cost = floatval($row['price']);
        $lab_total += $cost;
        $lab_items[] = [
            'id' => $row['request_id'],
            'description' => "Lab Test: {$row['test_name']} (" . date('d M', strtotime($row['request_date'])) . ")",
            'amount' => $cost
        ];
    }
    
    // 4. Pharmacy Dispense Medication receipts (Unbilled)
    $pharmQuery = $conn->query("
        SELECT md.dispense_id, md.total_price, md.dispense_date
        FROM medicine_dispenses md
        WHERE md.patient_id = $patient_id AND md.invoice_id IS NULL AND md.status = 'Dispensed'
    ");
    $pharm_items = [];
    $pharm_total = 0.00;
    while ($row = $pharmQuery->fetch_assoc()) {
        $cost = floatval($row['total_price']);
        $pharm_total += $cost;
        $pharm_items[] = [
            'id' => $row['dispense_id'],
            'description' => "Medication Dispense #INV-PH-{$row['dispense_id']} (" . date('d M', strtotime($row['dispense_date'])) . ")",
            'amount' => $cost
        ];
    }
    
    $grand_subtotal = $opd_total + $bed_total + $lab_total + $pharm_total;
    
    header('Content-Type: application/json');
    echo json_encode([
        'opd' => $opd_items,
        'opd_total' => $opd_total,
        'ipd' => $bed_items,
        'ipd_total' => $bed_total,
        'lab' => $lab_items,
        'lab_total' => $lab_total,
        'pharmacy' => $pharm_items,
        'pharmacy_total' => $pharm_total,
        'subtotal' => $grand_subtotal
    ]);
    exit();
}

// ------------------------------
// POST HANDLERS: Create / Settle Invoice
// ------------------------------

// 1. Generate Invoice
if (isset($_POST['generate_invoice'])) {
    $patient_id = intval($_POST['patient_id']);
    $opd_charge = floatval($_POST['opd_charges']);
    $ipd_charge = floatval($_POST['ipd_charges']);
    $lab_charge = floatval($_POST['lab_charges']);
    $pharm_charge = floatval($_POST['pharmacy_charges']);
    $tax = floatval($_POST['tax_amount']);
    $discount = floatval($_POST['discount']);
    
    $subtotal = $opd_charge + $ipd_charge + $lab_charge + $pharm_charge;
    $total = ($subtotal + $tax) - $discount;
    
    $invoice_number = "INV-" . rand(100000, 999999);
    
    $conn->query("BEGIN");
    try {
        // Insert Invoice
        $stmt = $conn->prepare("INSERT INTO invoices (patient_id, invoice_number, opd_charges, ipd_charges, lab_charges, pharmacy_charges, tax_amount, discount, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid')");
        $stmt->bind_param("isddddddd", $patient_id, $invoice_number, $opd_charge, $ipd_charge, $lab_charge, $pharm_charge, $tax, $discount, $total);
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert invoice record.");
        }
        $invoice_id = $conn->insert_id;
        
        // Link all unbilled items for this patient to this invoice
        $up1 = $conn->query("UPDATE appointments SET invoice_id = $invoice_id WHERE patient_id = $patient_id AND invoice_id IS NULL AND fee_status != 'Paid Online'");
        if (!$up1) {
            throw new Exception("Failed to update unbilled appointments.");
        }
        
        $up2 = $conn->query("UPDATE bed_allocations SET invoice_id = $invoice_id WHERE patient_id = $patient_id AND invoice_id IS NULL");
        if (!$up2) {
            throw new Exception("Failed to update unbilled bed allocations.");
        }
        
        $up3 = $conn->query("UPDATE lab_requests SET invoice_id = $invoice_id WHERE patient_id = $patient_id AND invoice_id IS NULL AND status = 'Completed'");
        if (!$up3) {
            throw new Exception("Failed to update unbilled laboratory requests.");
        }
        
        $up4 = $conn->query("UPDATE medicine_dispenses SET invoice_id = $invoice_id WHERE patient_id = $patient_id AND invoice_id IS NULL AND status = 'Dispensed'");
        if (!$up4) {
            throw new Exception("Failed to update unbilled medicine dispenses.");
        }
        
        $conn->query("COMMIT");
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Generate Invoice', "Created invoice {$invoice_number} for patient ID {$patient_id}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Invoice {$invoice_number} generated successfully.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } catch (Exception $e) {
        $conn->query("ROLLBACK");
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to generate invoice. " . htmlspecialchars($e->getMessage()) . "
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 2. Mark Settle (Pay cash/counter)
if (isset($_GET['pay_invoice_id'])) {
    $invoice_id = intval($_GET['pay_invoice_id']);
    $method = trim($_GET['method'] ?? 'Cash');
    
    $stmt = $conn->prepare("UPDATE invoices SET status = 'Paid', payment_method = ? WHERE invoice_id = ?");
    $stmt->bind_param("si", $method, $invoice_id);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Settle Invoice', "Settle invoice ID {$invoice_id} via {$method}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Invoice settled and marked Paid.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// ------------------------------
// DATA FETCHING
// ------------------------------
$invoices = $conn->query("
    SELECT inv.*, u.full_name AS patient_name
    FROM invoices inv
    JOIN patients p ON inv.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    ORDER BY inv.created_at DESC
");

$patients = $conn->query("
    SELECT p.patient_id, u.full_name, p.phone
    FROM patients p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.full_name ASC
");

// Statistics counters
$totalInvoicesCount = $conn->query("SELECT COUNT(*) AS total FROM invoices")->fetch_assoc()['total'];
$unpaidInvoicesCount = $conn->query("SELECT COUNT(*) AS total FROM invoices WHERE status='Unpaid'")->fetch_assoc()['total'];
$revenuePaid = $conn->query("SELECT SUM(total_amount) AS total FROM invoices WHERE status='Paid'")->fetch_assoc()['total'] ?? 0.00;
$receivableUnpaid = $conn->query("SELECT SUM(total_amount) AS total FROM invoices WHERE status='Unpaid'")->fetch_assoc()['total'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Invoices | Smart Hospital</title>
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
            <a href="manage_laboratory.php" class="hms-sidebar-item">
                <i class="bi bi-virus2"></i> Laboratory
            </a>
            <a href="manage_pharmacy.php" class="hms-sidebar-item">
                <i class="bi bi-prescription"></i> Pharmacy
            </a>
            <a href="manage_billing.php" class="hms-sidebar-item active">
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
                    <span class="hms-breadcrumb-item-active">Patient Billing Center</span>
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
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Bills</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalInvoicesCount ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-receipt-cutoff fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Unpaid Invoices</span>
                                <h3 class="fw-bold mb-0 text-danger"><?= $unpaidInvoicesCount ?></h3>
                            </div>
                            <div class="bg-danger-subtle text-danger p-3 rounded-3">
                                <i class="bi bi-exclamation-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Revenue Settled</span>
                                <h3 class="fw-bold mb-0 text-success">INR <?= number_format($revenuePaid, 2) ?></h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-cash-stack fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Outstanding Bills</span>
                                <h3 class="fw-bold mb-0 text-warning">INR <?= number_format($receivableUnpaid, 2) ?></h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-clock-history fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <ul class="nav nav-pills" id="billingTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoicesList" type="button" role="tab"><i class="bi bi-list-check me-2"></i>Invoices Directory</button>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#generateInvoiceModal"><i class="bi bi-plus-lg me-2"></i>Compile Patient Invoice</button>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="tab-content" id="billingTabContent">
                <div class="tab-pane fade show active" id="invoicesList" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Patient Bills Ledger</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Invoice #</th>
                                        <th>Patient Name</th>
                                        <th>Aggregated Breakdown</th>
                                        <th>Date Generated</th>
                                        <th>Tax / Discount</th>
                                        <th>Total Invoice</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($invoices->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No patient bills generated.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $invoices->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold"><?= htmlspecialchars($row['invoice_number']) ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td class="small">
                                                    OPD: ₹<?= number_format($row['opd_charges'], 0) ?> |
                                                    IPD: ₹<?= number_format($row['ipd_charges'], 0) ?> |
                                                    Lab: ₹<?= number_format($row['lab_charges'], 0) ?> |
                                                    Phar: ₹<?= number_format($row['pharmacy_charges'], 0) ?>
                                                </td>
                                                <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                                <td class="small">
                                                    Tax: +₹<?= number_format($row['tax_amount'], 0) ?><br>
                                                    Disc: -₹<?= number_format($row['discount'], 0) ?>
                                                </td>
                                                <td class="fw-bold text-dark">INR <?= number_format($row['total_amount'], 2) ?></td>
                                                <td>
                                                    <?php if ($row['status'] === 'Paid'): ?>
                                                        <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Paid (<?= htmlspecialchars($row['payment_method'] ?? 'Cash') ?>)</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5 rounded-pill"><i class="bi bi-clock-history me-1"></i>Unpaid</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <?php if ($row['status'] === 'Unpaid'): ?>
                                                        <div class="btn-group me-2">
                                                            <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle rounded-pill px-3" data-bs-toggle="dropdown">Settle Bill</button>
                                                            <ul class="dropdown-menu">
                                                                <li><a class="dropdown-item" href="?pay_invoice_id=<?= $row['invoice_id'] ?>&method=Cash">Paid via Cash</a></li>
                                                                <li><a class="dropdown-item" href="?pay_invoice_id=<?= $row['invoice_id'] ?>&method=Card">Paid via Card</a></li>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                    <a href="../patient/download_invoice.php?id=<?= $row['invoice_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bi bi-download me-1"></i>Receipt</a>
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

<!-- Compile Invoice Modal -->
<div class="modal fade" id="generateInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Compile Consolidated Patient Bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Admitted / OPD Patient</label>
                        <select name="patient_id" class="form-select rounded-3" onchange="fetchUnbilledCharges(this.value)" required>
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

                    <div id="unbilledChargesBreakdown" class="d-none">
                        <h6 class="fw-bold border-bottom pb-2 mb-3">Itemized Pending Unbilled Items:</h6>
                        <div id="unbilledList" class="mb-3 small"></div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">OPD Consultation Charges (INR)</label>
                                <input type="number" step="0.01" name="opd_charges" id="opd_val" class="form-control rounded-3" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">IPD Bed charges (INR)</label>
                                <input type="number" step="0.01" name="ipd_charges" id="ipd_val" class="form-control rounded-3" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Laboratory Diagnostics (INR)</label>
                                <input type="number" step="0.01" name="lab_charges" id="lab_val" class="form-control rounded-3" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">Pharmacy Medications (INR)</label>
                                <input type="number" step="0.01" name="pharmacy_charges" id="pharm_val" class="form-control rounded-3" readonly>
                            </div>
                        </div>
                        
                        <div class="row border-top pt-3">
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Taxes & Surcharges (INR)</label>
                                <input type="number" step="0.01" name="tax_amount" id="tax_val" value="0.00" class="form-control rounded-3" oninput="calculateInvoiceTotal()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Hospital Discount (INR)</label>
                                <input type="number" step="0.01" name="discount" id="discount_val" value="0.00" class="form-control rounded-3" oninput="calculateInvoiceTotal()">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold text-primary">Consolidated Total (INR)</label>
                                <div class="h4 fw-bold mt-1 text-teal">₹<span id="grandTotalText">0.00</span></div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="unbilledChargesEmpty" class="text-center text-muted p-4">
                        <i class="bi bi-receipt display-4 text-muted"></i>
                        <p class="mt-2">Select a patient above to check their unbilled records.</p>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_invoice" id="submitBillBtn" class="btn btn-primary rounded-3 px-4 d-none">Generate Bill</button>
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

    let billSubtotal = 0.00;

    function fetchUnbilledCharges(patientId) {
        const breakdown = document.getElementById('unbilledChargesBreakdown');
        const emptyBox = document.getElementById('unbilledChargesEmpty');
        const list = document.getElementById('unbilledList');
        const submitBtn = document.getElementById('submitBillBtn');
        
        if (!patientId) {
            breakdown.classList.add('d-none');
            submitBtn.classList.add('d-none');
            emptyBox.classList.remove('d-none');
            emptyBox.innerHTML = `<i class="bi bi-receipt display-4 text-muted"></i><p class="mt-2">Select a patient above to check their unbilled records.</p>`;
            return;
        }

        emptyBox.innerHTML = `<div class="text-center p-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-secondary">Analyzing records...</p></div>`;

        fetch(`manage_billing.php?fetch_unbilled_patient_id=${patientId}`)
            .then(res => res.json())
            .then(data => {
                billSubtotal = parseFloat(data.subtotal);
                
                if (billSubtotal === 0.00) {
                    breakdown.classList.add('d-none');
                    submitBtn.classList.add('d-none');
                    emptyBox.classList.remove('d-none');
                    emptyBox.innerHTML = `<i class="bi bi-check-circle text-success fs-1"></i><h6 class="mt-2 fw-bold text-success">Account Fully Settled</h6><p class="small text-secondary">This patient has no outstanding unbilled OPD, IPD, Lab, or Pharmacy charges.</p>`;
                    return;
                }

                emptyBox.classList.add('d-none');
                breakdown.classList.remove('d-none');
                submitBtn.classList.remove('d-none');

                document.getElementById('opd_val').value = data.opd_total.toFixed(2);
                document.getElementById('ipd_val').value = data.ipd_total.toFixed(2);
                document.getElementById('lab_val').value = data.lab_total.toFixed(2);
                document.getElementById('pharm_val').value = data.pharmacy_total.toFixed(2);

                // Build text breakdown list
                let html = `<ul class="list-group list-group-flush border rounded-3 bg-white">`;
                
                // OPD
                data.opd.forEach(i => {
                    html += `<li class="list-group-item d-flex justify-content-between"><span>🎫 ${i.description}</span><span class="text-dark fw-bold">₹${i.amount}</span></li>`;
                });
                // IPD
                data.ipd.forEach(i => {
                    html += `<li class="list-group-item d-flex justify-content-between"><span>🛏️ ${i.description}</span><span class="text-dark fw-bold">₹${i.amount}</span></li>`;
                });
                // Lab
                data.lab.forEach(i => {
                    html += `<li class="list-group-item d-flex justify-content-between"><span>🔬 ${i.description}</span><span class="text-dark fw-bold">₹${i.amount}</span></li>`;
                });
                // Phar
                data.pharmacy.forEach(i => {
                    html += `<li class="list-group-item d-flex justify-content-between"><span>💊 ${i.description}</span><span class="text-dark fw-bold">₹${i.amount}</span></li>`;
                });
                
                html += `</ul>`;
                list.innerHTML = html;
                
                calculateInvoiceTotal();
            })
            .catch(() => {
                emptyBox.innerHTML = `<div class="text-danger p-3">Failed to process patient records.</div>`;
            });
    }

    function calculateInvoiceTotal() {
        const tax = parseFloat(document.getElementById('tax_val').value) || 0.00;
        const discount = parseFloat(document.getElementById('discount_val').value) || 0.00;
        const grandTotal = (billSubtotal + tax) - discount;
        
        document.getElementById('grandTotalText').innerText = grandTotal.toFixed(2);
    }
</script>
</body>
</html>
