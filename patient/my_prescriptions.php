<?php
session_start();

if(!isset($_SESSION['patient_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['patient_id'];

$getPatient = $conn->query("SELECT patient_id FROM patients WHERE user_id='$userID'");

$patient = ($getPatient)->fetch_assoc();

$patientID = $patient['patient_id'];

$query = "
SELECT
p.*,
d.full_name

FROM prescriptions p

JOIN doctors d
ON p.doctor_id=d.doctor_id

WHERE p.patient_id='$patientID'

ORDER BY p.created_at DESC
";

$result = $conn->query($query);

?>

<!DOCTYPE html>
<html>

<head>

<title>My Prescriptions</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<nav class="navbar navbar-dark bg-warning">

<div class="container">

<span class="navbar-brand">

💊 My Prescriptions

</span>

<a href="dashboard.php" class="btn btn-dark">

Dashboard

</a>

</div>

</nav>

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-warning">

<h3>Prescription History</h3>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Doctor</th>

<th>Diagnosis</th>

<th>Medicines</th>

<th>Notes</th>

<th>Date</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php

if(($result)->num_rows>0){

while($row=($result)->fetch_assoc()){

?>

<tr>

<td><?= $row['prescription_id']; ?></td>

<td><?= htmlspecialchars($row['full_name']); ?></td>

<td><?= nl2br(htmlspecialchars($row['diagnosis'])); ?></td>

<td><?= nl2br(htmlspecialchars($row['medicines'])); ?></td>

<td><?= nl2br(htmlspecialchars($row['notes'])); ?></td>

<td><?= $row['created_at']; ?></td>

<td>
    <a href="download_prescription.php?id=<?= $row['prescription_id']; ?>" target="_blank" class="btn btn-outline-primary btn-sm">
        📄 Print PDF
    </a>
</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="6" class="text-center">

No Prescriptions Available

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