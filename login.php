<?php
session_start();
include "includes/config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email=?");
    $stmt->bind_param("s",$email);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows == 1){

        $user = $result->fetch_assoc();

        if(password_verify($password,$user['password'])){

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];

            header("Location: patient/dashboard.php");
            exit();

        }else{

            $message = "Incorrect Password";

        }

    }else{

        $message = "Email Not Found";

    }

}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">🏥</div>
        <h2 class="login-title">Smart Hospital</h2>
        <p class="login-subtitle">Patient Quick Portal Login</p>
        
        <?php
        if ($message != "") {
            echo "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> " . htmlspecialchars($message) . "</div>";
        }
        ?>
        
        <form method="POST" class="login-form">
            <div class="form-group mb-3">
                <label><i class="bi bi-envelope-fill"></i> Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter Email" required>
            </div>
            
            <div class="form-group mb-4">
                <label><i class="bi bi-lock-fill"></i> Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter Password" required>
            </div>
            
            <button type="submit" class="btn-login-submit">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>
        
        <div class="login-footer-actions">
            <h6>New Patient?</h6>
            <a href="register.php" class="btn btn-primary w-100 mb-2 btn-link-action" style="background-color: var(--primary-color); border: none;">
                <i class="bi bi-person-plus-fill"></i> Create New Account
            </a>
            <a href="index.php" class="btn btn-outline-success w-100 btn-link-action" style="color: var(--success-color); border-color: var(--success-color);">
                <i class="bi bi-house-fill"></i> Back to Home
            </a>
        </div>
    </div>
</div>

</body>
</html>
