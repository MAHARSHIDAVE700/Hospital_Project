<?php
session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// Add Doctor
if(isset($_POST['add_doctor'])){

    $name = $_POST['full_name'];
    $department = $_POST['department'];
    $specialization = $_POST['specialization'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $experience = $_POST['experience'];

    $stmt = $conn->prepare("INSERT INTO doctors(full_name,department_id,specialization,phone,email,experience) VALUES(?,?,?,?,?,?)");

    $stmt->bind_param("sisssi",$name,$department,$specialization,$phone,$email,$experience);

    if($stmt->execute()){
        $message="<div class='alert alert-success'>Doctor Added Successfully.</div>";
    }else{
        $message="<div class='alert alert-danger'>Failed to Add Doctor.</div>";
    }
}

$departments=$conn->query("SELECT * FROM departments");

$doctorList=$conn->query("
SELECT doctors.*,departments.department_name
FROM doctors
JOIN departments
ON doctors.department_id=departments.department_id
");
?>

<!DOCTYPE html>
<html>

<head>

<title>Manage Doctors</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-4">

<h2>Manage Doctors</h2>

<?= $message ?>

<div class="card shadow mb-4">

<div class="card-header bg-primary text-white">

Add Doctor

</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<input type="text"
name="full_name"
class="form-control"
placeholder="Doctor Name"
required>

</div>

<div class="col-md-6 mb-3">

<select
name="department"
class="form-select"
required>

<option value="">Select Department</option>

<?php while($row=($departments)->fetch_assoc()){ ?>

<option value="<?= $row['department_id']; ?>">

<?= $row['department_name']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">

<input
type="text"
name="specialization"
class="form-control"
placeholder="Specialization"
required>

</div>

<div class="col-md-6 mb-3">

<input
type="text"
name="phone"
class="form-control"
placeholder="Phone"
required>

</div>

<div class="col-md-6 mb-3">

<input
type="email"
name="email"
class="form-control"
placeholder="Email"
required>

</div>

<div class="col-md-6 mb-3">

<input
type="number"
name="experience"
class="form-control"
placeholder="Experience (Years)"
required>

</div>

</div>

<button
class="btn btn-primary"
name="add_doctor">

Add Doctor

</button>

</form>

</div>

</div>

<h3>Doctor List</h3>

<table class="table table-bordered">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Name</th>

<th>Department</th>

<th>Specialization</th>

<th>Phone</th>

<th>Email</th>

<th>Experience</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($doctor=($doctorList)->fetch_assoc()){ ?>

<tr>

<td><?= $doctor['doctor_id']; ?></td>

<td><?= $doctor['full_name']; ?></td>

<td><?= $doctor['department_name']; ?></td>

<td><?= $doctor['specialization']; ?></td>

<td><?= $doctor['phone']; ?></td>

<td><?= $doctor['email']; ?></td>

<td><?= $doctor['experience']; ?> Years</td>

<td>

<a href="edit_doctor.php?id=<?= $doctor['doctor_id']; ?>"
class="btn btn-success btn-sm">

Edit

</a>

<a href="delete_doctor.php?id=<?= $doctor['doctor_id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this doctor?');">

Delete

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<a href="dashboard.php" class="btn btn-secondary">

← Back to Dashboard

</a>

</div>

</body>

</html>
