<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

include "../includes/config.php";

$user_id = $_SESSION['user_id'];
$message = "";

// Update Profile
if(isset($_POST['update'])){

    $full_name = $_POST['full_name'];
    $email = $_POST['email'];

    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=? WHERE id=?");
    $stmt->bind_param("ssi",$full_name,$email,$user_id);

    if($stmt->execute()){
        $message = "<div class='alert alert-success'>Profile Updated Successfully.</div>";
    }else{
        $message = "<div class='alert alert-danger'>Update Failed.</div>";
    }
}

// Fetch User Data
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-info text-white">
<h3>My Profile</h3>
</div>

<div class="card-body">

<?= $message ?>

<form method="POST">

<div class="mb-3">
<label>Full Name</label>
<input type="text" name="full_name" class="form-control"
value="<?= $user['full_name']; ?>" required>
</div>

<div class="mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control"
value="<?= $user['email']; ?>" required>
</div>

<div class="mb-3">
<label>Role</label>
<input type="text" class="form-control"
value="<?= ucfirst($user['role']); ?>" readonly>
</div>

<button class="btn btn-primary" name="update">
Update Profile
</button>

<a href="dashboard.php" class="btn btn-secondary">
Back
</a>

</form>

</div>

</div>

</div>

</body>
</html>