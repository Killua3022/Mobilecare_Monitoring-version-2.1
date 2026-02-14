<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config/database.php';

// Check login
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$site = $_SESSION['site'] ?? '';

if($role !== 'admin' && $role !== 'super_admin'){
    die("Access Denied: You do not have permission to view this page.");
}

// ==========================
// Handle Revert Soft Delete
// ==========================
if(isset($_POST['revert_delete']) && isset($_POST['log_id'])){
    $log_id = intval($_POST['log_id']);

    // Fetch log
    $log_stmt = $conn->prepare("SELECT module, action, target_id FROM activity_logs WHERE id=?");
    $log_stmt->bind_param("i", $log_id);
    $log_stmt->execute();
    $log = $log_stmt->get_result()->fetch_assoc();

    $escalation_id = null;

    if($log && $log['module'] === 'Escalation'){
        // Get ID from target_id
        if(!empty($log['target_id'])){
            $escalation_id = intval($log['target_id']);
        } else {
            // Fallback: parse ID from action text
            if(preg_match('/Soft deleted escalation ID (\d+)/', $log['action'], $matches)){
                $escalation_id = intval($matches[1]);
            }
        }

        // Revert soft delete in escalations
        if($escalation_id){
            $revert_stmt = $conn->prepare("UPDATE escalations SET is_deleted=0 WHERE id=?");
            $revert_stmt->bind_param("i", $escalation_id);
            $revert_stmt->execute();
        }
    }
}

// ==========================
// Pagination Setup
// ==========================
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total logs
if($role === 'super_admin'){
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM activity_logs");
} else {
    $count_stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE u.site = ?
    ");
    $count_stmt->bind_param("s",$site);
}
$count_stmt->execute();
$total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $limit);

// Fetch logs
if($role === 'super_admin'){
    $stmt = $conn->prepare("
        SELECT l.*, u.name AS user_name, u.role, u.site
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii",$limit,$offset);
} else {
    $stmt = $conn->prepare("
        SELECT l.*, u.name AS user_name, u.role, u.site
        FROM activity_logs l
        LEFT JOIN users u ON u.id = l.user_id
        WHERE u.site = ?
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sii",$site,$limit,$offset);
}
$stmt->execute();
$logs = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Activity Logs</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include '../layouts/navbar.php'; ?>

<div class="container mx-auto p-6">

    <h1 class="text-3xl font-bold mb-6 text-gray-800">
        Activity Logs <?= $role==='super_admin' ? '(All Sites)' : '- '.htmlspecialchars($site) ?>
    </h1>

    <div class="bg-white p-6 rounded-xl shadow overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th class="border p-2">ID</th>
                    <th class="border p-2">User</th>
                    <th class="border p-2">Role</th>
                    <th class="border p-2">Module</th>
                    <th class="border p-2">Action</th>
                    <th class="border p-2">Site</th>
                    <th class="border p-2">Date & Time</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Revert</th>
                </tr>
            </thead>
            <tbody>
                <?php if($logs && $logs->num_rows > 0): ?>
                    <?php while($log = $logs->fetch_assoc()): ?>
                        <?php 
                            $is_deleted = false;
                            $escalation_id = null;

                            if($log['module'] === 'Escalation'){
                                // Use target_id first
                                if(!empty($log['target_id'])){
                                    $escalation_id = intval($log['target_id']);
                                } else {
                                    // Fallback: parse ID from action
                                    if(preg_match('/Soft deleted escalation ID (\d+)/', $log['action'], $matches)){
                                        $escalation_id = intval($matches[1]);
                                    }
                                }

                                if($escalation_id){
                                    $check_stmt = $conn->prepare("SELECT is_deleted FROM escalations WHERE id=?");
                                    $check_stmt->bind_param("i", $escalation_id);
                                    $check_stmt->execute();
                                    $result = $check_stmt->get_result()->fetch_assoc();
                                    if($result && $result['is_deleted'] == 1){
                                        $is_deleted = true;
                                    }
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-100 <?= $is_deleted ? 'bg-red-50 line-through' : '' ?>">
                            <td class="border p-2"><?= $log['id'] ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['user_name'] ?? '-') ?></td>
                            <td class="border p-2"><?= ucfirst($log['role'] ?? '-') ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['module'] ?? '-') ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['action'] ?? '-') ?></td>
                            <td class="border p-2"><?= htmlspecialchars($log['site'] ?? '-') ?></td>
                            <td class="border p-2"><?= $log['created_at'] ?></td>
                            <td class="border p-2"><?= $is_deleted ? 'Deleted' : 'Active' ?></td>
                            <td class="border p-2">
                                <?php if($is_deleted && $escalation_id): ?>
                                    <form method="POST">
                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                        <button type="submit" name="revert_delete" class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded text-sm">Revert</button>
                                    </form>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td class="border p-2 text-center" colspan="9">No activity logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="mt-4 flex justify-center space-x-2">
            <?php for($i=1;$i<=$total_pages;$i++): ?>
                <a href="?page=<?= $i ?>" class="px-3 py-1 rounded <?= $i==$page ? 'bg-blue-600 text-white' : 'bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>

</div>

</body>
</html>
