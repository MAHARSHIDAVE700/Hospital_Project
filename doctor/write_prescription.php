<?php
session_start();

if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if(!isset($_GET['id'])){
    header("Location: todays_appointments.php");
    exit();
}

$appointment_id = $_GET['id'];

// Get appointment details
$query = "
SELECT
a.*,
u.full_name AS patient_name
FROM appointments a
JOIN patients p ON a.patient_id = p.patient_id
JOIN users u ON p.user_id = u.id
WHERE a.appointment_id=?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i",$appointment_id);
$stmt->execute();

$appointment = $stmt->get_result()->fetch_assoc();

if(isset($_POST['save'])){

    $diagnosis = $_POST['diagnosis'];
    $medicines = $_POST['medicines'];
    $notes = $_POST['notes'];

    $doctor_id = $_SESSION['doctor_id'];
    $patient_id = $appointment['patient_id'];

    $stmt = $conn->prepare("
    INSERT INTO prescriptions
    (appointment_id,doctor_id,patient_id,diagnosis,medicines,notes)
    VALUES(?,?,?,?,?,?)
    ");

    $stmt->bind_param(
        "iiisss",
        $appointment_id,
        $doctor_id,
        $patient_id,
        $diagnosis,
        $medicines,
        $notes
    );

    if($stmt->execute()){

        $conn->query("
        UPDATE appointments
        SET status='Completed'
        WHERE appointment_id=$appointment_id
        ");

        // Trigger SMS alert for 3rd patient in queue
        include_once "../includes/sms_helper.php";
        $docQuery = $conn->query("
            SELECT a.doctor_id, d.full_name AS doctor_name 
            FROM appointments a 
            JOIN doctors d ON a.doctor_id = d.doctor_id 
            WHERE a.appointment_id=$appointment_id
        ");
        if ($docQuery && $doc = $docQuery->fetch_assoc()) {
            $docID = $doc['doctor_id'];
            $docName = $doc['doctor_name'];
            
            $pendingQuery = $conn->query("
                SELECT p.phone, u.full_name 
                FROM appointments a
                JOIN patients p ON a.patient_id = p.patient_id
                JOIN users u ON p.user_id = u.id
                WHERE a.doctor_id='$docID' 
                AND a.appointment_date=CURRENT_DATE 
                AND a.status='Pending'
                ORDER BY a.appointment_time ASC, a.appointment_id ASC
            ");
            
            $i = 0;
            while ($pat = $pendingQuery->fetch_assoc()) {
                if ($i === 3) {
                    SMSHelper::sendQueuePositionAlert($pat['phone'], $pat['full_name'], $docName, 3);
                    break;
                }
                $i++;
            }
        }

        header("Location: todays_appointments.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>

<head>

<title>Write Prescription</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Write Prescription</h3>

</div>

<div class="card-body">

<h5>Patient:
<?= htmlspecialchars($appointment['patient_name']); ?>
</h5>

<form method="POST">

<label class="mt-3">Diagnosis</label>

<textarea
name="diagnosis"
class="form-control"
rows="3"
required></textarea>

<label class="mt-3">Medicines</label>

<textarea
name="medicines"
class="form-control"
rows="5"
required></textarea>

<label class="mt-3">Doctor Notes</label>

<textarea
name="notes"
class="form-control"
rows="4"></textarea>

<br>

<button
class="btn btn-success"
name="save">

Save Prescription

</button>

<a
href="todays_appointments.php"
class="btn btn-secondary">

Back

</a>

</form>

</div>

</div>

</div>

</body>

</html>