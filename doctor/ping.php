<?php
// doctor/ping.php
session_start();

if (!isset($_SESSION['doctor_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "unauthorized"]);
    exit();
}

include_once "../includes/config.php";

$userID = $_SESSION['doctor_id'];

// Get doctor email
$userQuery = $conn->query("SELECT email FROM users WHERE id='$userID'");
$user = $userQuery ? $userQuery->fetch_assoc() : null;
$email = $user ? $user['email'] : '';

if (!empty($email)) {
    $conn->query("UPDATE doctors SET last_active_at = CURRENT_TIMESTAMP WHERE LOWER(email)=LOWER('$email')");
} else {
    $docName = $conn->real_escape_string($_SESSION['doctor_name'] ?? '');
    if (!empty($docName)) {
        $conn->query("UPDATE doctors SET last_active_at = CURRENT_TIMESTAMP WHERE LOWER(full_name) LIKE LOWER('%$docName%')");
    }
}

header('Content-Type: application/json');
echo json_encode(["status" => "active", "timestamp" => date('Y-m-d H:i:s')]);
?>
