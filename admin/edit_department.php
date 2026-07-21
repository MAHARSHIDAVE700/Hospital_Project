<?php
session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM departments WHERE department_id=?");
$stmt->bind_param("i",$id);
$stmt->execute();

$result = $stmt->get_result();
$department = $result->fetch_assoc();

if(isset($_POST['update'])){

    $name = trim($_POST['department_name']);

    $update = $conn->prepare("UPDATE departments SET department_name=? WHERE department_id=?");
    $update->bind_param("si",$name,$id);

    if($update->execute()){

        header("Location: manage_departments.php");
        exit();

    }

}
?>

<!DOCTYPE html>
<html>

<head>

<title>Edit Department</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-warning">

<h3>Edit Department</h3>

</div>

<div class="card-body">

<form method="POST">

<label class="mb-2">Department Name</label>

<input
type="text"
name="department_name"
class="form-control mb-3"
value="<?= $department['department_name']; ?>"
required>

<button
class="btn btn-warning"
name="update">

Update Department

</button>

<a href="manage_departments.php"
class="btn btn-secondary">

Back

</a>

</form>

</div>

</div>

</div>

</body>

</html>
