<?php
session_start();
header('Content-Type: application/json');
include "../includes/config.php";

$userId = $_SESSION['patient_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['doctor_id'] ?? null;

if (!$userId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$stmt = $conn->prepare("SELECT id, title, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 10");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
    }
}

echo json_encode($notifications);
?>
