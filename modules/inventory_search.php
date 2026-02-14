<?php
require '../config/database.php';
session_start();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$site = $_SESSION['site'] ?? '';

$search = $_GET['search'] ?? '';
if($role==='super_admin'){
    $stmt = $conn->prepare("
        SELECT i.*, u.name AS creator
        FROM inventory i
        LEFT JOIN users u ON u.id=i.created_by
        WHERE i.is_deleted=0 AND 
        (i.serial_number LIKE ? OR i.description LIKE ? OR i.part_number LIKE ? OR i.item_type LIKE ?)
        ORDER BY i.id DESC
    ");
    $search_param = "%$search%";
    $stmt->bind_param("ssss",$search_param,$search_param,$search_param,$search_param);
} else {
    $stmt = $conn->prepare("
        SELECT i.*, u.name AS creator
        FROM inventory i
        LEFT JOIN users u ON u.id=i.created_by
        WHERE i.site=? AND i.is_deleted=0 AND
        (i.serial_number LIKE ? OR i.description LIKE ? OR i.part_number LIKE ? OR i.item_type LIKE ?)
        ORDER BY i.id DESC
    ");
    $search_param = "%$search%";
    $stmt->bind_param("sssss",$site,$search_param,$search_param,$search_param,$search_param);
}
$stmt->execute();
$items = $stmt->get_result();

while($row = $items->fetch_assoc()){
    echo "<tr class='border-t hover:bg-gray-50'>";
    echo "<td class='p-3'>".htmlspecialchars($row['site'])."</td>";
    echo "<td class='p-3'>".htmlspecialchars($row['item_type'])."</td>";
    echo "<td class='p-3'>".htmlspecialchars($row['ownership'] ?? '-')."</td>";
    echo "<td class='p-3'>".htmlspecialchars($row['part_number'] ?? '-')."</td>";
    echo "<td class='p-3'>".htmlspecialchars($row['serial_number'])."</td>";
    echo "<td class='p-3'>".htmlspecialchars($row['description'])."</td>";
    echo "<td class='p-3'>".$row['quantity']."</td>";
    echo "<td class='p-3'>".htmlspecialchars($row['creator'])."</td>";
    echo "<td class='p-3 flex gap-2'>";
    if($role==='user') echo "<a href='?soft_delete=".$row['id']."' class='text-yellow-600'><i class='bx bx-trash'></i></a>";
    if($role==='admin'||$role==='super_admin') echo "<a href='?hard_delete=".$row['id']."' class='text-red-600'><i class='bx bx-x-circle'></i></a>";
    echo "</td></tr>";
}
?>
