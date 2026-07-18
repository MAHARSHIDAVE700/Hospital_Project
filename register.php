<?php
include "includes/config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    // Validation
    if ($password != $confirm_password) {
        $message = "<div class='alert alert-danger'>Passwords do not match.</div>";
    } else {

        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {

            $message = "<div class='alert alert-danger'>Email already registered.</div>";

        } else {

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name,email,password) VALUES (?,?,?)");
            $stmt->bind_param("sss",$full_name,$email,$hashedPassword);

            if($stmt->execute()){

                header("Location: login.php");
                exit();

            }else{

                $message="<div class='alert alert-danger'>Registration Failed.</div>";

            }

            $stmt->close();

        }

        $check->close();
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Patient Registration</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="row justify-content-center">

<div class="col-md-6">

<div class="card shadow">

<div class="card-header bg-primary text-white text-center">

<h3>Create Patient Account</h3>

</div>

<div class="card-body">

<?php echo $message; ?>

<form method="POST">

<div class="mb-3">

<label class="form-label">Full Name</label>

<input type="text"
class="form-control"
name="full_name"
required>

</div>

<div class="mb-3">

<label class="form-label">Email</label>

<input type="email"
class="form-control"
name="email"
required>

</div>

<div class="mb-3">

<label class="form-label">Password</label>

<input type="password"
class="form-control"
name="password"
required>

</div>

<div class="mb-3">

<label class="form-label">Confirm Password</label>

<input type="password"
class="form-control"
name="confirm_password"
required>

</div>

<button
class="btn btn-primary w-100">

Register

</button>

</form>

<div class="text-center mt-3">

Already have an account?

<a href="login.php">

Login

</a>

</div>

</div>

</div>

</div>

</div>

</div>

</body>

</html>