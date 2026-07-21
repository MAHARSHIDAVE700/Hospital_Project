<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$name = $_SESSION['doctor_name'];

$stmt = $conn->prepare("
SELECT
d.*,
dep.department_name
FROM doctors d
LEFT JOIN departments dep
ON d.department_id = dep.department_id
WHERE d.full_name=?
");

$stmt->bind_param("s", $name);
$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows==0){
    die("Doctor profile not found.");
}

$doctor = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>Doctor Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-primary text-white">
<h3>Doctor Profile</h3>
</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th>Name</th>
<td><?= htmlspecialchars($doctor['full_name']) ?></td>
</tr>

<tr>
<th>Email</th>
<td><?= htmlspecialchars($doctor['email']) ?></td>
</tr>

<tr>
<th>Phone</th>
<td><?= htmlspecialchars($doctor['phone']) ?></td>
</tr>

<tr>
<th>Department</th>
<td><?= htmlspecialchars($doctor['department_name']) ?></td>
</tr>

<tr>
<th>Specialization</th>
<td><?= htmlspecialchars($doctor['specialization']) ?></td>
</tr>

<tr>
<th>Experience</th>
<td><?= htmlspecialchars($doctor['experience']) ?> Years</td>
</tr>

</table>

<a href="dashboard.php" class="btn btn-secondary">
← Back
</a>

</div>

</div>

</div>

</body>
</html>
