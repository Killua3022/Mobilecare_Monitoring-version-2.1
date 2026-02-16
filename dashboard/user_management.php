<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
if($role !== 'admin' && $role !== 'super_admin'){
    die("Access Denied: You do not have permission to view this page.");
}

$errors = []; // Store all errors

// List of sites
$sites = [
    'Marikina', 'Northeast Square', 'APP MEGA MALL', 'The Podium',
    'SM LANANG','ROBINSONS NAGA', 'APP GREENBELT 3', 'APP POWER PLANT MALL',
    'GLORIETTA 5','APP BONIFACIO HIGH STREET','ROBINSONS GALLERIA, CEBU',
    'FESTIVE WALK MALL, ILOILO','SM ILOILO (APP)','LIMKETKAI MALL, CDO',
    'VERTIS NORTH','APP SM ANNEX','APP TRINOMA','APP ROBINSONS MAGNOLIA',
    'NEWPOINT MALL','ROBINSONS LA UNION','KCC MALL, ZAMBOANGA',
    'ABREEZA MALL, DAVAO','SM LANANG','KCC MALL, COTABATO','S MAISON',
    'APP MALL OF ASIA','APP FESTIVAL MALL','LIMA ESTATE','ROBINSONS NAGA'
];

// ==========================
// Handle single row update
// ==========================
if(isset($_POST['update_user'])){
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role_update = $_POST['role'];
    $status_update = $_POST['status'];
    $site_update = $_POST['site'];

    // Check for admin limit (max 3 per site)
    if($role_update === 'admin'){
        $stmt_check = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role='admin' AND site=? AND id!=?");
        $stmt_check->bind_param("si", $site_update, $user_id);
        $stmt_check->execute();
        $admin_count = $stmt_check->get_result()->fetch_assoc()['admin_count'] ?? 0;
        if($admin_count >= 3){
            $errors[] = "The site '$site_update' already has 3 admins. Cannot assign more.";
        }
    }

    if(empty($errors)){
        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, status=?, site=? WHERE id=?");
        $stmt->bind_param("sssssi",$name,$email,$role_update,$status_update,$site_update,$user_id);
        $stmt->execute();
    }
}

// ==========================
// Handle delete
// ==========================
if(isset($_POST['delete_user'])){
    $user_id = intval($_POST['user_id']);
    $stmt = $conn->prepare("SELECT role FROM users WHERE id=?");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    if($data['role'] === 'super_admin'){
        $errors[] = "Cannot delete super admin.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i",$user_id);
        $stmt->execute();
    }
}

// ==========================
// Handle bulk update
// ==========================
if(isset($_POST['bulk_update']) && !empty($_POST['selected_users'])){
    $selected_users = $_POST['selected_users'];
    $bulk_role = $_POST['bulk_role'] ?? null;
    $bulk_status = $_POST['bulk_status'] ?? null;

    foreach($selected_users as $user_id){
        $user_id = intval($user_id);
        $update_fields = [];
        $params = [];
        $types = '';

        // Handle role update
        if($bulk_role){
            if($bulk_role === 'admin'){
                // Get user's site
                $site_result = $conn->query("SELECT site FROM users WHERE id=$user_id");
                $user_site = $site_result->fetch_assoc()['site'] ?? '';

                // Count current admins excluding this user
                $stmt_count = $conn->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role='admin' AND site=? AND id!=?");
                $stmt_count->bind_param("si", $user_site, $user_id);
                $stmt_count->execute();
                $admin_count = $stmt_count->get_result()->fetch_assoc()['admin_count'] ?? 0;

                if($admin_count >= 3){
                    $errors[] = "User ID $user_id skipped: Site '$user_site' already has 3 admins.";
                    continue; // Skip updating this user
                }
            }
            $update_fields[] = "role=?";
            $params[] = $bulk_role;
            $types .= 's';
        }

        // Handle status update
        if($bulk_status){
            $update_fields[] = "status=?";
            $params[] = $bulk_status;
            $types .= 's';
        }

        if(!empty($update_fields)){
            $params[] = $user_id;
            $types .= 'i';
            $stmt = $conn->prepare("UPDATE users SET ".implode(', ',$update_fields)." WHERE id=?");
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }
    }
}

// ==========================
// Pagination setup
// ==========================
$limit = 10; // users per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch total users count
$total_users_result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $total_users_result->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

