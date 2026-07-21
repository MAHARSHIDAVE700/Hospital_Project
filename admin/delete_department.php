<?php

session_start();

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

include "../includes/config.php";

if(isset($_GET['id'])){

    $id=$_GET['id'];

    $stmt=$conn->prepare("DELETE FROM departments WHERE department_id=?");

    $stmt->bind_param("i",$id);

    $stmt->execute();

}

header("Location: manage_departments.php");
exit();

?>
