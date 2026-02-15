<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

if($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin'){
    die("Access Denied.");
}

$module = $_GET['module'] ?? '';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="'.$module.'_'.date('Ymd').'.csv"');
$output = fopen('php://output', 'w');

if($module === 'users') {
    fputcsv($output, ['ID','Name','Role','Status','Created At']);
    $result = $conn->query("SELECT id, name, role, status, created_at FROM users");
    while($row = $result->fetch_assoc()) fputcsv($output, $row);

} elseif($module === 'escalations') {
    fputcsv($output, ['ID','AR Number','Engineer Number','Dispatch ID','Serial Number','Unit Description','CSS Response','Remarks','Site','Status','Type','Approval Status','Approval By','Created By','Created At']);
    $result = $conn->query("SELECT id, ar_number, engineer_number, dispatch_id, serial_number, unit_description, css_response, remarks, site, status, type, approval_status, approval_by, created_by, created_at FROM escalations");
    while($row = $result->fetch_assoc()) fputcsv($output, $row);

} elseif($module === 'inventory') {
    fputcsv($output, ['ID','Site','Item Type','Ownership','Part Number','Serial Number','Description','Quantity','Created By','Created At']);
    $result = $conn->query("SELECT id, site, item_type, ownership, part_number, serial_number, description, quantity, created_by, created_at FROM inventory WHERE is_deleted=0");
    while($row = $result->fetch_assoc()) fputcsv($output, $row);

} elseif($module === 'frontline') {
    fputcsv($output, ['ID','Site','Start Time','End Time','AHT','Type','Product','AR','CSO','Serial Number','Created At']);
    $result = $conn->query("SELECT id, site, start_time, end_time, aht, type, product, ar, cso, serial_number, created_at FROM frontline WHERE is_deleted=0");
    while($row = $result->fetch_assoc()) fputcsv($output, $row);

} elseif($module === 'cso_aht') {
    fputcsv($output, ['Month','CSO','Total AHT']);
    $result = $conn->query("
        SELECT 
            cso,
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(aht) as total_aht
        FROM frontline
        WHERE is_deleted = 0
        GROUP BY cso, month
        ORDER BY month ASC, cso ASC
    ");
    while($row = $result->fetch_assoc()){
        fputcsv($output, [$row['month'], $row['cso'], $row['total_aht']]);
    }

} else {
    die("Invalid module.");
}

fclose($output);
exit;
