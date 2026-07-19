<?php

class AIChatbot {
    private static $faqDatabase = [
        'opd timing' => 'Our OPD operates Monday to Saturday from 8:00 AM to 8:00 PM.',
        'timing' => 'Our hospital OPD runs from 8:00 AM to 8:00 PM daily. Emergency services are open 24x7.',
        'appointment' => 'You can book an appointment online via our Patient Portal by clicking "Book Appointment" on the home page.',
        'fee' => 'The standard OPD consultation fee is ₹200.',
        'emergency' => 'For medical emergencies, please call our 24x7 helpline +91 8140150700 or visit our emergency ward immediately.',
        'location' => 'Narayan Hospital is located at Halvad, Gujarat.',
        'doctor' => 'We have specialists in Cardiology, Orthopedics, Pediatrics, Neurology, and General Medicine.',
        'blood bank' => 'Our Blood Bank maintains stocks of all major blood groups 24x7.',
        'ambulance' => 'You can request an emergency ambulance by calling +91 8140150700 or using our online Ambulance Request tab.'
    ];

    public static function getResponse($userQuery) {
        $queryLower = strtolower(trim($userQuery));

        foreach (self::$faqDatabase as $keyword => $response) {
            if (strpos($queryLower, $keyword) !== false) {
                return $response;
            }
        }

        return "Thank you for reaching out to Narayan AI Assistant. For specific medical advice or OPD bookings, please consult our registered doctors or contact reception at +91 8140150700.";
    }
}
