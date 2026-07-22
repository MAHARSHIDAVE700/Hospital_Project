<?php
session_start();

if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['patient_id'];

// Get patient_id
$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");
$patient = $getPatient->fetch_assoc();
$patientID = $patient ? $patient['patient_id'] : 0;

$message = "";

// ------------------------------
// POST HANDLER: Confirm Razorpay Payment
// ------------------------------
if (isset($_POST['razorpay_payment_id']) && isset($_POST['invoice_id'])) {
    $paymentId = trim($_POST['razorpay_payment_id']);
    $invoice_id = intval($_POST['invoice_id']);
    
    $stmt = $conn->prepare("UPDATE invoices SET status = 'Paid', payment_method = 'Paid Online' WHERE invoice_id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $invoice_id, $patientID);
    if ($stmt->execute()) {
        ActivityLogger::log($userID, 'patient', 'Pay Invoice Online', "Paid invoice ID {$invoice_id} online via Razorpay. Txn ID: {$paymentId}");
        $message = "<div class='alert alert-success alert-dismissible fade show' role='alert'>
            <strong>Success:</strong> Payment checkout complete! Invoice marked Paid.
            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
        </div>";
    }
}

// Fetch Invoices
$invoices = $conn->query("
    SELECT * 
    FROM invoices
    WHERE patient_id = '$patientID'
    ORDER BY created_at DESC
");

// Stats metrics
$totalBillsCount = $invoices->num_rows;
$unpaidCount = $conn->query("SELECT COUNT(*) AS total FROM invoices WHERE patient_id='$patientID' AND status='Unpaid'")->fetch_assoc()['total'];
$outstandingAmount = $conn->query("SELECT SUM(total_amount) AS total FROM invoices WHERE patient_id='$patientID' AND status='Unpaid'")->fetch_assoc()['total'] ?? 0.00;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Invoices | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
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
            <a href="my_pharmacy_bills.php" class="hms-sidebar-item">
                <i class="bi bi-receipt"></i> Pharmacy Bills
            </a>
            <a href="my_bills.php" class="hms-sidebar-item active">
                <i class="bi bi-wallet2"></i> Invoices & Bills
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
                    <span class="hms-breadcrumb-item-active">My Invoices & Accounts</span>
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
            <?= $message ?>

            <!-- Statistics Banner -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Total Invoices</span>
                                <h3 class="fw-bold mb-0 text-dark"><?= $totalBillsCount ?></h3>
                            </div>
                            <div class="bg-primary-subtle text-primary p-3 rounded-3">
                                <i class="bi bi-receipt-cutoff fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Unpaid Bills</span>
                                <h3 class="fw-bold mb-0 text-danger"><?= $unpaidCount ?></h3>
                            </div>
                            <div class="bg-danger-subtle text-danger p-3 rounded-3">
                                <i class="bi bi-exclamation-circle fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4 rounded-4 bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold fs-7 uppercase d-block mb-1">Outstanding Balance</span>
                                <h3 class="fw-bold mb-0 text-warning">INR <?= number_format($outstandingAmount, 2) ?></h3>
                            </div>
                            <div class="bg-warning-subtle text-warning p-3 rounded-3">
                                <i class="bi bi-cash-stack fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoices Ledger -->
            <h3 class="mb-4">My Invoices History</h3>
            
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">Hospital Invoices Log</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Invoice #</th>
                                <th>Date Generated</th>
                                <th>Aggregated Items</th>
                                <th>Tax / Discount</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices->num_rows == 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No invoices generated for your account.</td>
                                </tr>
                            <?php else: ?>
                                <?php while ($row = $invoices->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-4 text-muted fw-semibold"><?= htmlspecialchars($row['invoice_number']) ?></td>
                                        <td><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                        <td class="small">
                                            OPD: ₹<?= number_format($row['opd_charges'], 0) ?> |
                                            IPD: ₹<?= number_format($row['ipd_charges'], 0) ?> |
                                            Lab: ₹<?= number_format($row['lab_charges'], 0) ?> |
                                            Phar: ₹<?= number_format($row['pharmacy_charges'], 0) ?>
                                        </td>
                                        <td class="small text-muted">
                                            Tax: +₹<?= number_format($row['tax_amount'], 0) ?><br>
                                            Disc: -₹<?= number_format($row['discount'], 0) ?>
                                        </td>
                                        <td class="fw-bold text-dark">INR <?= number_format($row['total_amount'], 2) ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Paid'): ?>
                                                <span class="badge bg-success-subtle text-success px-2.5 py-1.5 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>Settled (<?= htmlspecialchars($row['payment_method']) ?>)</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger px-2.5 py-1.5 rounded-pill"><i class="bi bi-clock-history me-1"></i>Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($row['status'] === 'Unpaid'): ?>
                                                <button type="button" class="btn btn-sm btn-success text-white rounded-pill px-3 me-2" onclick="payInvoice(<?= $row['invoice_id'] ?>, '<?= htmlspecialchars($row['invoice_number']) ?>', <?= $row['total_amount'] ?>)"><i class="bi bi-credit-card me-1"></i>Pay Online</button>
                                            <?php endif; ?>
                                            <a href="download_invoice.php?id=<?= $row['invoice_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3"><i class="bi bi-download me-1"></i>Receipt</a>
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

<!-- Razorpay form handler -->
<form method="POST" id="razorpayForm">
    <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
    <input type="hidden" name="invoice_id" id="payment_invoice_id">
</form>

<script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    function payInvoice(invoiceId, invoiceNumber, amount) {
        var options = {
            "key": "rzp_test_placeholderKey", // Replace with real Razorpay Key ID
            "amount": (amount * 100).toFixed(0), // Amount in paise
            "currency": "INR",
            "name": "Narayan Hospital",
            "description": "Hospital Invoice payment " + invoiceNumber,
            "image": "../assets/images/hospital.jpg",
            "handler": function (response){
                document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                document.getElementById('payment_invoice_id').value = invoiceId;
                document.getElementById('razorpayForm').submit();
            },
            "prefill": {
                "name": "<?= htmlspecialchars($_SESSION['patient_name']) ?>",
                "email": "patient@hospital.com", // Fallback email
                "contact": "0000000000" // Fallback phone
            },
            "theme": {
                "color": "#0d9488"
            }
        };
        var rzp1 = new Razorpay(options);
        rzp1.open();
    }
</script>
</body>
</html>
