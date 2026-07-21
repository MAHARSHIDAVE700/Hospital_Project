<?php
session_start();

if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$userID = $_SESSION['doctor_id'];
$user = $conn->query("SELECT email FROM users WHERE id='$userID'")->fetch_assoc();
$email = $user ? $user['email'] : '';
$doctor = $conn->query("SELECT doctor_id FROM doctors WHERE LOWER(email)=LOWER('$email')")->fetch_assoc();
$doctorID = $doctor ? $doctor['doctor_id'] : 0;

$query = "
SELECT
a.appointment_id,
a.patient_id,
u.full_name AS patient_name,
p.phone,
a.appointment_date,
a.appointment_time,
a.status

FROM appointments a

JOIN patients p
ON a.patient_id = p.patient_id

JOIN users u
ON p.user_id = u.id

WHERE a.doctor_id=?
AND a.appointment_date = CURRENT_DATE

ORDER BY a.appointment_time ASC
";

$stmt=$conn->prepare($query);
$stmt->bind_param("i",$doctorID);
$stmt->execute();

$result=$stmt->get_result();
?>

<!DOCTYPE html>
<html>

<head>

<title>Today's Appointments</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body class="bg-light">

<div class="container mt-5">

<div class="d-flex justify-content-between mb-4">

<h2>📅 My Appointments</h2>

<a href="dashboard.php" class="btn btn-secondary">

Dashboard

</a>

</div>

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Patient</th>

<th>Phone</th>

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

<td><?= htmlspecialchars($row['phone']); ?></td>

<td><?= $row['appointment_date']; ?></td>

<td><?= $row['appointment_time']; ?></td>

<td>

<?php

if($row['status']=="Pending"){

echo "<span class='badge bg-warning text-dark'>Pending</span>";

}elseif($row['status']=="Confirmed"){

echo "<span class='badge bg-success'>Confirmed</span>";

}elseif($row['status']=="Completed"){

echo "<span class='badge bg-primary'>Completed</span>";

}else{

echo "<span class='badge bg-danger'>Cancelled</span>";

}

?>

</td>

<td>

<a href="view_patient_records.php?patient_id=<?= $row['patient_id']; ?>" class="btn btn-info text-white btn-sm">
    Medical History
</a>
<a
href="write_prescription.php?id=<?= $row['appointment_id']; ?>"
class="btn btn-success btn-sm">

Prescription

</a>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="7" class="text-center">

No Appointments Found

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>

<script>
// Live update the table every 5 seconds
setInterval(() => {
    fetch(window.location.href)
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTbody = doc.querySelector('tbody');
            if (newTbody) {
                document.querySelector('tbody').innerHTML = newTbody.innerHTML;
            }
        });
}, 5000);
</script>

</body>

</html>
