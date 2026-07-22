<?php

interface EmailProviderInterface {
    public function send($to, $subject, $bodyHtml, $attachments = []);
}

class StandardEmailProvider implements EmailProviderInterface {
    private $logFile;
    
    public function __construct() {
        $filePath = dirname(__DIR__) . '/email_log.txt';
        if (is_writable(file_exists($filePath) ? $filePath : dirname($filePath))) {
            $this->logFile = $filePath;
        } else {
            $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'email_log.txt';
        }
    }
    
    public function send($to, $subject, $bodyHtml, $attachments = []) {
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Narayan Hospital <noreply@narayanhospital.com>" . "\r\n";
        
        $attachmentNames = [];
        foreach ($attachments as $att) {
            $attachmentNames[] = $att['filename'];
        }
        $attStr = count($attachmentNames) > 0 ? " | ATTACHMENTS: " . implode(', ', $attachmentNames) : "";

        // Log locally for debugging/testing
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] TO: {$to} | SUBJECT: {$subject}{$attStr}\nBODY:\n{$bodyHtml}\n----------------------------------------\n";
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        
        // Attempt native PHP mail send (suppressing errors if SMTP is unconfigured locally)
        @mail($to, $subject, $bodyHtml, $headers);
        return true;
    }
}

class ResendEmailProvider implements EmailProviderInterface {
    private $apiKey;
    private $fromEmail;
    private $logFile;

    public function __construct($apiKey, $fromEmail = 'onboarding@resend.dev') {
        $this->apiKey = $apiKey;
        $this->fromEmail = $fromEmail;
        $filePath = dirname(__DIR__) . '/email_log.txt';
        if (is_writable(file_exists($filePath) ? $filePath : dirname($filePath))) {
            $this->logFile = $filePath;
        } else {
            $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'email_log.txt';
        }
    }

    public function send($to, $subject, $bodyHtml, $attachments = []) {
        $timestamp = date('Y-m-d H:i:s');
        $url = 'https://api.resend.com/emails';
        
        $data = [
            'from'    => $this->fromEmail,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $bodyHtml
        ];

        if (!empty($attachments)) {
            $data['attachments'] = $attachments;
        }

        $attachmentNames = [];
        foreach ($attachments as $att) {
            $attachmentNames[] = $att['filename'];
        }
        $attStr = count($attachmentNames) > 0 ? " | ATTACHMENTS: " . implode(', ', $attachmentNames) : "";

        // Log locally for transparency
        $logEntry = "[{$timestamp}] (RESEND SENDING) TO: {$to} | FROM: {$this->fromEmail} | SUBJECT: {$subject}{$attStr}\n";
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($data)
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            $errEntry = "[{$timestamp}] (RESEND ERROR) cURL Error: {$error}\n----------------------------------------\n";
            @file_put_contents($this->logFile, $errEntry, FILE_APPEND);
            return false;
        } else {
            $resDecoded = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && isset($resDecoded['id'])) {
                $successEntry = "[{$timestamp}] (RESEND SUCCESS) ID: {$resDecoded['id']}\n----------------------------------------\n";
                @file_put_contents($this->logFile, $successEntry, FILE_APPEND);
                return true;
            } else {
                $failEntry = "[{$timestamp}] (RESEND FAILED) HTTP Code: {$httpCode} | Response: {$response}\n----------------------------------------\n";
                @file_put_contents($this->logFile, $failEntry, FILE_APPEND);
                return false;
            }
        }
    }
}

class EmailHelper {
    private static $provider = null;
    
    private static function getProvider() {
        if (self::$provider === null) {
            $apiKey = getenv('RESEND_API_KEY');
            if (empty($apiKey) && defined('RESEND_API_KEY')) {
                $apiKey = RESEND_API_KEY;
            }
            
            if (!empty($apiKey)) {
                $fromEmail = getenv('RESEND_FROM_EMAIL');
                if (empty($fromEmail) && defined('RESEND_FROM_EMAIL')) {
                    $fromEmail = RESEND_FROM_EMAIL;
                }
                if (empty($fromEmail)) {
                    $fromEmail = 'onboarding@resend.dev';
                }
                self::$provider = new ResendEmailProvider($apiKey, $fromEmail);
            } else {
                self::$provider = new StandardEmailProvider();
            }
        }
        return self::$provider;
    }
    
    public static function sendEmail($to, $subject, $bodyHtml, $attachments = []) {
        if (empty($to)) return false;
        return self::getProvider()->send($to, $subject, $bodyHtml, $attachments);
    }
    
