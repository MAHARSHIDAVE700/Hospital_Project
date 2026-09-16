<?php
/**
 * Appointment Generator Foundation Stub
 * Path: simulation/generators/AppointmentGenerator.php
 */

class AppointmentGenerator {

    /**
     * Generate synthetic appointment payload.
     *
     * @param int $patientId
     * @param int $doctorId
     * @return array
     */
    public static function generateSampleData($patientId = 1, $doctorId = 1) {
        $times = ['09:00 AM', '10:30 AM', '11:15 AM', '02:00 PM', '03:45 PM', '04:30 PM'];
        return [
            'patient_id'       => $patientId,
            'doctor_id'        => $doctorId,
            'appointment_date' => date('Y-m-d', strtotime('+' . rand(0, 5) . ' days')),
            'appointment_time' => $times[array_rand($times)],
            'fee_status'       => 'Pending',
            'status'           => 'Approved',
            'is_simulated'     => true
        ];
    }
}
