<?php
// patient/download_invoice.php
session_start();

if (!isset($_SESSION['patient_id']) && !isset($_SESSION['doctor_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

include "../includes/config.php";
require_once "../includes/fpdf.php";

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("Invalid Invoice ID");
}

$query = "
    SELECT inv.*, u.full_name AS patient_name, pat.phone, pat.age, pat.gender, pat.address
    FROM invoices inv
    JOIN patients pat ON inv.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    WHERE inv.invoice_id = $id
";
$res = $conn->query($query);
if (!$res || $res->num_rows === 0) {
    die("Invoice not found.");
}
$inv = $res->fetch_assoc();

// Check patient access permissions
if (isset($_SESSION['patient_id']) && !isset($_SESSION['admin_id']) && !isset($_SESSION['doctor_id'])) {
    $patientUser = $_SESSION['patient_id'];
    $checkP = $conn->query("SELECT patient_id FROM patients WHERE user_id = '$patientUser'")->fetch_assoc();
    if (!$checkP || $checkP['patient_id'] != $inv['patient_id']) {
        die("Unauthorized access to this invoice.");
    }
}

// ------------------------------
// FPDF BILLING INVOICE ENGINE
// ------------------------------
class InvoicePDF extends FPDF {
    function Header() {
        // Logo or Header Branding
        $this->SetFont('Arial', 'B', 16);
        $this->SetTextColor(13, 148, 136); // Teal color
        $this->Cell(100, 10, '🏥 Narayan Hospital Clinic', 0, 0);
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(90, 10, 'OFFICIAL BILL RECEIPT', 0, 1, 'R');
        
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
        $this->Cell(100, 5, 'Narayan Healthcare Center · Billing Desk', 0, 0);
        $this->Cell(90, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 1, 'R');
    }
}

$pdf = new InvoicePDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetMargins(10, 15, 10);

// Demographics Block
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(100, 6, 'Patient Details:', 0, 0);
$pdf->Cell(90, 6, 'Invoice Details:', 0, 1, 'R');

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(100, 5, 'Name: ' . $inv['patient_name'], 0, 0);
$pdf->Cell(90, 5, 'Invoice ID: ' . $inv['invoice_number'], 0, 1, 'R');

$pdf->Cell(100, 5, 'Age / Gender: ' . $inv['age'] . ' Yrs / ' . $inv['gender'], 0, 0);
$pdf->Cell(90, 5, 'Date Generated: ' . date('d M Y', strtotime($inv['created_at'])), 0, 1, 'R');

$pdf->Cell(100, 5, 'Phone: ' . $inv['phone'], 0, 0);
$pdf->Cell(90, 5, 'Status: ' . strtoupper($inv['status']), 0, 1, 'R');

$pdf->Cell(100, 5, 'Address: ' . ($inv['address'] ?? 'N/A'), 0, 0);
$pdf->Cell(90, 5, 'Payment Method: ' . ($inv['payment_method'] ?? 'Cash/Counter'), 0, 1, 'R');

$pdf->Ln(8);

// Table Header
$pdf->SetFillColor(241, 245, 249);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(20, 8, 'S.No', 1, 0, 'C', true);
$pdf->Cell(120, 8, 'Consolidated Service Component', 1, 0, 'L', true);
$pdf->Cell(50, 8, 'Amount (INR)', 1, 1, 'R', true);

// Items List
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);

$index = 1;
$subtotal = 0.00;

$breakdown = [
    ['OPD Consultation & Token Fees', $inv['opd_charges']],
    ['IPD Admitted Ward / Bed Charges', $inv['ipd_charges']],
    ['Laboratory Diagnostic Test Charges', $inv['lab_charges']],
    ['Pharmacy Prescription Medication Sales', $inv['pharmacy_charges']]
];

foreach ($breakdown as $item) {
    $cost = floatval($item[1]);
    if ($cost > 0) {
        $pdf->Cell(20, 8, $index++, 1, 0, 'C');
        $pdf->Cell(120, 8, ' ' . $item[0], 1, 0, 'L');
        $pdf->Cell(50, 8, number_format($cost, 2) . ' ', 1, 1, 'R');
        $subtotal += $cost;
    }
}

$pdf->Ln(4);

// Calculations right-aligned
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(130, 6, '', 0, 0);
$pdf->Cell(35, 6, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(25, 6, number_format($subtotal, 2) . ' ', 0, 1, 'R');

if (floatval($inv['tax_amount']) > 0) {
    $pdf->Cell(130, 6, '', 0, 0);
    $pdf->Cell(35, 6, 'Tax / Surcharge:', 0, 0, 'R');
    $pdf->Cell(25, 6, '+' . number_format($inv['tax_amount'], 2) . ' ', 0, 1, 'R');
}

if (floatval($inv['discount']) > 0) {
    $pdf->Cell(130, 6, '', 0, 0);
    $pdf->Cell(35, 6, 'Discount:', 0, 0, 'R');
    $pdf->Cell(25, 6, '-' . number_format($inv['discount'], 2) . ' ', 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(130, 8, '', 0, 0);
$pdf->Cell(35, 8, 'Total Paid / Due:', 0, 0, 'R');
$pdf->Cell(25, 8, 'INR ' . number_format($inv['total_amount'], 2) . ' ', 0, 1, 'R');

$pdf->Ln(15);

// Terms and signature
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(100, 4, 'Notes: This invoice was generated electronically.', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(90, 4, 'Signature of Cashier In Charge:', 0, 1, 'R');

$pdf->Ln(12);
$pdf->Cell(190, 4, 'Narayan Healthcare Accounts Desk', 0, 1, 'R');

$pdf->Output('I', 'Hospital_Invoice_' . $inv['invoice_number'] . '.pdf');
?>