    public static function getTemplate($title, $patientName, $contentHtml) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 20px; }
                .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
                .body { padding: 30px; color: #333333; line-height: 1.6; }
                .footer { background: #e9ecef; padding: 15px; text-align: center; font-size: 12px; color: #6c757d; }
                .btn { display: inline-block; padding: 10px 20px; color: white; background: #0d6efd; text-decoration: none; border-radius: 5px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='header'>
                    <h2>🏥 Narayan Hospital</h2>
                </div>
                <div class='body'>
                    <h3>{$title}</h3>
                    <p>Dear <strong>{$patientName}</strong>,</p>
                    {$contentHtml}
                    <p>If you have any questions, please contact our support desk.</p>
                    <p>Best regards,<br><strong>Narayan Hospital Team</strong></p>
                </div>
                <div class='footer'>
                    &copy; " . date('Y') . " Narayan Hospital OPD Management System. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
    }

    public static function sendBookingConfirmation($to, $patientName, $doctorName, $date, $time, $appointmentId) {
        $subject = "Appointment Booking Confirmation - Narayan Hospital";
        $content = "
            <p>Your appointment has been successfully requested. Here are the details:</p>
            <ul>
                <li><strong>Appointment ID:</strong> #{$appointmentId}</li>
                <li><strong>Doctor:</strong> Dr. {$doctorName}</li>
                <li><strong>Date:</strong> {$date}</li>
                <li><strong>Time:</strong> {$time}</li>
                <li><strong>Status:</strong> Pending Approval</li>
            </ul>
            <p>You will receive an update once the hospital admin approves your appointment.</p>
        ";
        $bodyHtml = self::getTemplate("Appointment Received", $patientName, $content);
        return self::sendEmail($to, $subject, $bodyHtml);
    }

    public static function sendAppointmentApproval($to, $patientName, $doctorName, $date, $time, $tokenNumber) {
        $subject = "Appointment Approved! - Narayan Hospital";
        $content = "
            <p>Great news! Your appointment has been <strong>Approved & Confirmed</strong> by the administration.</p>
            <ul>
                <li><strong>Token Number:</strong> <span style='font-size:18px; color:#0d6efd;'><strong>{$tokenNumber}</strong></span></li>
                <li><strong>Doctor:</strong> Dr. {$doctorName}</li>
                <li><strong>Date:</strong> {$date}</li>
                <li><strong>Time:</strong> {$time}</li>
            </ul>
            <p>Please arrive at least 15 minutes before your scheduled slot.</p>
        ";
        $bodyHtml = self::getTemplate("Appointment Approved", $patientName, $content);
        return self::sendEmail($to, $subject, $bodyHtml);
    }

    public static function sendAppointmentCancellation($to, $patientName, $doctorName, $date, $time) {
        $subject = "Appointment Status Update - Narayan Hospital";
        $content = "
            <p>We regret to inform you that your appointment with <strong>Dr. {$doctorName}</strong> on <strong>{$date}</strong> at <strong>{$time}</strong> has been <strong>Cancelled</strong>.</p>
            <p>Please visit our portal to reschedule or choose a different time slot.</p>
        ";
        $bodyHtml = self::getTemplate("Appointment Cancelled", $patientName, $content);
        return self::sendEmail($to, $subject, $bodyHtml);
    }

    public static function sendPasswordResetLink($to, $userName, $resetUrl) {
        $subject = "Password Reset Request - Narayan Hospital";
        $content = "
            <p>We received a request to reset your password for your Narayan Hospital account.</p>
            <p>Click the link below to set a new password. This link is valid for 1 hour:</p>
            <p><a href='{$resetUrl}' class='btn' style='color:#ffffff;'>Reset My Password</a></p>
            <p style='font-size:12px; color:#777;'>Or copy and paste this link in your browser: <br>{$resetUrl}</p>
            <p>If you did not request a password reset, you can safely ignore this email.</p>
        ";
        $bodyHtml = self::getTemplate("Reset Password", $userName, $content);
        return self::sendEmail($to, $subject, $bodyHtml);
    }

    public static function sendVerificationEmail($to, $userName, $verifyUrl) {
        $subject = "Verify Your Email Address - Narayan Hospital";
        $content = "
            <p>Thank you for creating an account with Narayan Hospital!</p>
            <p>Please confirm your email address by clicking the button below:</p>
            <p><a href='{$verifyUrl}' class='btn' style='color:#ffffff;'>Verify Email Address</a></p>
            <p style='font-size:12px; color:#777;'>Or copy and paste this link in your browser: <br>{$verifyUrl}</p>
        ";
        $bodyHtml = self::getTemplate("Email Verification", $userName, $content);
        return self::sendEmail($to, $subject, $bodyHtml);
    }

    public static function sendReminderEmail($to, $patientName, $doctorName, $date, $time) {
        $subject = "Appointment Reminder - Narayan Hospital";
        $content = "
            <p>This is a friendly reminder that you have an appointment scheduled for tomorrow.</p>
            <ul>
                <li><strong>Doctor:</strong> Dr. {$doctorName}</li>
                <li><strong>Date:</strong> {$date}</li>
                <li><strong>Time:</strong> {$time}</li>
            </ul>
            <p>Please arrive at least 15 minutes before your scheduled slot. If you need to reschedule, please log in to the patient portal or contact support.</p>
        ";
        $bodyHtml = self::getTemplate("Appointment Tomorrow", $patientName, $content);
        return self::sendEmail($to, $subject, $bodyHtml);
    }
}
