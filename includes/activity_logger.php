<?php

class ActivityLogger {
    public static function log($userId, $role, $action, $details = null) {
        global $conn;
        if (!$conn) return false;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $role, $action, $details, $ip);
        return $stmt->execute();
    }
}
