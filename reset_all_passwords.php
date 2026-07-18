<?php
include "includes/config.php";

$newPassword = "123456";
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$sql = "UPDATE users SET password='$hashedPassword'";

if($conn->query($sql)){
    echo "<h2>✅ All passwords have been reset successfully!</h2>";
    echo "<p>New Password: <strong>123456</strong></p>";
} else {
    echo "Error: " . $conn->error;
}
?>