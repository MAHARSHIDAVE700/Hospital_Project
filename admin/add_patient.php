<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";
$success_message = "";

if (isset($_POST['save'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $age = intval($_POST['age']);
    $address = trim($_POST['address']);

    if (empty($full_name) || empty($email) || empty($password) || empty($phone) || empty($gender) || empty($age)) {
        $message = "All fields except address are required.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $res = $check->get_result();

        if ($res && $res->num_rows > 0) {
            $message = "Email is already registered.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, is_email_verified) VALUES (?, ?, ?, 'patient', 1) RETURNING id");
            $stmt->bind_param("sss", $full_name, $email, $hashedPassword);

            if ($stmt->execute()) {
                $user_row = $stmt->get_result()->fetch_assoc();
                $user_id = $user_row['id'];

                // Insert into patients
                $stmt2 = $conn->prepare("INSERT INTO patients (user_id, phone, gender, age, address) VALUES (?, ?, ?, ?, ?)");
                $stmt2->bind_param("issis", $user_id, $phone, $gender, $age, $address);

                if ($stmt2->execute()) {
                    header("Location: manage_patients.php?added=1");
                    exit();
                } else {
                    $message = "Failed to create patient profile in patients table.";
                }
            } else {
                $message = "Failed to create user account.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Patient | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow border-0" style="border-radius: 16px;">
        <div class="card-header bg-primary text-white py-3" style="border-top-left-radius: 16px; border-top-right-radius: 16px;">
            <h3 class="mb-0"><i class="bi bi-person-plus-fill"></i> Add Patient</h3>
        </div>
        <div class="card-body p-4">

            <?php if ($message != "") { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter full name">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="patient@example.com">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Password (for Portal Login)</label>
                        <input type="text" name="password" class="form-control" required value="Patient@123" placeholder="Enter portal password">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" class="form-control" required placeholder="Enter phone number">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Age</label>
                        <input type="number" name="age" class="form-control" required placeholder="Enter age" min="1" max="120">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Gender</label>
                        <select name="gender" class="form-select" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="col-12 mb-4">
                        <label class="form-label fw-semibold">Residential Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Enter patient's full address"></textarea>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" name="save" class="btn btn-success px-4 py-2 fw-semibold">
                        <i class="bi bi-check-circle"></i> Save Patient
                    </button>
                    <a href="manage_patients.php" class="btn btn-secondary px-4 py-2">
                        Cancel
                    </a>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
