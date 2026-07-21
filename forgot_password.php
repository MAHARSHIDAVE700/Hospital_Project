<?php
session_start();
include "includes/config.php";
include "includes/email_helper.php";

$message = "";

if (isset($_POST['request_reset'])) {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $updateStmt->bind_param("ssi", $token, $expires, $user['id']);
        
        if ($updateStmt->execute()) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $domain = $_SERVER['HTTP_HOST'];
            $resetUrl = $protocol . $domain . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
            
            EmailHelper::sendPasswordResetLink($email, $user['full_name'], $resetUrl);
            
            $message = "
                <div class='alert alert-success'>
                    <i class='bi bi-check-circle-fill'></i> Password reset link has been generated!
                    <div class='mt-2 small text-muted'>
                        If using local XAMPP without configured SMTP, check <code>email_log.txt</code> in your project root or click the link below:<br>
                        <a href='{$resetUrl}' class='fw-bold text-success'>Direct Reset Link</a>
                    </div>
                </div>
            ";
        } else {
            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Failed to process request. Please try again.</div>";
        }
    } else {
        // Generic message for security
        $message = "<div class='alert alert-info'><i class='bi bi-info-circle-fill'></i> If an account exists with that email, a password reset link has been sent.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">🔑</div>
        <h2 class="login-title">Forgot Password</h2>
        <p class="login-subtitle">Enter your registered email to receive a reset link</p>
        
        <?php if (!empty($message)) echo $message; ?>
        
        <form method="POST" class="login-form">
            <div class="form-group mb-4">
                <label><i class="bi bi-envelope-fill"></i> Registered Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your email address" required>
            </div>
            
            <button type="submit" name="request_reset" class="btn-login-submit mb-3">
                <i class="bi bi-send-fill"></i> Send Reset Link
            </button>
        </form>
        
        <div class="login-footer-actions">
            <a href="patient/login.php" class="btn btn-outline-primary w-100 mb-2 btn-link-action">
                <i class="bi bi-arrow-left"></i> Back to Patient Login
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
