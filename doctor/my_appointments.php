<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

// Get doctor's email from users table
$userID = $_SESSION['doctor_id'];

$user = (
    $conn->query("SELECT email FROM users WHERE id='$userID'")
)->fetch_assoc();

$email = $user['email'];

// Get doctor_id from doctors table
$doctor = (
    $conn->query("SELECT doctor_id FROM doctors WHERE email='$email'")
)->fetch_assoc();

$doctorID = $doctor['doctor_id'];

// Fetch appointments
$query = "
SELECT
a.*,
u.full_name AS patient_name

FROM appointments a

JOIN patients p
ON a.patient_id=p.patient_id

JOIN users u
ON p.user_id=u.id

WHERE a.doctor_id='$doctorID'

ORDER BY a.appointment_date DESC,
a.appointment_time DESC
";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>

<head>

<title>Doctor Appointments</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-primary">

<div class="container">

<span class="navbar-brand">
👨‍⚕️ Doctor Panel
</span>

<a href="dashboard.php" class="btn btn-light">
Dashboard
</a>

</div>

</nav>

<div class="container mt-5">

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Success!</strong> The appointment status has been updated.
    </div>
<?php endif; ?>

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>My Appointments</h3>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>Patient</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php

if(($result)->num_rows>0){

while($row=($result)->fetch_assoc()){

?>

<tr>

<td><?= $row['appointment_id']; ?></td>

<td><?= htmlspecialchars($row['patient_name']); ?></td>

<td><?= $row['appointment_date']; ?></td>

<td><?= $row['appointment_time']; ?></td>

<td><?= $row['status']; ?></td>

<td>

<a href="view_patient_records.php?patient_id=<?= $row['patient_id']; ?>" class="btn btn-info text-white btn-sm">
    Medical History
</a>
<a href="../admin/update_appointment.php?id=<?= $row['appointment_id']; ?>&status=Confirmed"
class="btn btn-success btn-sm">

Confirm

</a>

<a href="write_prescription.php?appointment_id=<?= $row['appointment_id']; ?>"
class="btn btn-warning btn-sm">

Prescription

</a>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="6" class="text-center">

No Appointments Found

</td>

</tr>

<?php } ?>

</tbody>

</table>

<a href="dashboard.php" class="btn btn-secondary">

← Back

</a>

</div>

</div>

</div>

</body>

</html>