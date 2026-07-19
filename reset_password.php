<?php
session_start();
include "includes/config.php";

$message = "";
$validToken = false;
$token = trim($_GET['token'] ?? '');

if (!empty($token)) {
    $stmt = $conn->prepare("SELECT id, full_name, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (strtotime($user['reset_expires']) > time()) {
            $validToken = true;
        } else {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> This password reset link has expired. Please request a new one.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Invalid password reset token.</div>";
    }
} else {
    $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> No reset token provided.</div>";
}

if ($validToken && isset($_POST['reset_password'])) {
    $newPassword = trim($_POST['password']);
    $confirmPassword = trim($_POST['confirm_password']);
    
    if (strlen($newPassword) < 6) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Password must be at least 6 characters long.</div>";
    } elseif ($newPassword !== $confirmPassword) {
        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Passwords do not match.</div>";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
        $updateStmt->bind_param("ss", $hashedPassword, $token);
        
        if ($updateStmt->execute()) {
            $message = "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> Password has been reset successfully! You can now log in with your new password.</div>";
            $validToken = false;
        } else {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Failed to update password. Please try again.</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">🔒</div>
        <h2 class="login-title">Set New Password</h2>
        <p class="login-subtitle">Enter your new account password below</p>
        
        <?php if (!empty($message)) echo $message; ?>
        
        <?php if ($validToken): ?>
        <form method="POST" class="login-form">
            <div class="form-group mb-3">
                <label><i class="bi bi-lock-fill"></i> New Password</label>
                <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
            </div>
            
            <div class="form-group mb-4">
                <label><i class="bi bi-shield-lock-fill"></i> Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
            </div>
            
            <button type="submit" name="reset_password" class="btn-login-submit mb-3">
                <i class="bi bi-check2-circle"></i> Update Password
            </button>
        </form>
        <?php endif; ?>
        
        <div class="login-footer-actions">
            <a href="patient/login.php" class="btn btn-outline-primary w-100 mb-2 btn-link-action">
                <i class="bi bi-box-arrow-in-right"></i> Go to Patient Login
            </a>
            <a href="doctor/login.php" class="btn btn-outline-info w-100 mb-2 btn-link-action">
                <i class="bi bi-person-badge"></i> Go to Doctor Login
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
