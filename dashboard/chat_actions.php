<?php
session_start();
require '../config/database.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if($action==='get_messages'){
    $other_id = intval($_GET['user_id']);
    $stmt = $conn->prepare("SELECT * FROM chats WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC");
    $stmt->bind_param("iiii",$user_id,$other_id,$other_id,$user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while($row = $result->fetch_assoc()){
        $messages[] = $row;
    }

    // Mark as read
    $update = $conn->prepare("UPDATE chats SET is_read=1, status='delivered' WHERE receiver_id=? AND sender_id=? AND is_read=0");
    $update->bind_param("ii",$user_id,$other_id);
    $update->execute();

    echo json_encode($messages);
}

if($action==='send'){
    $receiver_id = intval($_POST['receiver_id']);
    $message = $_POST['message'] ?? '';
    $file_path = null;

    if(isset($_FILES['file']) && $_FILES['file']['error']===0){
        $target_dir = "../uploads/";
        if(!is_dir($target_dir)) mkdir($target_dir,0777,true);
        $file_name = time().'_'.basename($_FILES['file']['name']);
        move_uploaded_file($_FILES['file']['tmp_name'],$target_dir.$file_name);
        $file_path = $file_name;
    }

    $stmt = $conn->prepare("INSERT INTO chats(sender_id,receiver_id,message,file_path) VALUES(?,?,?,?)");
    $stmt->bind_param("iiss",$user_id,$receiver_id,$message,$file_path);
    if($stmt->execute()){
        echo json_encode(['status'=>'success']);
    } else {
        echo json_encode(['status'=>'error']);
    }
}
?>
