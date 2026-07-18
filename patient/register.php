<?php
session_start();
include "../includes/config.php";

$message = "";

if (isset($_POST['register'])) {

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone']);
    $gender = $_POST['gender'];
    $age = intval($_POST['age']);
    $address = trim($_POST['address']);

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $message = "<div class='alert alert-danger'>Email already registered.</div>";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'patient') RETURNING id");
        $stmt->bind_param("sss", $full_name, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            $user_row = $stmt->get_result()->fetch_assoc();
            $user_id = $user_row['id'];

            // Insert into patients
            $stmt2 = $conn->prepare("INSERT INTO patients (user_id, phone, gender, age, address) VALUES (?, ?, ?, ?, ?)");
            $stmt2->bind_param("issis", $user_id, $phone, $gender, $age, $address);

            if ($stmt2->execute()) {
                header("Location: login.php");
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Patient profile registration failed.</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>User account registration failed.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Patient Registration | Smart Hospital</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

body{

background:linear-gradient(135deg,#0d6efd,#20c997);

min-height:100vh;

display:flex;

justify-content:center;

align-items:center;

padding:30px;

font-family:'Segoe UI',sans-serif;

}

.register-card{

width:650px;

background:#fff;

border-radius:20px;

padding:35px;

box-shadow:0 20px 50px rgba(0,0,0,.25);

animation:fadeIn .8s;

}

@keyframes fadeIn{

from{

opacity:0;

transform:translateY(30px);

}

to{

opacity:1;

transform:translateY(0);

}

}

.logo{

font-size:60px;

text-align:center;

}

.title{

text-align:center;

font-weight:bold;

color:#198754;

}

.subtitle{

text-align:center;

color:#777;

margin-bottom:30px;

}

.form-control,
.form-select{

height:50px;

border-radius:10px;

}

textarea.form-control{

height:100px;

}

.btn-register{

height:50px;

font-size:18px;

font-weight:bold;

border-radius:10px;

}

.footer{

text-align:center;

margin-top:20px;

color:gray;

font-size:14px;

}

</style>

</head>

<body>

<div class="register-card">

<div class="logo">🏥</div>

<h2 class="title">

Smart Hospital

</h2>

<p class="subtitle">

Create Your Patient Account

</p>

<?= $message ?>

<form method="POST">
    <div class="row">

<div class="col-md-6 mb-3">

<label class="form-label">
<i class="bi bi-person-fill"></i>
Full Name
</label>

<input
type="text"
name="full_name"
class="form-control"
placeholder="Enter Full Name"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">
<i class="bi bi-envelope-fill"></i>
Email Address
</label>

<input
type="email"
name="email"
class="form-control"
placeholder="Enter Email"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">
<i class="bi bi-lock-fill"></i>
Password
</label>

<div class="input-group">

<input
type="password"
name="password"
id="password"
class="form-control"
placeholder="Create Password"
required>

<button
class="btn btn-outline-secondary"
type="button"
onclick="togglePassword()">

<i class="bi bi-eye"></i>

</button>

</div>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">
<i class="bi bi-telephone-fill"></i>
Phone Number
</label>

<input
type="text"
name="phone"
class="form-control"
placeholder="Enter Phone Number"
required>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">
<i class="bi bi-gender-ambiguous"></i>
Gender
</label>

<select
name="gender"
class="form-select"
required>

<option value="">
Select Gender
</option>

<option value="Male">
Male
</option>

<option value="Female">
Female
</option>

<option value="Other">
Other
</option>

</select>

</div>

<div class="col-md-6 mb-3">

<label class="form-label">
<i class="bi bi-calendar-event-fill"></i>
Age
</label>

<input
type="number"
name="age"
class="form-control"
placeholder="Enter Age"
required>

</div>

<div class="col-12 mb-3">

<label class="form-label">
<i class="bi bi-geo-alt-fill"></i>
Address
</label>

<textarea
name="address"
class="form-control"
placeholder="Enter Your Address"
required></textarea>

</div>

<div class="col-12">

<button
type="submit"
name="register"
class="btn btn-success btn-register w-100">

<i class="bi bi-person-plus-fill"></i>

Create Account

</button>

</div>

</div>
<hr>

<div class="text-center mt-3">

<h6>Already have an account?</h6>

<a href="login.php" class="btn btn-primary w-100 mb-2">

<i class="bi bi-box-arrow-in-right"></i>

Login Here

</a>

<a href="../index.php" class="btn btn-outline-success w-100">

<i class="bi bi-house-fill"></i>

Back to Home

</a>

</div>

<div class="footer">

Developed by Maharshi Dave

</div>

</form>

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