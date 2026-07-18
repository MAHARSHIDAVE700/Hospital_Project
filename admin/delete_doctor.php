<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_doctors.php");
    exit();
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("DELETE FROM doctors WHERE doctor_id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: manage_doctors.php?deleted=1");
    exit();
} else {
    echo "<div class='alert alert-danger'>Failed to delete doctor.</div>";
}
?>