<?php

interface SMSProviderInterface {
    public function send($to, $message);
}

class FileSMSProvider implements SMSProviderInterface {
    private $logFile;
    
    public function __construct() {
        $this->logFile = dirname(__DIR__) . '/sms_log.txt';
    }
    
    public function send($to, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] TO: {$to} | MSG: {$message}\n----------------------------------------\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND);
        return true;
    }
}

// In the future, other providers can be configured simply by defining class e.g.:
// class TwilioSMSProvider implements SMSProviderInterface { ... }

class SMSHelper {
    private static $provider = null;
    
    private static function getProvider() {
        if (self::$provider === null) {
            // Default to File Logging provider for local simulation.
            // Easily swap out with a different provider here.
            self::$provider = new FileSMSProvider();
        }
        return self::$provider;
    }
    
    public static function sendSMS($to, $message) {
        if (empty($to)) return false;
        return self::getProvider()->send($to, $message);
    }
    
    public static function sendBookingSMS($to, $patientName, $doctorName, $date, $time, $tokenId) {
        $msg = "Dear {$patientName}, your appointment with Dr. {$doctorName} is confirmed for {$date} at {$time}. Your queue Token ID is #{$tokenId}. Thank you!";
        return self::sendSMS($to, $msg);
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
