<?php

require_once __DIR__ . '/neon_db.php';

$host = "ep-blue-boat-auib76so-pooler.c-10.us-east-1.aws.neon.tech";
$user = "neondb_owner";
$pass = "npg_1RJYwpx8SvVK";
$db = "neondb";

try {
    $conn = new NeonDB($host, $user, $pass, $db);
} catch (Exception $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

require_once __DIR__ . '/activity_logger.php';
?>