// Fetch paginated users
$users = $conn->query("SELECT * FROM users ORDER BY site ASC, role DESC, name ASC LIMIT $limit OFFSET $offset");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Management</title>
<link rel="icon" type="image/x-icon" href="/assets/favicon_io/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon_io/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon_io/favicon-16x16.png">
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include '../layouts/navbar.php'; ?>

<div class="p-6 max-w-7xl mx-auto">

<h2 class="text-2xl font-bold mb-4">User Management</h2>

<?php if(!empty($errors)): ?>
<div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-sm">
    <?php foreach($errors as $e) echo $e."<br>"; ?>
</div>
<?php endif; ?>

<form method="POST">
<div class="my-4 flex items-center space-x-2">
    <select name="bulk_role" class="border p-1 rounded">
        <option value="">-- Change Role --</option>
        <option value="user">User</option>
        <option value="admin">Admin</option>
        <?php if($_SESSION['role']==='super_admin'): ?>
        <option value="super_admin">Super Admin</option>
        <?php endif; ?>
    </select>

    <select name="bulk_status" class="border p-1 rounded">
        <option value="">-- Change Status --</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
    </select>

    <button type="submit" name="bulk_update" class="bg-green-600 hover:bg-green-700 text-white p-1 rounded">Update Selected</button>
</div>

<table class="w-full border-collapse bg-white shadow rounded overflow-hidden">
    <thead class="bg-gray-200">
        <tr>
            <th class="p-2 border"><input type="checkbox" id="select_all"></th>
            <th class="p-2 text-left border">ID</th>
            <th class="p-2 text-left border">Name</th>
            <th class="p-2 text-left border">Email</th>
            <th class="p-2 text-left border">Role</th>
            <th class="p-2 text-left border">Status</th>
            <th class="p-2 text-left border">Site</th>
            <th class="p-2 text-left border">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while($user = $users->fetch_assoc()): ?>
        <tr class="hover:bg-gray-100">
            <td class="p-2 border">
                <input type="checkbox" name="selected_users[]" value="<?= $user['id'] ?>">
            </td>
            <form method="POST">
            <td class="p-2 border"><?= $user['id'] ?></td>
            <td class="p-2 border">
                <input name="name" value="<?= htmlspecialchars($user['name']) ?>" class="border p-1 rounded w-full">
            </td>
            <td class="p-2 border">
                <input name="email" value="<?= htmlspecialchars($user['email']) ?>" class="border p-1 rounded w-full">
            </td>
            <td class="p-2 border">
                <select name="role" class="border p-1 rounded w-full">
                    <option value="user" <?= $user['role']==='user'?'selected':'' ?>>User</option>
                    <option value="admin" <?= $user['role']==='admin'?'selected':'' ?>>Admin</option>
                    <?php if($_SESSION['role']==='super_admin'): ?>
                    <option value="super_admin" <?= $user['role']==='super_admin'?'selected':'' ?>>Super Admin</option>
                    <?php endif; ?>
                </select>
            </td>
            <td class="p-2 border">
                <select name="status" class="border p-1 rounded w-full">
                    <option value="active" <?= $user['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </td>
            <td class="p-2 border">
                <select name="site" class="border p-1 rounded w-full">
                    <?php foreach($sites as $s): ?>
                        <option value="<?= $s ?>" <?= $user['site']===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="p-2 border flex space-x-2">
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <button type="submit" name="update_user" class="bg-blue-600 hover:bg-blue-700 text-white p-1 rounded text-sm">Update</button>
                <?php if($user['role'] !== 'super_admin'): ?>
                    <button type="submit" name="delete_user" class="bg-red-600 hover:bg-red-700 text-white p-1 rounded text-sm" onclick="return confirm('Are you sure you want to permanently delete this?')">Delete</button>
                <?php endif; ?>
            </td>
            </form>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</form>

<!-- Pagination -->
<div class="mt-4 flex justify-center space-x-2">
    <?php for($i=1; $i<=$total_pages; $i++): ?>
        <a href="?page=<?= $i ?>" class="px-3 py-1 rounded <?= $i==$page?'bg-blue-600 text-white':'bg-gray-200' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>

</div>

<script>
// Select All Checkbox
document.getElementById('select_all').addEventListener('change', function() {
    let checkboxes = document.querySelectorAll('input[name="selected_users[]"]');
    checkboxes.forEach(cb => cb.checked = this.checked);
});
</script>

</body>
</html>
