<?php
/**
 * Consultation Generator Foundation Stub
 * Path: simulation/generators/ConsultationGenerator.php
 */

class ConsultationGenerator {

    /**
     * Generate synthetic medical record & prescription payload.
     *
     * @return array
     */
    public static function generateSampleData() {
        $diagnoses = ['Acute Bronchitis', 'Hypertension', 'Type 2 Diabetes', 'Viral Gastroenteritis', 'Migraine Headaches'];
        $medicines = ['Paracetamol 650mg, Amoxicillin 500mg', 'Metformin 500mg, Atorvastatin 10mg', 'Pantoprazole 40mg, Domperidone 10mg'];
        
        return [
            'diagnosis'    => $diagnoses[array_rand($diagnoses)],
            'prescription' => $medicines[array_rand($medicines)],
            'notes'        => 'Simulated patient consultation completed cleanly.',
            'is_simulated' => true
        ];
    }
}
