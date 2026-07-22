<?php
// patient/download_lab_report.php
session_start();

if (!isset($_SESSION['patient_id']) && !isset($_SESSION['doctor_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

include "../includes/config.php";
require_once "../includes/fpdf.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Report ID");
}

$query = "
    SELECT lr.*, lt.test_name, lt.test_code, lt.sample_type, lt.price,
           d.full_name AS doctor_name, dep.department_name,
           u.full_name AS patient_name, pat.phone, pat.age, pat.gender
    FROM lab_requests lr
    JOIN lab_tests lt ON lr.test_id = lt.test_id
    LEFT JOIN doctors d ON lr.doctor_id = d.doctor_id
    LEFT JOIN departments dep ON d.department_id = dep.department_id
    JOIN patients pat ON lr.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    WHERE lr.request_id = $id AND lr.status = 'Completed'
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Laboratory report not found or not completed yet.");
}
$lr = $res->fetch_assoc();

// Check patient access permissions
if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['doctor_id'])) {
    $patientUser = $_SESSION['patient_id'];
    $checkP = $conn->query("SELECT patient_id FROM patients WHERE user_id = '$patientUser'")->fetch_assoc();
    if (!$checkP || $checkP['patient_id'] != $lr['patient_id']) {
        die("Unauthorized access to this diagnostic report.");
    }
}

// ------------------------------
// PDF REPORT GENERATION
// ------------------------------
require_once "../includes/pdf_helper.php";

$pdfData = PDFHelper::generateLabReportPDF($lr);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Lab_Report_' . $lr['request_id'] . '.pdf"');
echo $pdfData;
exit();
?>
