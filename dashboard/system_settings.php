<?php
session_start();
require '../config/database.php';

// Access control: Only admin or super_admin
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];
if($role !== 'admin' && $role !== 'super_admin'){
    die("Access Denied: You do not have permission to view this page.");
}

// Load settings (placeholder)
// For demo, we store theme in session
if(!isset($_SESSION['theme'])){
    $_SESSION['theme'] = 'light';
}

$success = "";

if(isset($_POST['update_settings'])){
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    $_SESSION['theme'] = $theme;
    $success = "System settings updated successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Settings</title>
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link href="/Mobilecare_Monitoring version 2.1/assets/css/output.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen <?php if($_SESSION['theme'] === 'dark') echo 'dark'; ?>">

<?php include '../layouts/navbar.php'; ?>

<div class="p-6 flex justify-center">

<div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg w-96">

<h2 class="text-2xl font-bold mb-4 text-center text-gray-800 dark:text-gray-100">System Settings</h2>

<?php if($success): ?>
<div class="bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-200 p-2 rounded mb-4 text-sm">
    <?= $success ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-4">

    <div>
        <label class="block text-sm font-medium mb-1 text-gray-800 dark:text-gray-200">Theme</label>
        <select name="theme" class="border p-2 w-full rounded">
            <option value="light" <?= $_SESSION['theme']==='light'?'selected':'' ?>>Light</option>
            <option value="dark" <?= $_SESSION['theme']==='dark'?'selected':'' ?>>Dark</option>
        </select>
    </div>

    <div>
        <button type="submit" name="update_settings" class="bg-blue-600 hover:bg-blue-700 text-white w-full p-2 rounded transition">
            Save Settings
        </button>
    </div>

</form>

</div>
</div>

</body>
</html>
