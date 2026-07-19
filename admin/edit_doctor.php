<?php
session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if(!isset($_GET['id'])){
    header("Location: manage_doctors.php");
    exit();
}

$id = $_GET['id'];
$message = "";

// Fetch doctor
$stmt = $conn->prepare("SELECT * FROM doctors WHERE doctor_id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();

if(!$doctor){
    die("Doctor not found.");
}

// Fetch departments
$departments = $conn->query("SELECT * FROM departments");

// Update doctor
if(isset($_POST['update'])){

    $name = trim($_POST['full_name']);
    $department = intval($_POST['department']);
    $specialization = trim($_POST['specialization']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password'] ?? '');
    $experience = intval($_POST['experience']);
    $qualification = trim($_POST['qualification']);
    $fee = floatval($_POST['consultation_fee']);
    $days = trim($_POST['available_days']);
    $time = trim($_POST['available_time']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("
    UPDATE doctors SET
    full_name=?,
    department_id=?,
    specialization=?,
    phone=?,
    email=?,
    experience=?,
    qualification=?,
    consultation_fee=?,
    available_days=?,
    available_time=?,
    status=?
    WHERE doctor_id=?
    ");

    $stmt->bind_param(
        "sisssidssssi",
        $name,
        $department,
        $specialization,
        $phone,
        $email,
        $experience,
        $qualification,
        $fee,
        $days,
        $time,
        $status,
        $id
    );

    if($stmt->execute()){
        // Update user table password if provided
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $uStmt = $conn->prepare("UPDATE users SET password = ?, full_name = ? WHERE LOWER(email) = LOWER(?)");
            $uStmt->bind_param("sss", $hashed, $name, $email);
            $uStmt->execute();
        } else {
            $uStmt = $conn->prepare("UPDATE users SET full_name = ? WHERE LOWER(email) = LOWER(?)");
            $uStmt->bind_param("ss", $name, $email);
            $uStmt->execute();
        }

        header("Location: manage_doctors.php");
        exit();
    }else{
        $message = "Update Failed!";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<title>Edit Doctor</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-warning">

<h3>Edit Doctor</h3>

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
<input type="text" name="full_name" class="form-control" value="<?= $doctor['full_name']; ?>" required>
</div>

<div class="col-md-6 mb-3">
<label>Department</label>
<select name="department" class="form-select" required>

<?php while($row=($departments)->fetch_assoc()){ ?>

<option value="<?= $row['department_id']; ?>"
<?= ($doctor['department_id']==$row['department_id']) ? 'selected' : ''; ?>>

<?= $row['department_name']; ?>

</option>

<?php } ?>

</select>
</div>

<div class="col-md-6 mb-3">
<label>Specialization</label>
<input type="text" name="specialization" class="form-control" value="<?= $doctor['specialization']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Phone</label>
<input type="text" name="phone" class="form-control" value="<?= $doctor['phone']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" value="<?= $doctor['email']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>New Password (Leave blank to keep existing)</label>
<input type="password" name="password" class="form-control" placeholder="Enter new password to change">
</div>

<div class="col-md-6 mb-3">
<label>Experience (Years)</label>
<input type="number" name="experience" class="form-control" value="<?= $doctor['experience']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Qualification</label>
<input type="text" name="qualification" class="form-control" value="<?= $doctor['qualification']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Consultation Fee</label>
<input type="number" step="0.01" name="consultation_fee" class="form-control" value="<?= $doctor['consultation_fee']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Available Days</label>
<input type="text" name="available_days" class="form-control" value="<?= $doctor['available_days']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Available Time</label>
<input type="text" name="available_time" class="form-control" value="<?= $doctor['available_time']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Status</label>

<select name="status" class="form-select">

<option value="Active" <?= ($doctor['status']=="Active") ? "selected" : ""; ?>>
Active
</option>

<option value="Inactive" <?= ($doctor['status']=="Inactive") ? "selected" : ""; ?>>
Inactive
</option>

</select>

</div>

</div>

<button type="submit" name="update" class="btn btn-warning">
Update Doctor
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