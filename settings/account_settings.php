<?php
session_start();
require '../config/database.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$id = $_SESSION['user_id'];
$error = "";
$success = "";

// Fetch user
$stmt = $conn->prepare("SELECT * FROM users WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle update
if(isset($_POST['update'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $position = trim($_POST['position']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if(empty($name) || empty($email)){
        $error = "Name and Email cannot be empty.";
    } elseif(!empty($password) && $password !== $confirm_password){
        $error = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND id!=?");
        $stmt->bind_param("si", $email, $id);
        $stmt->execute();
        if($stmt->get_result()->num_rows > 0){
            $error = "Email already in use.";
        } else {
            if(!empty($password)){
                $hashed_password = password_hash($password,PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, position=? WHERE id=?");
                $stmt->bind_param("ssssi", $name, $email, $hashed_password, $position, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, position=? WHERE id=?");
                $stmt->bind_param("sssi", $name, $email, $position, $id);
            }
            if($stmt->execute()){
                $_SESSION['name'] = $name;
                $success = "Profile updated!";
                $user['name'] = $name;
                $user['email'] = $email;
                $user['position'] = $position;
            } else {
                $error = "Update failed: ".$stmt->error;
            }
        }
    }
}

// Positions
$positions = ['Engineer','Customer Service Officer','Parts Management Analyst','Specialist','Cashier','Supervisor','Manager'];
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Settings</title>
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link href="/Mobilecare_Monitoring version 2.1/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">

<?php include '../layouts/navbar1.php'; ?>

<div class="p-6 flex justify-center">

<!-- Profile Card -->
<div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl p-6 w-full max-w-lg">

<h2 class="text-2xl font-bold mb-6 text-center text-gray-800 dark:text-gray-100">Profile Settings</h2>

<?php if($error): ?>
<div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm"><?= $error ?></div>
<?php endif; ?>
<?php if($success): ?>
<div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm"><?= $success ?></div>
<?php endif; ?>

<form method="POST" class="space-y-5">

    <!-- Profile Picture -->
    <div class="flex justify-center">
        <img src="../assets/images/default_avatar.png" alt="Avatar" class="w-24 h-24 rounded-full border-2 border-blue-500 p-1">
    </div>

    <!-- Name -->
    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400">
    </div>

    <!-- Email -->
    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400">
    </div>

    <!-- Position -->
    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Position</label>
        <select name="position" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400">
            <?php foreach($positions as $pos): ?>
                <option value="<?= $pos ?>" <?= $user['position']===$pos?'selected':'' ?>><?= $pos ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Password -->
    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">New Password</label>
        <input type="password" name="password" placeholder="Leave blank to keep current password" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400">
    </div>
    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400">
    </div>

    <!-- Role (admin only) -->
    <?php if($_SESSION['role'] === 'admin' || $_SESSION['role']==='super_admin'): ?>
    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Role</label>
        <select class="border rounded p-2 w-full" disabled>
            <?php foreach($role_options as $opt): ?>
                <option value="<?= $opt ?>" <?= $user['role']===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-500 mt-1">Role editable in User Management page</p>
    </div>

    <div>
        <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Status</label>
        <select class="border rounded p-2 w-full" disabled>
            <option value="active" <?= $user['status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">Status editable in User Management page</p>
    </div>
    <?php endif; ?>

    <button type="submit" name="update" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded p-2 font-medium transition">
        Save Changes
    </button>

</form>
</div>

</div>
</body>
</html>
