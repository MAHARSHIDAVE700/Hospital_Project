<?php
session_start();

if(!isset($_SESSION['patient_id'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Coming Soon</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="bg-light">

<div class="container mt-5">

<div class="alert alert-info">

<h3>This page is under development.</h3>

<a href="dashboard.php" class="btn btn-primary mt-3">

Back to Dashboard

</a>

</div>

</div>

</body>
</html>
