<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include "includes/config.php";

// Get patient ID
$user_id = $_SESSION['user_id'];

$sql = "SELECT patient_id FROM patients WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

$patient_id = $patient['patient_id'];

// Fetch appointments
$query = "SELECT
a.appointment_id,
d.full_name,
dep.department_name,
a.appointment_date,
a.appointment_time,
a.status

FROM appointments a

JOIN doctors d
ON a.doctor_id = d.doctor_id

JOIN departments dep
ON d.department_id = dep.department_id

WHERE a.patient_id = ?

ORDER BY a.appointment_date ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$appointments = $stmt->get_result();
?>

<!DOCTYPE html>
<html>

<head>

<title>My Appointments</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<h2 class="mb-4">My Appointments</h2>

<table class="table table-bordered table-striped">

<thead class="table-primary">

<tr>

<th>ID</th>
<th>Doctor</th>
<th>Department</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($row = $appointments->fetch_assoc()){ ?>

<tr>

<td><?= $row['appointment_id']; ?></td>

<td><?= $row['full_name']; ?></td>

<td><?= $row['department_name']; ?></td>

<td><?= $row['appointment_date']; ?></td>

<td><?= $row['appointment_time']; ?></td>

<td><?= $row['status']; ?></td>

<td>

<?php
if($row['status']=="Pending"){
?>

<a href="cancel_appointment.php?id=<?= $row['appointment_id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Are you sure you want to cancel this appointment?')">

Cancel

</a>

<?php
}else{

echo "-";

}
?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<a href="patient/dashboard.php" class="btn btn-primary">

Back to Dashboard

</a>

</div>

</body>

</html>
