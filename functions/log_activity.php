<?php
function log_activity($conn, $user_id, $user_name, $role, $site, $module, $action){
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, role, site, module, action) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $user_name, $role, $site, $module, $action);
    $stmt->execute();
}
?>
