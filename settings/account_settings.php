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

// Define roles and positions
$role_options = ['user','admin','super_admin'];
$positions = ['Engineer','Customer Service Officer','Parts Management Analyst','Specialist','Cashier','Supervisor','Manager'];

// Handle update
if(isset($_POST['update'])){
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $position = trim($_POST['position']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Handle profile image
    if(isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0){
        $allowed_ext = ['jpg','jpeg','png','gif'];
        $file_name = $_FILES['profile_image']['name'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if(!in_array($ext, $allowed_ext)){
            $error = "Invalid image format. Allowed: jpg, jpeg, png, gif.";
        } else {
            $new_file_name = "user_".$id."_".time().".".$ext;
            $upload_dir = "../uploads/profiles/";
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $target_file = $upload_dir.$new_file_name;
            if(move_uploaded_file($file_tmp, $target_file)){
                $stmt_img = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
                $stmt_img->bind_param("si", $new_file_name, $id);
                $stmt_img->execute();
                $user['profile_image'] = $new_file_name;
            } else {
                $error = "Failed to upload image.";
            }
        }
    }

    if(empty($error)){
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

                    if(isset($_POST['role']) && $_SESSION['role']==='super_admin'){
                        $new_role = $_POST['role'];
                        if(in_array($new_role, $role_options)){
                            $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
                            $stmt->bind_param("si", $new_role, $id);
                            $stmt->execute();
                            $user['role'] = $new_role;
                        }
                    }
                } else {
                    $error = "Update failed: ".$stmt->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Account Settings</title>
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen">

<?php include '../layouts/navbar1.php'; ?>

<div class="p-6 flex justify-center items-start md:items-center min-h-screen">

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-8 w-full max-w-xl transition-all duration-300">

        <h2 class="text-3xl font-bold mb-6 text-center text-gray-800 dark:text-gray-100">Profile Settings</h2>

        <?php if($error): ?>
        <div class="bg-red-100 dark:bg-red-200 text-red-700 dark:text-red-800 p-3 rounded mb-4 text-sm transition">
            <?= $error ?>
        </div>
        <?php endif; ?>
        <?php if($success): ?>
        <div class="bg-green-100 dark:bg-green-200 text-green-700 dark:text-green-800 p-3 rounded mb-4 text-sm transition">
            <?= $success ?>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-5">

            <!-- Profile Picture -->
            <div class="flex flex-col items-center">
                <img id="previewImg" src="../uploads/profiles/<?= $user['profile_image'] ?? 'default_avatar.jpg' ?>" alt="Avatar" class="w-28 h-28 rounded-full border-2 border-blue-500 p-1 object-cover mb-2 transition-all">
                <label class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm transition">
                    Upload New Image
                    <input type="file" name="profile_image" accept="image/*" class="hidden" onchange="previewImage(event)">
                </label>
            </div>

            <!-- Name -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Full Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400 dark:bg-gray-700 dark:text-gray-100 transition">
            </div>

            <!-- Email -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400 dark:bg-gray-700 dark:text-gray-100 transition">
            </div>

            <!-- Position -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Position</label>
                <select name="position" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400 dark:bg-gray-700 dark:text-gray-100 transition">
                    <?php foreach($positions as $pos): ?>
                        <option value="<?= $pos ?>" <?= $user['position']===$pos?'selected':'' ?>><?= $pos ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">New Password</label>
                <input type="password" name="password" placeholder="Leave blank to keep current password" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400 dark:bg-gray-700 dark:text-gray-100 transition">
            </div>
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm new password" class="border rounded p-2 w-full focus:ring-2 focus:ring-blue-400 dark:bg-gray-700 dark:text-gray-100 transition">
            </div>

            <!-- Role -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Role</label>
                <select name="role" class="border rounded p-2 w-full dark:bg-gray-700 dark:text-gray-100 transition" <?= $_SESSION['role']!=='super_admin'?'disabled':'' ?>>
                    <?php foreach($role_options as $opt): ?>
                        <option value="<?= $opt ?>" <?= $user['role']===$opt?'selected':'' ?>><?= ucfirst($opt) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if($_SESSION['role']!=='super_admin'): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Role editable only by Super Admin</p>
                <?php endif; ?>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-gray-700 dark:text-gray-200 font-medium mb-1">Status</label>
                <select class="border rounded p-2 w-full dark:bg-gray-700 dark:text-gray-100 transition" disabled>
                    <option value="active" <?= $user['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $user['status']==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Status editable in User Management page</p>
            </div>

            <button type="submit" name="update" class="w-full bg-blue-600 hover:bg-blue-700 text-white rounded p-3 font-medium transition">
                Save Changes
            </button>

        </form>
    </div>
</div>

<script>
    function previewImage(event){
        const reader = new FileReader();
        reader.onload = function(){
            document.getElementById('previewImg').src = reader.result;
        }
        reader.readAsDataURL(event.target.files[0]);
    }
</script>

</body>
</html>
