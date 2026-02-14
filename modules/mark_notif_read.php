<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require '../config/database.php';
if(isset($_POST['notif_id'])){
    $id = intval($_POST['notif_id']);
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
}
?>
