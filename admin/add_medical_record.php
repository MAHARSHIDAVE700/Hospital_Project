<?php
session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$message="";

$patients=$conn->query("SELECT patient_id FROM patients");
$doctors=$conn->query("SELECT doctor_id,full_name FROM doctors");

if(isset($_POST['save'])){

    $patient=$_POST['patient'];
    $doctor=$_POST['doctor'];
    $diagnosis=$_POST['diagnosis'];
    $prescription=$_POST['prescription'];
    $notes=$_POST['notes'];

    $stmt=$conn->prepare("INSERT INTO medical_records(patient_id,doctor_id,diagnosis,prescription,notes)
    VALUES(?,?,?,?,?)");

    $stmt->bind_param("iisss",$patient,$doctor,$diagnosis,$prescription,$notes);

    if($stmt->execute()){
        $message="<div class='alert alert-success'>Medical Record Saved.</div>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<title>Add Medical Record</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<h2>Add Medical Record</h2>

<?= $message ?>

<form method="POST">

<label>Patient ID</label>

<input
type="number"
name="patient"
class="form-control mb-3"
required>

<label>Doctor</label>

<select
name="doctor"
class="form-select mb-3">

<?php while($row=($doctors)->fetch_assoc()){ ?>

<option value="<?= $row['doctor_id']; ?>">

<?= $row['full_name']; ?>

</option>

<?php } ?>

</select>

<label>Diagnosis</label>

<textarea
name="diagnosis"
class="form-control mb-3"
required></textarea>

<label>Prescription</label>

<textarea
name="prescription"
class="form-control mb-3"
required></textarea>

<label>Notes</label>

<textarea
name="notes"
class="form-control mb-3"></textarea>

<button
class="btn btn-primary"
name="save">

Save Record

</button>

</form>

</div>

</body>

</html>