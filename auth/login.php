<?php
session_start();
require '../config/database.php';

$error = "";

// Redirect if already logged in
if(isset($_SESSION['user_id'])){
    header("Location: ../dashboard/dashboard.php");
    exit();
}

if(isset($_POST['login'])){

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Basic validation
    if(empty($email) || empty($password)){
        $error = "Please fill in all fields.";
    }else{

        // Prepared statement (SECURE)
        $stmt = $conn->prepare("SELECT * FROM users WHERE email=? AND status='active'");
        $stmt->bind_param("s",$email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if($user && password_verify($password,$user['password'])){

            // Store session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['site'] = $user['site']; 
            
            header("Location: ../dashboard/dashboard.php");
            exit();

        }else{
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>

<!-- Tailwind Local Build -->
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link href="/Mobilecare_Monitoring version 2.1/assets/css/output.css" rel="stylesheet">
</head>

<body class="bg-gray-100 flex items-center justify-center h-screen">

<div class="bg-white p-8 rounded-xl shadow-lg w-96">

    <h2 class="text-2xl font-bold text-center mb-6">Login</h2>

    <!-- Error Message -->
    <?php if($error): ?>
        <div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-sm">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">

        <input 
            type="email" 
            name="email" 
            placeholder="Email"
            class="border p-2 w-full mb-4 rounded"
            required
        >

        <input 
            type="password" 
            name="password" 
            placeholder="Password"
            class="border p-2 w-full mb-4 rounded"
            required
        >

        <button 
            type="submit"
            name="login"
            class="bg-blue-600 hover:bg-blue-700 text-white w-full p-2 rounded transition"
        >
            Login
        </button>

    </form>

    <p class="text-sm text-center mt-4">
        Don't have an account?
        <a href="signup.php" class="text-blue-600 font-semibold">Signup</a>
    </p>

</div>

</body>
</html>
