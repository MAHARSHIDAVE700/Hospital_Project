<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// Fetch Departments
$departments = $conn->query("SELECT * FROM departments");

// Save Doctor
if(isset($_POST['save'])){

    $full_name = trim($_POST['full_name']);
    $department_id = intval($_POST['department_id']);
    $specialization = trim($_POST['specialization']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $experience = intval($_POST['experience']);
    $qualification = trim($_POST['qualification']);
    $consultation_fee = floatval($_POST['consultation_fee']);
    $available_days = trim($_POST['available_days']);
    $available_time = trim($_POST['available_time']);
    $status = $_POST['status'];

    if (empty($email) || empty($password)) {
        $message = "Email and Password are required for doctor account creation!";
    } else {
        // Check if user account already exists
        $userCheck = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $userCheck->bind_param("s", $email);
        $userCheck->execute();
        $userResult = $userCheck->get_result();

        if ($userResult && $userResult->num_rows > 0) {
            // Update password & role if user already exists
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $updateUser = $conn->prepare("UPDATE users SET password = ?, role = 'doctor', full_name = ? WHERE email = ?");
            $updateUser->bind_param("sss", $hashed_password, $full_name, $email);
            $updateUser->execute();
        } else {
            // Create user account for doctor login
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'doctor';
            $userStmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
            $userStmt->bind_param("ssss", $full_name, $email, $hashed_password, $role);
            $userStmt->execute();
        }

        // Insert into doctors table
        $stmt = $conn->prepare("INSERT INTO doctors
        (full_name,department_id,specialization,phone,email,experience,qualification,consultation_fee,available_days,available_time,status)
        VALUES(?,?,?,?,?,?,?,?,?,?,?)");

        $stmt->bind_param(
            "sisssisssss",
            $full_name,
            $department_id,
            $specialization,
            $phone,
            $email,
            $experience,
            $qualification,
            $consultation_fee,
            $available_days,
            $available_time,
            $status
        );

        if($stmt->execute()){
            header("Location: manage_doctors.php");
            exit();
        }else{
            $message = "Error inserting doctor record: ".$conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Add Doctor</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>Add Doctor</h3>

</div>

<div class="card-body">

<?php
if($message!=""){
    echo "<div class='alert alert-danger'>$message</div>";
}
?>

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<label>Full Name</label>
<input type="text" name="full_name" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label>Department</label>

<select name="department_id" class="form-select" required>

<option value="">Select Department</option>

<?php
while($row=($departments)->fetch_assoc()){
?>
<option value="<?= $row['department_id']; ?>">
<?= $row['department_name']; ?>
</option>
<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">
<label>Specialization</label>
<input type="text" name="specialization" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Phone</label>
<input type="text" name="phone" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label>Account Login Password</label>
<input type="password" name="password" class="form-control" placeholder="Enter password for doctor login" required>
</div>

<div class="col-md-6 mb-3">
<label>Experience (Years)</label>
<input type="number" name="experience" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Qualification</label>
<input type="text" name="qualification" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Consultation Fee</label>
<input type="number" step="0.01" name="consultation_fee" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Available Days</label>
<input type="text" name="available_days" class="form-control" placeholder="Mon-Fri">
</div>

<div class="col-md-6 mb-3">
<label>Available Time</label>
<input type="text" name="available_time" class="form-control" placeholder="10:00 AM - 5:00 PM">
</div>

<div class="col-md-6 mb-3">
<label>Status</label>

<select name="status" class="form-select">

<option value="Active">Active</option>
<option value="Inactive">Inactive</option>

</select>

</div>

</div>

<button class="btn btn-success" name="save">
Save Doctor
</button>

<a href="manage_doctors.php" class="btn btn-secondary">
Back
</a>

</form>

</div>

</div>

</div>

</body>
</html>
