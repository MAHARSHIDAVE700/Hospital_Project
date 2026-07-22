<?php
// includes/pdf_helper.php
require_once __DIR__ . '/fpdf.php';

class PDFHelper {
    public static function generatePrescriptionPDF($p) {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(13, 110, 253); // Blue
        $pdf->Cell(0, 10, 'Narayan Hospital', 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(108, 117, 125); // Gray
        $pdf->Cell(0, 5, 'Multi-Specialty OPD & Research Center', 0, 1, 'C');
        $pdf->Cell(0, 5, '123 Health Ave, Medical Zone | Emergency Contact: +91 98765 43210', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Horizontal line
        $pdf->SetDrawColor(13, 110, 253);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(8);
        
        // Doctor details
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(100, 7, 'Dr. ' . $p['doctor_name'], 0, 0);
        $pdf->Cell(90, 7, 'Prescription ID: #' . $p['prescription_id'], 0, 1, 'R');
        
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(100, 5, $p['department_name'] ?? 'General Medicine', 0, 0);
        $pdf->Cell(90, 5, 'Date: ' . date('d M Y, h:i A', strtotime($p['created_at'])), 0, 1, 'R');
        $pdf->Ln(10);
        
        // Patient details box
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(222, 226, 230);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(10, $pdf->GetY(), 190, 20, 'DF');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY(15, $pdf->GetY() + 3);
        $pdf->Cell(90, 5, 'Patient Name: ' . $p['patient_name'], 0, 0);
        $pdf->Cell(80, 5, 'Age / Gender: ' . $p['age'] . ' Yrs / ' . $p['gender'], 0, 1, 'R');
        
        $pdf->SetXY(15, $pdf->GetY() + 2);
        $pdf->Cell(90, 5, 'Phone: ' . $p['phone'], 0, 1);
        $pdf->SetXY(10, $pdf->GetY() + 8);
        
        // Rx symbol
        $pdf->SetFont('Times', 'B', 24);
        $pdf->SetTextColor(13, 110, 253);
        $pdf->Cell(0, 10, 'Rx', 0, 1);
        $pdf->Ln(2);
        
        // Diagnosis
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 6, 'DIAGNOSIS / FINDINGS', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 6, $p['diagnosis'], 1, 'L');
        $pdf->Ln(8);
        
        // Medicines
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 6, 'PRESCRIBED MEDICINES', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(0, 6, $p['medicines'], 1, 'L');
        $pdf->Ln(8);
        
        // Notes
        if (!empty($p['notes'])) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(108, 117, 125);
            $pdf->Cell(0, 6, 'ADDITIONAL INSTRUCTIONS', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->MultiCell(0, 6, $p['notes'], 1, 'L');
            $pdf->Ln(15);
        }
        
        // Signature Line
        $pdf->SetY($pdf->GetPageHeight() - 50);
        $pdf->Line(140, $pdf->GetY(), 190, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Dr. ' . $p['doctor_name'], 0, 1, 'R');
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 4, 'Authorized Medical Practitioner', 0, 1, 'R');
        $pdf->Cell(0, 4, 'Computer-Generated Prescription', 0, 1, 'L');
        
        return $pdf->Output('S');
    }

    public static function generateBillPDF($a) {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(25, 135, 84); // Green
        $pdf->Cell(0, 10, 'Narayan Hospital', 0, 1, 'C');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(108, 117, 125); // Gray
        $pdf->Cell(0, 5, 'Official OPD Fee Receipt', 0, 1, 'C');
        $pdf->Cell(0, 5, 'GSTIN: 24AAACN1234F1Z9 | Reg No: NH/2026/789', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Horizontal line
        $pdf->SetDrawColor(25, 135, 84);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(8);
        
        // Receipt info
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(100, 7, 'Receipt No: REC-' . sprintf('%05d', $a['appointment_id']), 0, 0);
        $pdf->Cell(90, 7, 'Date: ' . date('d M Y'), 0, 1, 'R');
        $pdf->Ln(6);
        
        // Table content
        $pdf->SetDrawColor(222, 226, 230);
        $pdf->SetLineWidth(0.2);
        
        $data = [
            ['Patient Name', $a['patient_name']],
            ['Phone', $a['phone']],
            ['Consulting Doctor', 'Dr. ' . $a['doctor_name'] . ' (' . ($a['department_name'] ?? 'General') . ')'],
            ['Appointment Slot', $a['appointment_date'] . ' at ' . $a['appointment_time']],
            ['Queue Token No', $a['token_number'] ?? 'Pending Token'],
            ['OPD Consultation Fee', 'INR ' . number_format($a['opd_fee_paid'] ?? 200, 2)],
            ['Payment Status', $a['fee_status'] ?? 'Paid']
        ];
        
        foreach ($data as $row) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetFillColor(248, 249, 250);
            $pdf->Cell(60, 10, $row[0], 1, 0, 'L', true);
            
            $pdf->SetFont('Arial', '', 10);
            if ($row[0] === 'OPD Consultation Fee') {
                $pdf->SetTextColor(25, 135, 84);
                $pdf->SetFont('Arial', 'B', 10);
            } else {
                $pdf->SetTextColor(0, 0, 0);
            }
            $pdf->Cell(130, 10, $row[1], 1, 1, 'L');
        }
        
        $pdf->Ln(15);
        
        // Footer text
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 5, 'Thank you for choosing Narayan Hospital. Wish you a speedy recovery!', 0, 1, 'C');
        $pdf->Cell(0, 5, 'This receipt is electronically issued and valid without signature.', 0, 1, 'C');
        
        return $pdf->Output('S');
    }

