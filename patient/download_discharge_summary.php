<?php
// patient/download_discharge_summary.php
session_start();

if (!isset($_SESSION['patient_id']) && !isset($_SESSION['doctor_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

include "../includes/config.php";
require_once "../includes/fpdf.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid IPD Admission ID");
}

$query = "
    SELECT ipd.*, u.full_name AS patient_name, pat.phone, pat.age, pat.gender, pat.address, d.full_name AS doctor_name, b.bed_number, b.bed_type
    FROM ipd_admissions ipd
    JOIN patients pat ON ipd.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    JOIN doctors d ON ipd.doctor_id = d.doctor_id
    JOIN beds b ON ipd.bed_id = b.bed_id
    WHERE ipd.ipd_id = $id
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("IPD Admission record not found.");
}
$ipd = $res->fetch_assoc();

// Check patient access permissions
if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['doctor_id'])) {
    $patientUser = $_SESSION['patient_id'];
    $checkP = $conn->query("SELECT patient_id FROM patients WHERE user_id = '$patientUser'")->fetch_assoc();
    if (!$checkP || $checkP['patient_id'] != $ipd['patient_id']) {
        die("Unauthorized access to this admission summary.");
    }
}

// ------------------------------
// FPDF DISCHARGE SUMMARY ENGINE
// ------------------------------
class DischargePDF extends FPDF {
    function Header() {
        // Logo or Header Branding
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(13, 148, 136); // Teal color
        $this->Cell(100, 10, '🏥 Narayan Hospital Clinic', 0, 0);
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(90, 10, 'CLINICAL DISCHARGE SUMMARY', 0, 1, 'R');
        
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.5);
        $this->Line(10, 22, 200, 22);
        $this->Ln(8);
    }

    function Footer() {
        $this->SetY(-25);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(3);
        
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(100, 5, 'Narayan Clinical IPD Department', 0, 0);
        $this->Cell(90, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');
    }
}

$pdf = new DischargePDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 15, 10);

// Patient Demographics Row
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(100, 6, 'Patient Profile:', 0, 0);
$pdf->Cell(90, 6, 'Admission details:', 0, 1, 'R');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(100, 5, 'Name: ' . $ipd['patient_name'], 0, 0);
$pdf->Cell(90, 5, 'Admission ID: #IPD-' . $ipd['ipd_id'], 0, 1, 'R');

$pdf->Cell(100, 5, 'Age / Gender: ' . $ipd['age'] . ' Yrs / ' . $ipd['gender'], 0, 0);
$pdf->Cell(90, 5, 'Admitted: ' . date('d M Y, h:i A', strtotime($ipd['admission_date'])), 0, 1, 'R');

$pdf->Cell(100, 5, 'Phone: ' . $ipd['phone'], 0, 0);
$pdf->Cell(90, 5, 'Discharged: ' . ($ipd['discharge_date'] ? date('d M Y, h:i A', strtotime($ipd['discharge_date'])) : 'In Stay'), 0, 1, 'R');

$pdf->Cell(100, 5, 'Address: ' . ($ipd['address'] ?? 'N/A'), 0, 0);
$pdf->Cell(90, 5, 'Attending Officer: Dr. ' . $ipd['doctor_name'], 0, 1, 'R');

$pdf->Cell(100, 5, '', 0, 0);
$pdf->Cell(90, 5, 'Room / Bed allocated: ' . $ipd['bed_number'] . ' (' . $ipd['bed_type'] . ')', 0, 1, 'R');

$pdf->Ln(5);

// Check-In Vitals Block
$pdf->SetFillColor(248, 250, 252);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(190, 6, ' Check-in Vitals & Initial Parameters:', 0, 1, 'L', true);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(47, 6, 'BP: ' . $ipd['initial_bp'] . ' mmHg', 0, 0);
$pdf->Cell(47, 6, 'Temp: ' . $ipd['initial_temp'] . ' F', 0, 0);
$pdf->Cell(47, 6, 'Pulse: ' . $ipd['initial_pulse'] . ' bpm', 0, 0);
$pdf->Cell(49, 6, 'Weight: ' . $ipd['initial_weight'] . ' kg', 0, 1);

$pdf->Ln(4);

// Admission Reason
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(190, 6, 'Admission Reason / Initial Diagnosis:', 0, 1, 'L');
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->MultiCell(190, 5, $ipd['admission_reason']);

$pdf->Ln(6);

// Stay Progress Timeline (Daily Checks)
$logs = $conn->query("SELECT * FROM ipd_progress_logs WHERE ipd_id = $id ORDER BY log_date ASC");
if ($logs && $logs->num_rows > 0) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(190, 6, 'Daily Clinical Progress Log Timeline:', 0, 1, 'L');
    
    // Header for logs
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(241, 245, 249);
    $pdf->Cell(40, 7, 'Date & Time', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Vitals (BP/T/P)', 1, 0, 'C', true);
    $pdf->Cell(85, 7, 'Progress Notes / Clinical Observations', 1, 0, 'L', true);
    $pdf->Cell(30, 7, 'Logged By', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(71, 85, 105);
    while ($log = $logs->fetch_assoc()) {
        $dateStr = date('d M, h:i A', strtotime($log['log_date']));
        $vitalsStr = $log['blood_pressure'] . ' | ' . $log['temp_f'] . 'F | ' . $log['pulse_rate'] . 'b';
        
        // MultiCell auto wrapper calculation
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        $pdf->Cell(40, 8, $dateStr, 1, 0, 'C');
        $pdf->Cell(35, 8, $vitalsStr, 1, 0, 'C');
        
        // Store y coordinates for MultiCell wrapping
        $xStart = $pdf->GetX();
        $pdf->MultiCell(85, 4, $log['clinical_notes'], 1, 'L');
        $yEnd = $pdf->GetY();
        
        // Return cursor to complete row
        $pdf->SetXY($xStart + 85, $y);
        $pdf->Cell(30, ($yEnd - $y), $log['logged_by'], 1, 1, 'C');
    }
    $pdf->Ln(6);
}

// Discharge Treatment advice
if ($ipd['status'] === 'Discharged') {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(190, 6, 'Discharge Summary & Treatment Advice (Recommendations):', 0, 1, 'L');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(71, 85, 105);
    $pdf->MultiCell(190, 5, $ipd['discharge_summary']);
    
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(190, 6, 'Condition at Exit: ' . strtoupper($ipd['discharge_status']), 0, 1, 'L');
}

$pdf->Ln(15);

// Signatures block
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(100, 4, 'Notes: Generated by Electronic Medical Record system.', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(90, 4, 'Signature of Attending Medical Officer:', 0, 1, 'R');

$pdf->Ln(10);
$pdf->Cell(190, 4, 'Dr. ' . $ipd['doctor_name'] . ' (MD/MS)', 0, 1, 'R');

$pdf->Output('I', 'Discharge_Summary_IPD_' . $ipd['ipd_id'] . '.pdf');
?>
