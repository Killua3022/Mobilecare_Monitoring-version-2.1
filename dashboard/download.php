<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    exit("Unauthorized");
}

if(!isset($_GET['file']) || empty($_GET['file'])){
    exit("No file specified");
}

$file = basename($_GET['file']);
$path = __DIR__ . '/uploads/' . $file; // new folder

if(!file_exists($path)){
    http_response_code(404);
    exit("File not found");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'. $file .'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
