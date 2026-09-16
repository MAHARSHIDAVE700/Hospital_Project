<?php
/**
 * IPD Patient Journey Payload Generator
 * Path: simulation/generators/IPDGenerator.php
 * 
 * Generates realistic synthetic IPD admission reasons, initial vitals, daily clinical progress logs,
 * and discharge summaries matching exact PostgreSQL table columns.
 */

class IPDGenerator {

    /**
     * Generate synthetic IPD admission data payload.
     *
     * @return array
     */
    public static function generateIPDPayload() {
        $reasons = [
            'Severe viral fever with acute dehydration',
            'Post-operative monitoring following appendectomy',
            'Acute bronchial asthma flare-up requiring oxygen therapy',
            'Severe gastroenteritis with electrolyte imbalance',
            'Hypertensive crisis under clinical observation',
            'Acute lumbar strain under physical rehabilitation',
            'Pneumonia observation and IV antibiotic administration'
        ];

        $bps = ['120/80 mmHg', '128/84 mmHg', '118/76 mmHg', '135/88 mmHg', '122/78 mmHg'];
        $temps = ['101.4 F', '99.8 F', '100.2 F', '98.6 F', '102.0 F'];
        $pulses = ['88 bpm', '94 bpm', '82 bpm', '76 bpm', '90 bpm'];
        $weights = ['64 kg', '72 kg', '58 kg', '80 kg', '68 kg'];

        $summaries = [
            'Patient responded very well to IV antibiotic and rehydration therapy. Afebrile for over 48 hours. Vitals stable. Discharged on oral medications.',
            'Post-operative recovery uneventful. Surgical site clean and dry. Pain well controlled. Discharged with advice for 1 week rest.',
            'Respiratory symptoms fully resolved. Oxygen saturation 98% on room air. Chest clear on auscultation. Discharged with inhaler prescription.',
            'Blood pressure and metabolic parameters stabilized. Clinical condition satisfactory. Discharged with follow-up scheduled after 7 days.'
        ];

        return [
            'admission_reason'  => $reasons[array_rand($reasons)],
            'initial_bp'        => $bps[array_rand($bps)],
            'initial_temp'      => $temps[array_rand($temps)],
            'initial_pulse'     => $pulses[array_rand($pulses)],
            'initial_weight'    => $weights[array_rand($weights)],
            'progress_bp'       => '120/80 mmHg',
            'progress_temp'     => '98.6 F',
            'progress_pulse'    => '80 bpm',
            'clinical_notes'    => 'Day 2 IPD Progress: Patient hemodynamically stable. Tolerating soft diet. IV fluids tapering down. No fresh complaints.',
            'discharge_summary' => $summaries[array_rand($summaries)],
            'discharge_status'  => 'Cured'
        ];
    }
}
