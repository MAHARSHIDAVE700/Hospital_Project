<?php
// cron/send_reminders.php
// This script runs automatically (e.g. daily cron job) to notify patients about their appointments tomorrow.

// If requested via browser, set text/plain header for clean output
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

require_once dirname(__DIR__) . "/includes/config.php";
require_once dirname(__DIR__) . "/includes/email_helper.php";
require_once dirname(__DIR__) . "/includes/sms_helper.php";

echo "--- NARAYAN HOSPITAL APPOINTMENT REMINDER SYSTEM ---\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Fetch tomorrow's date
$tomorrow = date('Y-m-d', strtotime('+1 day'));
echo "Querying confirmed appointments for: {$tomorrow}...\n";

$query = "
    SELECT a.appointment_id, a.appointment_date, a.appointment_time, 
           u.full_name AS patient_name, u.email, pat.phone,
           d.full_name AS doctor_name
    FROM appointments a
    JOIN patients pat ON a.patient_id = pat.patient_id
    JOIN users u ON pat.user_id = u.id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.status = 'Confirmed'
      AND a.appointment_date = ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Database query compilation failed: " . $conn->error . "\n");
}

$stmt->bind_param("s", $tomorrow);
if (!$stmt->execute()) {
    die("Query execution failed: " . $stmt->error . "\n");
}

$result = $stmt->get_result();
$sentCount = 0;

if ($result->num_rows === 0) {
    echo "No confirmed appointments found for tomorrow.\n";
} else {
    echo "Found {$result->num_rows} confirmed appointment(s).\n\n";
    
    while ($row = $result->fetch_assoc()) {
        $patientName = $row['patient_name'];
        $doctorName = $row['doctor_name'];
        $date = $row['appointment_date'];
        $time = $row['appointment_time'];
        $email = trim($row['email']);
        $phone = trim($row['phone']);
        
        echo "Processing Appointment #{$row['appointment_id']} - Patient: {$patientName}\n";
        
        // 1. Send Email Reminder via Resend
        if (!empty($email)) {
            echo "  -> Dispatching email to: {$email}... ";
            $emailStatus = EmailHelper::sendReminderEmail($email, $patientName, $doctorName, $date, $time);
            echo $emailStatus ? "SUCCESS\n" : "FAILED\n";
        } else {
            echo "  -> No email address found.\n";
        }
        
        // 2. Send SMS / WhatsApp reminder
        if (!empty($phone)) {
            echo "  -> Dispatching SMS & WhatsApp to: {$phone}... ";
            $smsStatus = SMSHelper::sendReminderNotification($phone, $patientName, $doctorName, $date, $time);
            echo $smsStatus ? "SUCCESS\n" : "FAILED\n";
        } else {
            echo "  -> No phone number found.\n";
        }
        
        echo "\n";
        $sentCount++;
    }
}

echo "Completed. Total processed reminders: {$sentCount}\n";
?>
