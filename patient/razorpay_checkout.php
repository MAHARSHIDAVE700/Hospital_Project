<?php
session_start();
if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";
require_once "../includes/razorpay_helper.php";

$apptId = intval($_GET['id'] ?? 0);
if (!$apptId) {
    die("Invalid appointment ID");
}

$query = "
    SELECT a.*, u.full_name AS patient_name, u.email, p.phone, d.full_name AS doctor_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.patient_id
    JOIN users u ON p.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.appointment_id = $apptId
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Appointment record not found.");
}

$appt = $res->fetch_assoc();
$errorMsg = "";

// Handle payment confirmation POST with server-side signature verification
if (isset($_POST['razorpay_payment_id'], $_POST['razorpay_order_id'], $_POST['razorpay_signature'])) {
    $paymentId = trim($_POST['razorpay_payment_id']);
    $orderId   = trim($_POST['razorpay_order_id']);
    $signature = trim($_POST['razorpay_signature']);
    
    if (RazorpayHelper::verifySignature($orderId, $paymentId, $signature)) {
        $stmt = $conn->prepare("UPDATE appointments SET fee_status = 'Paid Online', opd_fee_paid = 200.00, razorpay_order_id = ?, razorpay_payment_id = ? WHERE appointment_id = ?");
        $stmt->bind_param("ssi", $orderId, $paymentId, $apptId);
        if ($stmt->execute()) {
            ActivityLogger::log($_SESSION['patient_id'], 'patient', 'Pay OPD Fee', "Paid OPD fee online via Razorpay (Order: {$orderId}, Payment: {$paymentId})");
            header("Location: token_card.php?id=" . $apptId . "&paid=1");
            exit();
        }
    } else {
        $errorMsg = "Payment verification failed. Invalid transaction signature.";
    }
}

// Generate server-side Razorpay Order
$razorpayKeyId = RazorpayHelper::getKeyId();
$orderResult = RazorpayHelper::createOrder("APPT-" . $apptId, 200.00);
$razorpayOrderId = $orderResult['order_id'];

// Save order ID for verification
$conn->query("UPDATE appointments SET razorpay_order_id = '$razorpayOrderId' WHERE appointment_id = $apptId");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Razorpay OPD Payment | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        body { background: #f4f6f9; font-family: 'Segoe UI', sans-serif; }
        .pay-card { max-width: 500px; margin: 60px auto; background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="container">
    <div class="pay-card text-center">
        <div class="fs-1 text-primary mb-2">💳</div>
        <h3 class="fw-bold">OPD Fee Payment</h3>
        <p class="text-muted">Narayan Hospital Online Payment Desk (Razorpay Test Mode)</p>
        <hr>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Error:</strong> <?= htmlspecialchars($errorMsg); ?>
            </div>
        <?php endif; ?>

        <div class="text-start mb-4 bg-light p-3 rounded">
            <p class="mb-1"><strong>Patient:</strong> <?= htmlspecialchars($appt['patient_name']); ?></p>
            <p class="mb-1"><strong>Doctor:</strong> Dr. <?= htmlspecialchars($appt['doctor_name']); ?></p>
            <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars($appt['appointment_date']); ?> at <?= htmlspecialchars($appt['appointment_time']); ?></p>
            <p class="mb-0"><strong>Amount Payable:</strong> <span class="text-success fw-bold fs-5">₹200.00</span></p>
        </div>

        <form method="POST" id="razorpayForm">
            <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
            <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
            <input type="hidden" name="razorpay_signature" id="razorpay_signature">
            <button type="button" id="payBtn" class="btn btn-success btn-lg w-100 py-3 font-weight-bold">
                🔒 Pay ₹200 via Razorpay (Test Mode)
            </button>
        </form>

        <a href="token_card.php?id=<?= $apptId ?>" class="btn btn-link text-muted mt-3">Skip & Pay at Counter</a>
    </div>
</div>

<script>
document.getElementById('payBtn').onclick = function(e){
    var options = {
        "key": "<?= htmlspecialchars($razorpayKeyId) ?>",
        "amount": "20000",
        "currency": "INR",
        "name": "Narayan Hospital",
        "description": "OPD Consultation Fee #<?= $apptId ?>",
        "order_id": "<?= htmlspecialchars($razorpayOrderId) ?>",
        "handler": function (response){
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
            document.getElementById('razorpay_order_id').value = response.razorpay_order_id || "<?= htmlspecialchars($razorpayOrderId) ?>";
            document.getElementById('razorpay_signature').value = response.razorpay_signature || "simulated_test_sig";
            document.getElementById('razorpayForm').submit();
        },
        "prefill": {
            "name": "<?= htmlspecialchars($appt['patient_name']) ?>",
            "email": "<?= htmlspecialchars($appt['email']) ?>",
            "contact": "<?= htmlspecialchars($appt['phone']) ?>"
        },
        "theme": {
            "color": "#0d6efd"
        }
    };
    var rzp1 = new Razorpay(options);
    rzp1.open();
    e.preventDefault();
}
</script>

</body>
</html>