    public static function generateLabReportPDF($lr) {
        $pdf = new FPDF();
        $pdf->AddPage();
        
        // Header
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(13, 148, 136); // Teal primary
        $pdf->Cell(100, 10, 'Narayan Hospital Laboratory', 0, 0);
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(90, 10, 'DIAGNOSTIC REPORT', 0, 1, 'R');
        
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, 22, 200, 22);
        $pdf->Ln(8);
        
        // Report details block
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(100, 6, 'Patient Demographics:', 0, 0);
        $pdf->Cell(90, 6, 'Report Summary:', 0, 1, 'R');
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(100, 5, 'Name: ' . $lr['patient_name'], 0, 0);
        $pdf->Cell(90, 5, 'Report ID: LAB-REQ-' . $lr['request_id'], 0, 1, 'R');
        
        $pdf->Cell(100, 5, 'Age / Gender: ' . $lr['age'] . ' Yrs / ' . $lr['gender'], 0, 0);
        $pdf->Cell(90, 5, 'Ordered Date: ' . date('d M Y', strtotime($lr['request_date'])), 0, 1, 'R');
        
        $pdf->Cell(100, 5, 'Phone: ' . $lr['phone'], 0, 0);
        $pdf->Cell(90, 5, 'Release Date: ' . date('d M Y, h:i A', strtotime($lr['result_date'])), 0, 1, 'R');
        
        $pdf->Cell(100, 5, 'Referred By: Dr. ' . ($lr['doctor_name'] ?? 'Clinical Direct'), 0, 0);
        $pdf->Cell(90, 5, 'Sample Type: ' . $lr['sample_type'], 0, 1, 'R');
        
        $pdf->Ln(8);
        
        // Diagnostic Test Info
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(190, 10, ' Test ordered: ' . $lr['test_name'] . ' (' . $lr['test_code'] . ')', 0, 1, 'L', true);
        $pdf->Ln(4);
        
        // Test Results parameters
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(190, 6, 'Diagnostic Findings and Parameter Measurements:', 0, 1);
        $pdf->Ln(2);
        
        // Split values and format nicely
        $pdf->SetFont('Courier', '', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->SetLineWidth(0.2);
        
        $resultsBlock = $lr['result_details'];
        $pdf->MultiCell(190, 6, $resultsBlock, 1, 'L', true);
        $pdf->Ln(8);
        
        // Overall Diagnostic Summary
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(30, 41, 59);
        $pdf->Cell(190, 6, 'Clinical Interpretation Summary:', 0, 1);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->MultiCell(190, 5, $lr['result_summary'], 0, 'L');
        
        $pdf->Ln(20);
        
        // Sign-off
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(71, 85, 105);
        $pdf->Cell(100, 5, 'Prepared By:', 0, 0);
        $pdf->Cell(90, 5, 'Approved By:', 0, 1, 'R');
        
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(100, 5, 'Clinical Lab Assistant', 0, 0);
        $pdf->Cell(90, 5, 'Medical Officer / Pathologist In Charge', 0, 1, 'R');
        
        return $pdf->Output('S');
    }
}
