<?php

interface SMSProviderInterface {
    public function send($to, $message);
}

class FileSMSProvider implements SMSProviderInterface {
    private $logFile;
    
    public function __construct() {
        $filePath = dirname(__DIR__) . '/sms_log.txt';
        if (is_writable(file_exists($filePath) ? $filePath : dirname($filePath))) {
            $this->logFile = $filePath;
        } else {
            $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sms_log.txt';
        }
    }
    
    public function send($to, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] TO: {$to} | MSG: {$message}\n----------------------------------------\n";
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        return true;
    }
}

// In the future, other providers can be configured simply by defining class e.g.:
// class TwilioSMSProvider implements SMSProviderInterface { ... }

class FileWhatsAppProvider {
    private $logFile;
    
    public function __construct() {
        $filePath = dirname(__DIR__) . '/whatsapp_log.txt';
        if (is_writable(file_exists($filePath) ? $filePath : dirname($filePath))) {
            $this->logFile = $filePath;
        } else {
            $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'whatsapp_log.txt';
        }
    }
    
    public function send($to, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] WHATSAPP TO: {$to} | MSG: {$message}\n----------------------------------------\n";
        @file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        return true;
    }
}

class SMSHelper {
    private static $provider = null;
    private static $waProvider = null;
    
    private static function getProvider() {
        if (self::$provider === null) {
            self::$provider = new FileSMSProvider();
        }
        return self::$provider;
    }

    private static function getWhatsAppProvider() {
        if (self::$waProvider === null) {
            self::$waProvider = new FileWhatsAppProvider();
        }
        return self::$waProvider;
    }
    
    public static function sendSMS($to, $message) {
        if (empty($to)) return false;
        return self::getProvider()->send($to, $message);
    }

    public static function sendWhatsApp($to, $message) {
        if (empty($to)) return false;
        return self::getWhatsAppProvider()->send($to, $message);
    }
    
    public static function sendBookingSMS($to, $patientName, $doctorName, $date, $time, $tokenId) {
        $msg = "Dear {$patientName}, your appointment with Dr. {$doctorName} is confirmed for {$date} at {$time}. Your queue Token ID is #{$tokenId}. Thank you!";
        self::sendSMS($to, $msg);
        self::sendWhatsApp($to, $msg);
        return true;
    }
    
    public static function sendReminderNotification($to, $patientName, $doctorName, $date, $time) {
        $msg = "Reminder: Dear {$patientName}, your appointment with Dr. {$doctorName} is scheduled for tomorrow at {$time} ({$date}). Please arrive on time.";
        self::sendSMS($to, $msg);
        self::sendWhatsApp($to, $msg);
        return true;
    }
    
    public static function sendQueuePositionAlert($to, $patientName, $doctorName, $positionsAhead) {
        $msg = "Dear {$patientName}, only {$positionsAhead} patients are ahead of you in Dr. {$doctorName}'s queue. Please proceed to the clinic room.";
        return self::sendSMS($to, $msg);
    }
    
    public static function sendDoctorStatusAlert($to, $patientName, $doctorName, $newStatus) {
        $statusMsg = "is currently delayed / on Break";
        if ($newStatus === 'Emergency') $statusMsg = "has an Emergency";
        if ($newStatus === 'Leave') $statusMsg = "is on Leave today";
        if ($newStatus === 'Offline') $statusMsg = "is currently Offline";
        
        $msg = "Dear {$patientName}, Dr. {$doctorName} {$statusMsg}. Your estimated waiting time has been adjusted. Please consult the reception desk for details.";
        return self::sendSMS($to, $msg);
    }
}
