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

// 1. Add Medicine
if (isset($_POST['add_medicine'])) {
    $med_name = trim($_POST['medicine_name']);
    $gen_name = trim($_POST['generic_name']);
    $category = trim($_POST['category']);
    $qty = intval($_POST['stock_quantity']);
    $price = floatval($_POST['unit_price']);
    $expiry = trim($_POST['expiry_date']);

    $stmt = $conn->prepare("INSERT INTO medicines (medicine_name, generic_name, category, stock_quantity, unit_price, expiry_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssids", $med_name, $gen_name, $category, $qty, $price, $expiry);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Add Medicine', "Added medicine {$med_name} to inventory");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Medicine added to inventory.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to add medicine.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 2. Edit Medicine
if (isset($_POST['edit_medicine'])) {
    $med_id = intval($_POST['medicine_id']);
    $med_name = trim($_POST['medicine_name']);
    $gen_name = trim($_POST['generic_name']);
    $category = trim($_POST['category']);
    $qty = intval($_POST['stock_quantity']);
    $price = floatval($_POST['unit_price']);
    $expiry = trim($_POST['expiry_date']);

    $stmt = $conn->prepare("UPDATE medicines SET medicine_name = ?, generic_name = ?, category = ?, stock_quantity = ?, unit_price = ?, expiry_date = ? WHERE medicine_id = ?");
    $stmt->bind_param("sssidsi", $med_name, $gen_name, $category, $qty, $price, $expiry, $med_id);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Edit Medicine', "Updated medicine details for ID {$med_id}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Medicine details updated.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to update medicine.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 3. Delete Medicine
if (isset($_GET['delete_medicine_id'])) {
    $med_id = intval($_GET['delete_medicine_id']);
    $stmt = $conn->prepare("DELETE FROM medicines WHERE medicine_id = ?");
    $stmt->bind_param("i", $med_id);
    if ($stmt->execute()) {
        ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Delete Medicine', "Removed medicine ID {$med_id} from inventory");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Medicine deleted from inventory.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Failed to delete medicine.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// 4. Dispense Prescription (Process checkout)
if (isset($_POST['dispense_meds'])) {
    $patient_id = intval($_POST['patient_id']);
    $med_ids = $_POST['med_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    
    if (empty($patient_id) || empty($med_ids)) {
        $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
            <strong>Error:</strong> Select a patient and add at least one medication.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    } else {
        $conn->query("BEGIN");
        try {
            $total_price = 0.00;
            
            // Loop once to calculate total and check stock availability
            $items_to_dispense = [];
            for ($i = 0; $i < count($med_ids); $i++) {
                $m_id = intval($med_ids[$i]);
                $qty = intval($quantities[$i]);
                if ($qty <= 0) continue;
                
                // Get item details
                $medQuery = $conn->query("SELECT medicine_name, unit_price, stock_quantity FROM medicines WHERE medicine_id = $m_id");
                $med = $medQuery->fetch_assoc();
                if (!$med) {
                    throw new Exception("Medicine not found in inventory.");
                }
                if ($med['stock_quantity'] < $qty) {
                    throw new Exception("Insufficient stock for: " . $med['medicine_name'] . " (In Stock: " . $med['stock_quantity'] . ")");
                }
                
                $price = $med['unit_price'];
                $item_total = $qty * $price;
                $total_price += $item_total;
                
                $items_to_dispense[] = [
                    'medicine_id' => $m_id,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => $item_total
                ];
            }
            
            if (empty($items_to_dispense)) {
                throw new Exception("No valid quantities were specified.");
            }
            
            // Insert Dispense Record
            $stmt = $conn->prepare("INSERT INTO medicine_dispenses (patient_id, total_price, status) VALUES (?, ?, 'Dispensed')");
            if (!$stmt) throw new Exception("Prepare failed for dispense insert: " . $conn->error);
            $stmt->bind_param("id", $patient_id, $total_price);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record master dispense log: " . $conn->error);
            }
            $dispense_id = $conn->insert_id;
            if (!$dispense_id) throw new Exception("Could not retrieve new dispense ID after insert.");
            
            // Insert line-items and decrement stock
            $itemStmt = $conn->prepare("INSERT INTO medicine_dispense_items (dispense_id, medicine_id, quantity, price_per_unit, total_price) VALUES (?, ?, ?, ?, ?)");
            if (!$itemStmt) throw new Exception("Prepare failed for dispense_items insert: " . $conn->error);
            $stockStmt = $conn->prepare("UPDATE medicines SET stock_quantity = stock_quantity - ? WHERE medicine_id = ?");
            if (!$stockStmt) throw new Exception("Prepare failed for stock update: " . $conn->error);
            
            foreach ($items_to_dispense as $item) {
                $d_id   = (int)$dispense_id;
                $m_id2  = (int)$item['medicine_id'];
                $qty2   = (int)$item['quantity'];
                $uprice = (float)$item['unit_price'];
                $itotal = (float)$item['total_price'];
                $itemStmt->bind_param("iiidd", $d_id, $m_id2, $qty2, $uprice, $itotal);
                if (!$itemStmt->execute()) {
                    throw new Exception("Failed to insert dispense item for medicine #{$m_id2}: " . $conn->error);
                }
                
                $decQty = (int)$item['quantity'];
                $decMid = (int)$item['medicine_id'];
                $stockStmt->bind_param("ii", $decQty, $decMid);
                if (!$stockStmt->execute()) {
                    throw new Exception("Failed to update stock for medicine #{$decMid}: " . $conn->error);
                }
            }
            
            $conn->query("COMMIT");
            ActivityLogger::log($_SESSION['admin_id'], 'admin', 'Dispense Medication', "Dispensed invoice #{$dispense_id} (INR {$total_price}) to patient ID {$patient_id}");
            $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
                <strong>Success:</strong> Medications dispensed successfully. Invoice #{$dispense_id} generated.
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        } catch (Exception $e) {
            $conn->query("ROLLBACK");
            $message = "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
                <strong>Error:</strong> " . $e->getMessage() . "
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
        }
    }
}

// ------------------------------
// DATA FETCHING & STATISTICS
// ------------------------------
$inventory = $conn->query("SELECT *, CASE WHEN expiry_date <= CURRENT_DATE + INTERVAL '90 days' THEN 1 ELSE 0 END AS near_expiry FROM medicines ORDER BY medicine_name ASC");

$invoices = $conn->query("
    SELECT md.*, u.full_name AS patient_name
    FROM medicine_dispenses md
    JOIN patients p ON md.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    ORDER BY md.dispense_date DESC
");

$patients = $conn->query("
    SELECT p.patient_id, u.full_name, p.phone
    FROM patients p
    JOIN users u ON p.user_id = u.id
    ORDER BY u.full_name ASC
");

// Stats counters
$totalMedsCount = $conn->query("SELECT COUNT(*) AS total FROM medicines")->fetch_assoc()['total'];
$lowStockCount = $conn->query("SELECT COUNT(*) AS total FROM medicines WHERE stock_quantity < 20")->fetch_assoc()['total'];
$expiringCount = $conn->query("SELECT COUNT(*) AS total FROM medicines WHERE expiry_date <= CURRENT_DATE + INTERVAL '90 days'")->fetch_assoc()['total'];
$totalSales = $conn->query("SELECT SUM(total_price) AS total FROM medicine_dispenses WHERE status='Dispensed'")->fetch_assoc()['total'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy & Inventory | Smart Hospital</title>
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
            <a href="manage_pharmacy.php" class="hms-sidebar-item active">
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
                    <span class="hms-breadcrumb-item-active">Pharmacy & Medication Stock</span>
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
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Medicines Catalog</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalMedsCount ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-capsule fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Low Stock Alerts</span>
                                <h3 class="fw-bold mb-0 text-danger"><?= $lowStockCount ?></h3>
                            </div>
                            <div class="bg-danger-subtle text-danger p-3 rounded-3">
                                <i class="bi bi-exclamation-triangle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Expiring / Expired</span>
                                <h3 class="fw-bold mb-0 text-warning"><?= $expiringCount ?></h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-calendar-x fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Sales Revenue</span>
                                <h3 class="fw-bold mb-0 text-success">INR <?= number_format($totalSales, 2) ?></h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-wallet2 fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Controls -->
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <ul class="nav nav-pills" id="pharmacyTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="inventory-tab" data-bs-toggle="tab" data-bs-target="#inventory" type="button" role="tab"><i class="bi bi-boxes me-2"></i>Medication Stock</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="dispense-tab" data-bs-toggle="tab" data-bs-target="#dispense" type="button" role="tab"><i class="bi bi-cart-plus me-2"></i>Dispense Meds</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="invoices-tab" data-bs-toggle="tab" data-bs-target="#invoices" type="button" role="tab"><i class="bi bi-receipt me-2"></i>Sales Invoices</button>
                    </li>
                </ul>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary rounded-3 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#addMedModal"><i class="bi bi-plus-lg me-2"></i>Add New Drug</button>
                </div>
            </div>

            <!-- Tabs Content -->
            <div class="tab-content" id="pharmacyTabContent">
                <!-- Tab 1: Medication Stock -->
                <div class="tab-pane fade show active" id="inventory" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Pharmacy Inventory Directory</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Medicine ID</th>
                                        <th>Medicine / Brand Name</th>
                                        <th>Generic Name</th>
                                        <th>Category</th>
                                        <th>Stock Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Expiry Date</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($inventory->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No medicines in inventory. Add a drug to begin.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $inventory->fetch_assoc()): ?>
                                            <tr class="<?= $row['stock_quantity'] < 20 ? 'table-danger-subtle' : '' ?>">
                                                <td class="ps-4 text-muted fw-semibold">#<?= $row['medicine_id'] ?></td>
                                                <td>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($row['medicine_name']) ?></span>
                                                    <?php if ($row['stock_quantity'] < 20): ?>
                                                        <span class="badge bg-danger rounded-pill ms-1" title="Low Stock"><i class="bi bi-exclamation-circle"></i> Low Stock</span>
                                                    <?php endif; ?>
                                                    <?php if ($row['near_expiry']): ?>
                                                        <span class="badge bg-warning text-dark rounded-pill ms-1" title="Expiring Soon"><i class="bi bi-calendar-x"></i> Expiring</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="text-secondary small"><?= htmlspecialchars($row['generic_name']) ?></span></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1"><?= htmlspecialchars($row['category']) ?></span></td>
                                                <td class="fw-bold <?= $row['stock_quantity'] < 20 ? 'text-danger' : 'text-dark' ?>"><?= $row['stock_quantity'] ?> units</td>
                                                <td class="fw-semibold text-teal">INR <?= number_format($row['unit_price'], 2) ?></td>
                                                <td class="small <?= $row['near_expiry'] ? 'text-warning font-bold' : 'text-muted' ?>"><?= date('d M Y', strtotime($row['expiry_date'])) ?></td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-secondary me-2 rounded-2" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)"><i class="bi bi-pencil"></i></button>
                                                    <a href="?delete_medicine_id=<?= $row['medicine_id'] ?>" class="btn btn-sm btn-outline-danger rounded-2" onclick="return confirm('Are you sure you want to delete this medicine from stocks?')"><i class="bi bi-trash"></i></a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Dispense Medicine -->
                <div class="tab-pane fade" id="dispense" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-5">
                            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                                <h5 class="fw-bold mb-3 text-dark">Dispense Order Checkout</h5>
                                <form method="POST" id="dispenseForm">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Select Admitted / OPD Patient</label>
                                        <select name="patient_id" id="dispense_patient" class="form-select rounded-3" onchange="fetchPatientPrescriptions(this.value)" required>
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
                                        <label class="form-label fw-semibold">Medication Cart Items</label>
                                        <div id="cartItemsContainer">
                                            <!-- Dynamic rows added here -->
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-3 mt-2" onclick="addCartRow()"><i class="bi bi-plus-circle me-1"></i>Add Medicine Line</button>
                                    </div>

                                    <div class="bg-light p-3 rounded-3 mb-4">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary fw-semibold">Total Invoice Amount:</span>
                                            <h4 class="fw-bold mb-0 text-dark">INR <span id="cartTotal">0.00</span></h4>
                                        </div>
                                    </div>

                                    <button type="submit" name="dispense_meds" class="btn btn-success text-white w-100 rounded-3 py-2 fw-semibold"><i class="bi bi-check2-circle me-2"></i>Complete Dispense & Checkout</button>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card border-0 shadow-sm p-4 rounded-4 bg-white h-100" style="min-height: 400px;">
                                <h5 class="fw-bold mb-3 text-dark">Patient Doctor Prescriptions Review</h5>
                                <div id="prescriptionReviewBox" class="text-muted p-5 text-center bg-light rounded-4 h-75 d-flex align-items-center justify-content-center">
                                    <div>
                                        <i class="bi bi-prescription display-3 text-muted"></i>
                                        <p class="mt-3">Select a patient on the left to pull their clinical doctor's prescription transcripts.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Sales Invoices -->
                <div class="tab-pane fade" id="invoices" role="tabpanel">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0 text-dark">Medication Billing & Dispense Invoices</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Invoice ID</th>
                                        <th>Patient Name</th>
                                        <th>Dispensed Date</th>
                                        <th>Total Paid</th>
                                        <th>Status</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($invoices->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No sales invoices logged.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php while ($row = $invoices->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 text-muted fw-semibold">#INV-PH-<?= $row['dispense_id'] ?></td>
                                                <td><span class="fw-bold text-dark"><?= htmlspecialchars($row['patient_name']) ?></span></td>
                                                <td><?= date('d M Y, h:i A', strtotime($row['dispense_date'])) ?></td>
                                                <td class="fw-semibold text-teal">INR <?= number_format($row['total_price'], 2) ?></td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-wallet2 me-1"></i>Paid Receipt</span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" onclick="viewInvoiceItems(<?= $row['dispense_id'] ?>)"><i class="bi bi-eye"></i> View Items</button>
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

<!-- Add Medicine Modal -->
<div class="modal fade" id="addMedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Add New Medication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Medicine Brand Name</label>
                        <input type="text" name="medicine_name" class="form-control rounded-3" placeholder="e.g., Paracetamol 500mg" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Generic Name</label>
                        <input type="text" name="generic_name" class="form-control rounded-3" placeholder="e.g., Acetaminophen" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category / Classification</label>
                        <select name="category" class="form-select rounded-3" required>
                            <option value="Analgesic / Antipyretic">Analgesic / Antipyretic</option>
                            <option value="Antibiotic">Antibiotic</option>
                            <option value="Antidiabetic">Antidiabetic</option>
                            <option value="Antihyperlipidemic">Antihyperlipidemic</option>
                            <option value="Antihistamine">Antihistamine</option>
                            <option value="NSAID / Painkiller">NSAID / Painkiller</option>
                            <option value="Antacid / PPI">Antacid / PPI</option>
                            <option value="Cardiovascular">Cardiovascular</option>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Stock level (Qty)</label>
                            <input type="number" name="stock_quantity" class="form-control rounded-3" value="100" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Price per Unit (INR)</label>
                            <input type="number" step="0.01" name="unit_price" class="form-control rounded-3" value="2.50" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batch Expiration Date</label>
                        <input type="date" name="expiry_date" class="form-control rounded-3" value="<?= date('Y-m-d', strtotime('+1 year')) ?>" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_medicine" class="btn btn-primary rounded-3 px-4">Add Medicine</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div class="modal fade" id="editMedModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Edit Medicine Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="medicine_id" id="edit_med_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Medicine Brand Name</label>
                        <input type="text" name="medicine_name" id="edit_med_name" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Generic Name</label>
                        <input type="text" name="generic_name" id="edit_gen_name" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category / Classification</label>
                        <input type="text" name="category" id="edit_category" class="form-control rounded-3" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Stock Level</label>
                            <input type="number" name="stock_quantity" id="edit_qty" class="form-control rounded-3" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Unit Price (INR)</label>
                            <input type="number" step="0.01" name="unit_price" id="edit_price" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batch Expiration Date</label>
                        <input type="date" name="expiry_date" id="edit_expiry" class="form-control rounded-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_medicine" class="btn btn-success rounded-3 px-4 text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Invoice Items View Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="fw-bold text-dark">Invoice Itemization Breakdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <div id="invoiceItemsContent">
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Backend Ajax helper tags / Array values -->
<?php
// Build inventory array for JS (NeonDBResult — use fetch loop, NOT mysqli_fetch_all)
$inventoryMedsArr = [];
if ($inventory && $inventory->num_rows > 0) {
    $inventory->data_seek(0);
    while ($iRow = $inventory->fetch_assoc()) {
        $inventoryMedsArr[] = $iRow;
    }
}
?>
<script>
    const inventoryMeds = <?= json_encode($inventoryMedsArr) ?>;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    function openEditModal(med) {
        document.getElementById('edit_med_id').value = med.medicine_id;
        document.getElementById('edit_med_name').value = med.medicine_name;
        document.getElementById('edit_gen_name').value = med.generic_name;
        document.getElementById('edit_category').value = med.category;
        document.getElementById('edit_qty').value = med.stock_quantity;
        document.getElementById('edit_price').value = med.unit_price;
        document.getElementById('edit_expiry').value = med.expiry_date;
        
        var editModal = new bootstrap.Modal(document.getElementById('editMedModal'));
        editModal.show();
    }

    // Pull Patient prescriptions using Fetch API
    function fetchPatientPrescriptions(patientId) {
        const reviewBox = document.getElementById('prescriptionReviewBox');
        if (!patientId) {
            reviewBox.innerHTML = `<div><i class="bi bi-prescription display-3 text-muted"></i><p class="mt-3">Select a patient on the left to pull their clinical doctor's prescription transcripts.</p></div>`;
            return;
        }

        reviewBox.innerHTML = `<div class="text-center p-5"><div class="spinner-border text-teal" role="status"></div><p class="mt-2 text-secondary">Fetching EMR logs...</p></div>`;

        fetch(`../patient/my_prescriptions.php?fetch_raw_patient_id=${patientId}`)
            .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    reviewBox.innerHTML = `
                        <div class="text-center p-5">
                            <i class="bi bi-folder2-open display-4 text-muted"></i>
                            <h6 class="fw-bold text-dark mt-3">No Active EMR Prescriptions</h6>
                            <p class="small text-secondary">Attending doctor has not saved prescriptions for this patient.</p>
                        </div>`;
                } else {
                    let html = `<div class="w-100 text-start" style="max-height: 450px; overflow-y: auto;">`;
                    data.forEach(p => {
                        html += `
                            <div class="card border border-light shadow-sm p-3 mb-3 bg-white rounded-3" style="border-left: 4px solid var(--primary-color) !important;">
                                <div class="d-flex justify-content-between mb-2 small text-secondary">
                                    <span><strong>Dr:</strong> Dr. ${p.full_name}</span>
                                    <span><strong>Date:</strong> ${p.created_at}</span>
                                </div>
                                <div class="mb-2"><strong>Diagnosis:</strong> <div class="text-dark bg-light p-2 rounded small mt-1">${p.diagnosis}</div></div>
                                <div><strong>Prescribed Medications:</strong> <div class="text-danger bg-danger-subtle p-2 rounded small mt-1 fw-bold">${p.medicines}</div></div>
                            </div>
                        `;
                    });
                    html += `</div>`;
                    reviewBox.innerHTML = html;
                }
            })
            .catch(() => {
                reviewBox.innerHTML = `<div class="text-danger p-5">Failed to fetch EMR data.</div>`;
            });
    }

    // Dynamic Pharmacy Cart Management
    function addCartRow() {
        const container = document.getElementById('cartItemsContainer');
        const rowId = 'cart_row_' + Date.now();
        
        let selectOptions = `<option value="">Choose medicine...</option>`;
        inventoryMeds.forEach(m => {
            selectOptions += `<option value="${m.medicine_id}" data-price="${m.unit_price}" data-stock="${m.stock_quantity}">${m.medicine_name} (Stock: ${m.stock_quantity} · INR ${m.unit_price})</option>`;
        });

        const rowHtml = `
            <div class="row g-2 mb-2 align-items-center cart-item-row" id="${rowId}">
                <div class="col-7">
                    <select name="med_id[]" class="form-select rounded-3 med-select" onchange="calculateCartTotal()" required>
                        ${selectOptions}
                    </select>
                </div>
                <div class="col-3">
                    <input type="number" name="quantity[]" class="form-control rounded-3 qty-input" value="1" min="1" oninput="calculateCartTotal()" required>
                </div>
                <div class="col-2 text-end">
                    <button type="button" class="btn btn-outline-danger btn-sm rounded-3" onclick="removeCartRow('${rowId}')"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', rowHtml);
    }

    function removeCartRow(rowId) {
        document.getElementById(rowId).remove();
        calculateCartTotal();
    }

    function calculateCartTotal() {
        let total = 0.00;
        const rows = document.querySelectorAll('.cart-item-row');
        rows.forEach(r => {
            const select = r.querySelector('.med-select');
            const qtyInput = r.querySelector('.qty-input');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const price = parseFloat(selectedOption.getAttribute('data-price'));
                const qty = parseInt(qtyInput.value) || 0;
                total += (price * qty);
            }
        });
        document.getElementById('cartTotal').innerText = total.toFixed(2);
    }

    // Fetch Invoice Line items
    function viewInvoiceItems(dispenseId) {
        var detailsModal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));
        detailsModal.show();

        const box = document.getElementById('invoiceItemsContent');
        box.innerHTML = `<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>`;

        fetch(`../patient/my_pharmacy_bills.php?fetch_dispense_id=${dispenseId}`)
            .then(res => res.json())
            .then(data => {
                let html = `
                    <table class="table table-hover align-middle table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Medicine Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>`;
                data.forEach(i => {
                    html += `
                        <tr>
                            <td><strong class="text-dark">${i.medicine_name}</strong><br><small class="text-muted">${i.generic_name}</small></td>
                            <td>${i.quantity} units</td>
                            <td>INR ${parseFloat(i.price_per_unit).toFixed(2)}</td>
                            <td class="text-end fw-semibold">INR ${parseFloat(i.total_price).toFixed(2)}</td>
                        </tr>
                    `;
                });
                html += `</tbody></table>`;
                box.innerHTML = html;
            })
            .catch(() => {
                box.innerHTML = `<div class="text-danger p-3">Failed to fetch invoice item details.</div>`;
            });
    }

    // Populate initial cart row on load
    window.addEventListener('DOMContentLoaded', () => {
        addCartRow();
    });
</script>
</body>
</html>
