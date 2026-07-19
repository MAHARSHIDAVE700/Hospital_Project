<?php

class OTPHelper {
    public static function generateOTP($length = 6) {
        return sprintf("%0" . $length . "d", mt_rand(0, pow(10, $length) - 1));
    }

    public static function sendOTP($phone, $otp) {
        include_once __DIR__ . '/sms_helper.php';
        $message = "Your Narayan Hospital OTP code is: {$otp}. Do not share this with anyone.";
        return SMSHelper::sendSMS($phone, $message);
    }
}
