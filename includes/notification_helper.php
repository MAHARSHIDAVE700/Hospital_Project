<?php

class NotificationHelper {
    public static function add($userId, $title, $message) {
        global $conn;
        if (!$conn || empty($userId)) return false;
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, is_read) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iss", $userId, $title, $message);
        return $stmt->execute();
    }
    
    public static function getUnreadCount($userId) {
        global $conn;
        if (!$conn || empty($userId)) return 0;
        
        $stmt = $conn->prepare("SELECT COUNT(*) AS unread FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            return (int)$row['unread'];
        }
        return 0;
    }
}
