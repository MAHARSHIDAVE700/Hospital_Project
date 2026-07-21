<?php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['doctor_id'];

// Get doctor's email
$user = (
    $conn->query("SELECT email FROM users WHERE id='$userID'")
)->fetch_assoc();

$email = $user['email'];

// Get doctor_id
$doctor = (
    $conn->query("SELECT doctor_id FROM doctors WHERE email='$email'")
)->fetch_assoc();

$doctorID = $doctor['doctor_id'];

$appointmentID = $_GET['appointment_id'];

$getAppointment = $conn->query("
SELECT *
FROM appointments
WHERE appointment_id='$appointmentID'
");

$appointment = ($getAppointment)->fetch_assoc();

$patientID = $appointment['patient_id'];

$message="";

if(isset($_POST['save'])){

    $diagnosis=$_POST['diagnosis'];
    $medicines=$_POST['medicines'];
    $notes=$_POST['notes'];

    $stmt=$conn->prepare("
    INSERT INTO prescriptions
    (appointment_id,patient_id,doctor_id,diagnosis,medicines,notes)
    VALUES(?,?,?,?,?,?)
    ");

    $stmt->bind_param(
    "iiisss",
    $appointmentID,
    $patientID,
    $doctorID,
    $diagnosis,
    $medicines,
    $notes
    );

    if($stmt->execute()){

        $conn->query("
        UPDATE appointments
        SET status='Completed'
        WHERE appointment_id='$appointmentID'
        ");

        $message="<div class='alert alert-success'>
        Prescription Saved Successfully.
        </div>";

    }else{

        $message="<div class='alert alert-danger'>
        Failed to Save Prescription.
        </div>";

    }

}
?>

<!DOCTYPE html>

<html>

<head>

<title>Write Prescription</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Write Prescription</h3>

</div>

<div class="card-body">

<?= $message ?>

<form method="POST">

<div class="mb-3">

<label>Diagnosis</label>

<textarea
name="diagnosis"
class="form-control"
rows="4"
required></textarea>

</div>

<div class="mb-3">

<label>Medicines</label>

<textarea
name="medicines"
class="form-control"
rows="4"
required></textarea>

</div>

<div class="mb-3">

<label>Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"></textarea>

</div>

<button
class="btn btn-success"
name="save">

Save Prescription

</button>

<a
href="my_appointments.php"
class="btn btn-secondary">

Back

</a>

</form>

</div>

</div>

</div>

</body>

</html>
