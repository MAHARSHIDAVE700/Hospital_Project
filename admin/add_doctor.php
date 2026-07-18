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

    $full_name = $_POST['full_name'];
    $department_id = $_POST['department_id'];
    $specialization = $_POST['specialization'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $experience = $_POST['experience'];
    $qualification = $_POST['qualification'];
    $consultation_fee = $_POST['consultation_fee'];
    $available_days = $_POST['available_days'];
    $available_time = $_POST['available_time'];
    $status = $_POST['status'];

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
        $message = "Error: ".$conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Add Doctor</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
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
<input type="email" name="email" class="form-control">
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