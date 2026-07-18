<?php
session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if(!isset($_GET['id'])){
    header("Location: manage_patients.php");
    exit();
}

$id = $_GET['id'];

// Patient Details
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

// Appointment History
$stmt = $conn->prepare("
SELECT
appointments.*,
doctors.full_name
FROM appointments
LEFT JOIN doctors
ON appointments.doctor_id = doctors.doctor_id
WHERE patient_id=?
ORDER BY appointment_date DESC
");

$stmt->bind_param("i",$id);
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html>

<head>

<title>Patient Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>Patient Details</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="30%">Patient ID</th>

<td><?= $patient['id']; ?></td>

</tr>

<tr>

<th>Name</th>

<td><?= htmlspecialchars($patient['full_name']); ?></td>

</tr>

<tr>

<th>Email</th>

<td><?= htmlspecialchars($patient['email']); ?></td>

</tr>

</table>

<h4 class="mt-4">

Appointment History

</h4>

<table class="table table-bordered table-striped">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Doctor</th>

<th>Date</th>

<th>Time</th>

<th>Status</th>

</tr>

</thead>

<tbody>

<?php

if(($appointments)->num_rows>0){

while($row=($appointments)->fetch_assoc()){

?>

<tr>

<td><?= $row['appointment_id']; ?></td>

<td><?= htmlspecialchars($row['full_name']); ?></td>

<td><?= $row['appointment_date']; ?></td>

<td><?= $row['appointment_time']; ?></td>

<td>

<?php

$status = $row['status'];

if($status=="Pending"){

echo "<span class='badge bg-warning text-dark'>Pending</span>";

}elseif($status=="Approved"){

echo "<span class='badge bg-success'>Approved</span>";

}elseif($status=="Completed"){

echo "<span class='badge bg-primary'>Completed</span>";

}else{

echo "<span class='badge bg-danger'>$status</span>";

}

?>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="5" class="text-center">

No Appointments Found

</td>

</tr>

<?php

}

?>

</tbody>

</table>

<a href="manage_patients.php" class="btn btn-secondary">

← Back

</a>

</div>

</div>

</div>

</body>

</html>