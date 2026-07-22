<?php
session_start();

include "../includes/config.php";

// 1. JSON API endpoint to fetch detailed dispense items
if (isset($_GET['fetch_dispense_id'])) {
    if (!isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
        http_response_code(403);
        echo json_encode(["error" => "Unauthorized"]);
        exit();
    }
    
    $dispenseId = intval($_GET['fetch_dispense_id']);
    
    // Check permission if patient
    if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id'])) {
        $pUser = $_SESSION['patient_id'];
        $checkP = $conn->query("
            SELECT md.patient_id 
            FROM medicine_dispenses md
            JOIN patients p ON md.patient_id = p.patient_id
            WHERE p.user_id = '$pUser' AND md.dispense_id = $dispenseId
        ");
        if (!$checkP || $checkP->num_rows === 0) {
            http_response_code(403);
            echo json_encode(["error" => "Unauthorized access to invoice"]);
            exit();
        }
    }
    
    // Fetch items
    $query = "
        SELECT mdi.*, m.medicine_name, m.generic_name
        FROM medicine_dispense_items mdi
        JOIN medicines m ON mdi.medicine_id = m.medicine_id
        WHERE mdi.dispense_id = ?
        ORDER BY mdi.item_id ASC
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $dispenseId);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = $row;
    }
    header('Content-Type: application/json');
    echo json_encode($list);
    exit();
}

// 2. Regular HTML render view for patient
if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

$userID = $_SESSION['patient_id'];

// Get patient_id
$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patient = $getPatient->fetch_assoc();
$patientID = $patient ? $patient['patient_id'] : 0;

$invoices = $conn->query("
    SELECT * 
    FROM medicine_dispenses
    WHERE patient_id = '$patientID'
    ORDER BY dispense_date DESC
");

// Total metrics
$totalBillsCount = $invoices->num_rows;
$totalSpent = $conn->query("SELECT SUM(total_price) AS total FROM medicine_dispenses WHERE patient_id='$patientID' AND status='Dispensed'")->fetch_assoc()['total'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pharmacy Bills | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
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
            <a href="my_pharmacy_bills.php" class="hms-sidebar-item active">
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
                    <span class="hms-breadcrumb-item-active">My Medication Receipts</span>
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
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Invoices</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalBillsCount ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-receipt fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Medication Cost Paid</span>
                                <h3 class="fw-bold mb-0 text-success">INR <?= number_format($totalSpent, 2) ?></h3>
                            </div>
                            <div class="bg-success-subtle text-success p-3 rounded-3">
                                <i class="bi bi-wallet2 fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoices List -->
            <h3 class="mb-4">Medication Billing History</h3>
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">Pharmacy Dispense Logs</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Invoice ID</th>
                                <th>Dispense Date & Time</th>
                                <th>Amount Paid</th>
                                <th>Billing Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices->num_rows == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No pharmacy billing receipts found.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($row = $invoices->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 text-muted fw-semibold">#INV-PH-<?= $row['dispense_id'] ?></td>
                                        <td><?= date('d M Y, h:i A', strtotime($row['dispense_date'])) ?></td>
                                        <td class="fw-semibold text-teal">INR <?= number_format($row['total_price'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Settled Receipt</span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <button class="btn btn-sm btn-outline-primary px-3 rounded-pill" onclick="viewInvoiceItems(<?= $row['dispense_id'] ?>)"><i class="bi bi-eye"></i> View Medication List</button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Details Modal -->
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    function viewInvoiceItems(dispenseId) {
        var detailsModal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));
        detailsModal.show();

        const box = document.getElementById('invoiceItemsContent');
        box.innerHTML = `<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>`;

        fetch(`my_pharmacy_bills.php?fetch_dispense_id=${dispenseId}`)
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
</script>
</body>
</html>
