<?php
session_start();
include "../includes/config.php";

$message = "";

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role='patient'");
    $stmt->bind_param("s",$email);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows == 1){

        $patient = $result->fetch_assoc();

        if(password_verify($password,$patient['password'])){

            $_SESSION['patient_id'] = $patient['id'];
            $_SESSION['patient_name'] = $patient['full_name'];

            ActivityLogger::log($patient['id'], 'patient', 'Login', 'Patient logged in successfully');

            header("Location: dashboard.php");
            exit();

        }else{

            $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Wrong Password</div>";

        }

    }else{

        $message = "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> Patient Not Found</div>";

    }
}

if (isset($_GET['registered'])) {
    $message = "<div class='alert alert-success'><i class='bi bi-check-circle-fill'></i> Account created successfully! A verification email has been logged in <code>email_log.txt</code> for local testing.</div>";
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
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">🏥</div>
        <h2 class="login-title">Smart Hospital</h2>
        <p class="login-subtitle">Patient Login Portal</p>
        
        <?php
        if (!empty($message)) {
            echo $message;
        }
        ?>
        
        <form method="POST" class="login-form">
            <div class="form-group mb-3">
                <label><i class="bi bi-envelope-fill"></i> Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter Email" required>
            </div>
            
            <div class="form-group mb-4">
                <label><i class="bi bi-lock-fill"></i> Password</label>
                <div class="input-group">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter Password" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="d-flex justify-content-end mb-3">
                <a href="../forgot_password.php" class="text-decoration-none small text-muted"><i class="bi bi-question-circle"></i> Forgot Password?</a>
            </div>
            
            <button type="submit" name="login" class="btn-login-submit">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>
        
        <div class="login-footer-actions">
            <h6>New Patient?</h6>
            <a href="register.php" class="btn btn-primary w-100 mb-2 btn-link-action" style="background-color: var(--primary-color); border: none;">
                <i class="bi bi-person-plus-fill"></i> Create New Account
            </a>
            <a href="../index.php" class="btn btn-outline-success w-100 btn-link-action" style="color: var(--success-color); border-color: var(--success-color);">
                <i class="bi bi-house-fill"></i> Back to Home
            </a>
        </div>
        
        <div class="login-developer-footer">
            Developed by Maharshi Dave
        </div>
    </div>
</div>

<script>
function togglePassword(){
    let password = document.getElementById("password");
    if(password.type === "password"){
        password.type = "text";
    }else{
        password.type = "password";
    }
}
</script>

</body>
</html>
