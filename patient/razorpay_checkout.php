<?php
session_start();
if (!isset($_SESSION['patient_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

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

// Handle payment confirmation POST
if (isset($_POST['razorpay_payment_id'])) {
    $paymentId = trim($_POST['razorpay_payment_id']);
    
    $stmt = $conn->prepare("UPDATE appointments SET fee_status = 'Paid Online', opd_fee_paid = 200.00 WHERE appointment_id = ?");
    $stmt->bind_param("i", $apptId);
    if ($stmt->execute()) {
        header("Location: token_card.php?id=" . $apptId . "&paid=1");
        exit();
    }
}
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
        <p class="text-muted">Narayan Hospital Online Payment Desk</p>
        <hr>

        <div class="text-start mb-4 bg-light p-3 rounded">
            <p class="mb-1"><strong>Patient:</strong> <?= htmlspecialchars($appt['patient_name']); ?></p>
            <p class="mb-1"><strong>Doctor:</strong> Dr. <?= htmlspecialchars($appt['doctor_name']); ?></p>
            <p class="mb-1"><strong>Date:</strong> <?= htmlspecialchars($appt['appointment_date']); ?> at <?= htmlspecialchars($appt['appointment_time']); ?></p>
            <p class="mb-0"><strong>Amount Payable:</strong> <span class="text-success fw-bold fs-5">₹200.00</span></p>
        </div>

        <form method="POST" id="razorpayForm">
            <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
            <button type="button" id="payBtn" class="btn btn-success btn-lg w-100 py-3 font-weight-bold">
                🔒 Pay ₹200 via Razorpay
            </button>
        </form>

        <a href="token_card.php?id=<?= $apptId ?>" class="btn btn-link text-muted mt-3">Skip & Pay at Counter</a>
    </div>
</div>

<script>
document.getElementById('payBtn').onclick = function(e){
    var options = {
        "key": "rzp_test_placeholderKey", // Replace with real Razorpay Key ID
        "amount": "20000", // Amount in paise (200 INR)
        "currency": "INR",
        "name": "Narayan Hospital",
        "description": "OPD Consultation Fee #<?= $apptId ?>",
        "image": "../assets/images/hospital.jpg",
        "handler": function (response){
            document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
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
