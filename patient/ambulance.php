<?php
session_start();
include "../includes/config.php";

$message = "";

if (isset($_POST['request_ambulance'])) {
    $patientName = trim($_POST['patient_name']);
    $phone = trim($_POST['phone']);
    $pickupAddress = trim($_POST['pickup_address']);

    $stmt = $conn->prepare("INSERT INTO ambulance_requests (patient_name, phone, pickup_address, status) VALUES (?, ?, ?, 'Requested')");
    $stmt->bind_param("sss", $patientName, $phone, $pickupAddress);

    if ($stmt->execute()) {
        $message = "
            <div class='alert alert-danger p-4 shadow-sm border-2 border-danger rounded-3'>
                <h4 class='alert-heading fw-bold'><i class='bi bi-exclamation-octagon-fill'></i> Emergency Ambulance Dispatched!</h4>
                <p class='mb-1'>Your request has been logged. Our emergency response team will contact you at <strong>" . htmlspecialchars($phone) . "</strong> immediately.</p>
                <hr>
                <p class='mb-0 fw-bold'>Emergency Hotline: +91 8140150700</p>
            </div>
        ";
    } else {
        $message = "<div class='alert alert-danger'>Failed to process emergency request.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Emergency Ambulance Request | Narayan Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-danger rounded-4 p-4">
                <div class="text-center mb-3">
                    <div class="fs-1 text-danger">🚨</div>
                    <h2 class="fw-bold text-danger">24x7 Ambulance Dispatch</h2>
                    <p class="text-muted">Instant emergency pickup request</p>
                </div>

                <?= $message; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient / Contact Person Name</label>
                        <input type="text" name="patient_name" class="form-control form-control-lg" placeholder="Enter Full Name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Emergency Contact Phone</label>
                        <input type="tel" name="phone" class="form-control form-control-lg" placeholder="Enter Phone Number" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Full Pickup Address &amp; Landmark</label>
                        <textarea name="pickup_address" class="form-control" rows="3" placeholder="Enter precise location..." required></textarea>
                    </div>

                    <button type="submit" name="request_ambulance" class="btn btn-danger btn-lg w-100 fw-bold py-3">
                        🚨 REQUEST AMBULANCE NOW
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="../index.php" class="btn btn-outline-secondary">← Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
