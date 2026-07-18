<?php
include "includes/config.php";

$password = password_hash("123456", PASSWORD_DEFAULT);

$sql = "UPDATE users SET password='$password'";

if($conn->query($sql)){
    echo "Password Updated Successfully!";
} else {
    echo "Error: " . $conn->error;
}
?>