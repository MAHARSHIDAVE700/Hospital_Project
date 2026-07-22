<?php
session_start();

if(!isset($_SESSION['doctor_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

$from_my_appointments = isset($_GET['appointment_id']);
$appointment_id = $_GET['id'] ?? $_GET['appointment_id'] ?? null;
if (!$appointment_id) {
    header("Location: todays_appointments.php");
    exit();
}

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

        ActivityLogger::log($_SESSION['doctor_id'], 'doctor', 'Complete Consultation', 'Completed appointment ID #' . $appointment_id . ' with prescription entry.');

        // Generate and email prescription & bill PDFs as attachments
        try {
            $prescription_query = $conn->query("
                SELECT p.*, d.full_name AS doctor_name, dep.department_name, u.full_name AS patient_name, u.email, pat.phone, pat.age, pat.gender
                FROM prescriptions p
                JOIN doctors d ON p.doctor_id = d.doctor_id
                LEFT JOIN departments dep ON d.department_id = dep.department_id
                JOIN patients pat ON p.patient_id = pat.patient_id
                JOIN users u ON pat.user_id = u.id
                WHERE p.appointment_id = $appointment_id
                ORDER BY p.prescription_id DESC LIMIT 1
            ");
            
            $appointment_query = $conn->query("
                SELECT a.*, d.full_name AS doctor_name, dep.department_name, u.full_name AS patient_name, pat.phone
                FROM appointments a
                JOIN doctors d ON a.doctor_id = d.doctor_id
                JOIN departments dep ON d.department_id = dep.department_id
                JOIN patients pat ON a.patient_id = pat.patient_id
                JOIN users u ON pat.user_id = u.id
                WHERE a.appointment_id = $appointment_id
            ");

            if ($prescription_query && $appointment_query) {
                $prescription_data = $prescription_query->fetch_assoc();
                $appointment_data = $appointment_query->fetch_assoc();

                if ($prescription_data && $appointment_data) {
                    include_once "../includes/pdf_helper.php";
                    include_once "../includes/email_helper.php";

                    $prescription_pdf = PDFHelper::generatePrescriptionPDF($prescription_data);
                    $bill_pdf = PDFHelper::generateBillPDF($appointment_data);

                    $attachments = [
                        [
                            'filename' => 'Prescription_' . $appointment_id . '.pdf',
                            'content' => base64_encode($prescription_pdf)
                        ],
                        [
                            'filename' => 'OPD_Bill_' . $appointment_id . '.pdf',
                            'content' => base64_encode($bill_pdf)
                        ]
                    ];

                    $patient_email = trim($prescription_data['email']);
                    if (!empty($patient_email)) {
                        $subject = "Consultation Summary & OPD Bill - Narayan Hospital";
                        $body = "
                            <p>Your consultation with <strong>Dr. " . htmlspecialchars($prescription_data['doctor_name']) . "</strong> is complete.</p>
                            <p>We have attached your digital prescription and OPD payment receipt to this email.</p>
                            <p>Thank you for choosing Narayan Hospital.</p>
                        ";
                        $bodyHtml = EmailHelper::getTemplate("Consultation Complete", $prescription_data['patient_name'], $body);
                        EmailHelper::sendEmail($patient_email, $subject, $bodyHtml, $attachments);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Failed to generate and email PDF prescription/bill: " . $e->getMessage());
        }

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

        if ($from_my_appointments) {
            header("Location: my_appointments.php?updated=1");
        } else {
            header("Location: todays_appointments.php");
        }
        exit();
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
