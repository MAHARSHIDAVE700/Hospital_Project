<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message = "";

// Add Department
if (isset($_POST['add_department'])) {

    $department = trim($_POST['department_name']);

    $stmt = $conn->prepare("INSERT INTO departments (department_name) VALUES (?)");
    $stmt->bind_param("s", $department);

    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Department Added Successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Failed to Add Department.</div>";
    }
}

$departments = $conn->query("SELECT * FROM departments");
?>

<!DOCTYPE html>
<html>

<head>

<title>Manage Departments</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<h2>Manage Departments</h2>

<?= $message ?>

<div class="card shadow mb-4">

<div class="card-header bg-success text-white">

Add Department

</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-9">

<input
type="text"
name="department_name"
class="form-control"
placeholder="Department Name"
required>

</div>

<div class="col-md-3">

<button
class="btn btn-success w-100"
name="add_department">

Add

</button>

</div>

</div>

</form>

</div>

</div>

<table class="table table-bordered">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Department Name</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($row=($departments)->fetch_assoc()){ ?>

<tr>

<td><?= $row['department_id']; ?></td>

<td><?= $row['department_name']; ?></td>

<td>

<a
href="edit_department.php?id=<?= $row['department_id']; ?>"
class="btn btn-warning btn-sm">

Edit

</a>

<a
href="delete_department.php?id=<?= $row['department_id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this department?')">

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
