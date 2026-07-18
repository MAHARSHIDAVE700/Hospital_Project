<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$query = "
SELECT
a.appointment_id,
u.full_name AS patient_name,
d.full_name AS doctor_name,
dep.department_name,
a.appointment_date,
a.appointment_time,
a.status

FROM appointments a

JOIN patients p ON a.patient_id = p.patient_id
JOIN users u ON p.user_id = u.id
JOIN doctors d ON a.doctor_id = d.doctor_id
JOIN departments dep ON d.department_id = dep.department_id

ORDER BY a.appointment_date ASC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>

<head>

<title>Manage Appointments</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<h2>Manage Appointments</h2>

<table class="table table-bordered table-striped">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>Patient</th>
<th>Doctor</th>
<th>Department</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($row=($result)->fetch_assoc()){ ?>

<tr>

<td><?= $row['appointment_id']; ?></td>

<td><?= $row['patient_name']; ?></td>

<td><?= $row['doctor_name']; ?></td>

<td><?= $row['department_name']; ?></td>

<td><?= $row['appointment_date']; ?></td>

<td><?= $row['appointment_time']; ?></td>

<td><?= $row['status']; ?></td>

<td>

<a href="update_appointment.php?id=<?= $row['appointment_id']; ?>&status=Confirmed"
class="btn btn-success btn-sm">

Confirm

</a>

<a href="update_appointment.php?id=<?= $row['appointment_id']; ?>&status=Completed"
class="btn btn-primary btn-sm">

Complete

</a>

<a href="update_appointment.php?id=<?= $row['appointment_id']; ?>&status=Cancelled"
class="btn btn-danger btn-sm">

Cancel

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