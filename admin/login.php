<?php
session_start();
include "../includes/config.php";

$message = "";

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND role='admin'");
    $stmt->bind_param("s",$email);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows==1){

        $admin = $result->fetch_assoc();

        if(password_verify($password,$admin['password'])){

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];

            header("Location: dashboard.php");
            exit();

        }else{

            $message = "Wrong Password";

        }

    }else{

        $message = "Admin Not Found";

    }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Login | Smart Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">🛡️</div>
        <h2 class="login-title">Administrator Portal</h2>
        <p class="login-subtitle">Smart Hospital Management System</p>
        
        <?php
        if($message!=""){
            echo "<div class='alert alert-danger'><i class='bi bi-exclamation-triangle-fill'></i> " . htmlspecialchars($message) . "</div>";
        }
        ?>
        
        <form method="POST" class="login-form">
            <div class="form-group mb-3">
                <label><i class="bi bi-envelope-fill"></i> Admin Email</label>
                <input type="email" name="email" class="form-control" placeholder="Enter Admin Email" required>
            </div>
            
            <div class="form-group mb-4">
                <label><i class="bi bi-lock-fill"></i> Password</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter Password" required>
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="d-flex justify-content-end mb-3">
                <a href="../forgot_password.php" class="text-decoration-none small text-muted"><i class="bi bi-question-circle"></i> Forgot Password?</a>
            </div>
            
            <button type="submit" name="login" class="btn-login-submit" style="background-color: var(--dark-color) !important;">
                <i class="bi bi-box-arrow-in-right"></i> Login
            </button>
        </form>
        
        <div class="login-footer-actions">
            <a href="../index.php" class="btn btn-outline-dark w-100 btn-link-action">
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
    let x=document.getElementById("password");
    if(x.type==="password"){
        x.type="text";
    }else{
        x.type="password";
    }
}
</script>

</body>
</html>