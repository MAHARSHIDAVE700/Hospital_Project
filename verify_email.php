<?php
session_start();
include "includes/config.php";

$message = "";
$status = "error";
$token = trim($_GET['token'] ?? '');

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, full_name, is_email_verified FROM users WHERE email_verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if ($user['is_email_verified'] == 1) {
            $message = "Your email address is already verified! You can log in to your account.";
            $status = "info";
        } else {
            $updateStmt = $conn->prepare("UPDATE users SET is_email_verified = 1, email_verification_token = NULL WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            if ($updateStmt->execute()) {
                $message = "Congratulations, " . htmlspecialchars($user['full_name']) . "! Your email address has been successfully verified.";
                $status = "success";
            } else {
                $message = "Failed to update verification status. Please try again.";
            }
        }
    } else {
        $message = "Invalid or expired verification link.";
    }
} else {
    $message = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card text-center">
        <?php if ($status === 'success'): ?>
            <div class="login-logo text-success">✅</div>
            <h2 class="login-title text-success">Email Verified!</h2>
        <?php elseif ($status === 'info'): ?>
            <div class="login-logo text-info">ℹ️</div>
            <h2 class="login-title text-info">Already Verified</h2>
        <?php else: ?>
            <div class="login-logo text-danger">❌</div>
            <h2 class="login-title text-danger">Verification Failed</h2>
        <?php endif; ?>
        
        <p class="login-subtitle mt-3"><?= htmlspecialchars($message); ?></p>
        
        <div class="login-footer-actions mt-4">
            <a href="patient/login.php" class="btn btn-primary w-100 mb-2 btn-link-action">
                <i class="bi bi-box-arrow-in-right"></i> Proceed to Patient Login
            </a>
            <a href="index.php" class="btn btn-outline-secondary w-100 btn-link-action">
                <i class="bi bi-house-fill"></i> Back to Home
            </a>
        </div>
        
        <div class="login-developer-footer">
            Developed by Maharshi Dave
        </div>
    </div>
</div>

</body>
</html>
