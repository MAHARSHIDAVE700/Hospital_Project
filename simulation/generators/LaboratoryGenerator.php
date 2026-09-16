<?php
/**
 * Laboratory Request & Result Generator
 * Path: simulation/generators/LaboratoryGenerator.php
 * 
 * Generates synthetic laboratory payloads and realistic test-specific results.
 */

class LaboratoryGenerator {

    /**
     * Map test names/codes to realistic clinical diagnostic result summaries.
     *
     * @param string $testName
     * @return array
     */
    public static function generateResultForTest($testName) {
        $testNameLower = strtolower($testName);

        if (strpos($testNameLower, 'cbc') !== false || strpos($testNameLower, 'blood count') !== false) {
            return [
                'summary' => 'Hemoglobin: 14.2 g/dL (Normal). Total WBC: 7,500 /uL. Platelets: 250,000 /uL.',
                'details' => 'RBC Count: 4.8 million/uL. Hematocrit: 42%. Differential Count: Neutrophils 62%, Lymphocytes 30%, Monocytes 5%, Eosinophils 3%.'
            ];
        } elseif (strpos($testNameLower, 'lipid') !== false) {
            return [
                'summary' => 'Total Cholesterol: 185 mg/dL. HDL: 52 mg/dL. LDL: 110 mg/dL. Triglycerides: 140 mg/dL.',
                'details' => 'Lipid panel within desirable reference range. Cardiac risk ratio normal (3.5).'
            ];
        } elseif (strpos($testNameLower, 'liver') !== false || strpos($testNameLower, 'lft') !== false) {
            return [
                'summary' => 'SGOT/AST: 28 U/L. SGPT/ALT: 32 U/L. Total Bilirubin: 0.9 mg/dL.',
                'details' => 'Alkaline Phosphatase: 85 U/L. Serum Albumin: 4.2 g/dL. No evidence of hepatic inflammation.'
            ];
        } elseif (strpos($testNameLower, 'thyroid') !== false || strpos($testNameLower, 'tsh') !== false) {
            return [
                'summary' => 'TSH: 2.4 uIU/mL (Normal). Free T3: 3.1 pg/mL. Free T4: 1.2 ng/dL.',
                'details' => 'Euthyroid state. Thyroid hormone levels within physiological limits.'
            ];
        } elseif (strpos($testNameLower, 'sugar') !== false || strpos($testNameLower, 'rbs') !== false) {
            return [
                'summary' => 'Random Blood Glucose: 105 mg/dL. Fasting equivalent: Normal.',
                'details' => 'Postprandial glycemic control satisfactory. No glycosuria.'
            ];
        } elseif (strpos($testNameLower, 'urine') !== false) {
            return [
                'summary' => 'Urine Routine: Clear, Pale Yellow. Specific Gravity: 1.015. pH: 6.0.',
                'details' => 'Protein: Negative. Glucose: Negative. Pus cells: 1-2/hpf. Red cells: Nil.'
            ];
        } elseif (strpos($testNameLower, 'kidney') !== false || strpos($testNameLower, 'kft') !== false) {
            return [
                'summary' => 'Serum Creatinine: 0.9 mg/dL. Blood Urea Nitrogen (BUN): 14 mg/dL. Uric Acid: 5.2 mg/dL.',
                'details' => 'Estimated GFR > 90 mL/min/1.73m2. Renal excretory function intact.'
            ];
        } else {
            return [
                'summary' => 'Diagnostic evaluation complete. Parameters within expected reference range.',
                'details' => 'Routine clinical screening yields normal physiological markers.'
            ];
        }
    }
}
