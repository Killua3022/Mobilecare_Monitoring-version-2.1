<?php

function requireRole($roles){
    if(!isset($_SESSION['role']) || !in_array($_SESSION['role'],$roles)){
        header("Location: ../dashboard/dashboard.php");
        exit();
    }
}
?>
