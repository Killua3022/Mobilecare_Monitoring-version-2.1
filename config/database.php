<?php

$host = "localhost";
$user = "root";
$pass = "";
$db   = "Mobilecare_Monitoring";

$conn = new mysqli($host,$user,$pass,$db);

if($conn->connect_error){
    die("Database Connection Failed");
}
