<?php
require '../config/database.php';

$error = "";
$success = "";

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

// Initialize form variables for sticky values
$name_val = "";
$email_val = "";
$site_val = "";

if(isset($_POST['signup'])){

    $name_val = trim($_POST['name']);
    $email_val = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $site_val = $_POST['site'] ?? '';
    $role = 'user'; // default role

    if(empty($name_val) || empty($email_val) || empty($password) || empty($confirm_password) || empty($site_val)){
        $error = "All fields are required.";
    } elseif($password !== $confirm_password){
        $error = "Passwords do not match.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users(name,email,password,role,status,site) VALUES(?,?,?,?,?,?)");
        $status = 'active';
        $stmt->bind_param("ssssss",$name_val,$email_val,$hashed_password,$role,$status,$site_val);

        if($stmt->execute()){
            $success = "Account created successfully. You can now login.";
            // Clear sticky values on success
            $name_val = "";
            $email_val = "";
            $site_val = "";
        } else {
            $error = "Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Signup</title>
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white p-8 rounded-xl shadow-lg w-96">

<h2 class="text-2xl font-bold text-center mb-6">Signup</h2>

<?php if($error): ?>
    <div class="bg-red-100 text-red-700 p-2 rounded mb-4 text-sm"><?= $error ?></div>
<?php endif; ?>

<?php if($success): ?>
    <div class="bg-green-100 text-green-700 p-2 rounded mb-4 text-sm"><?= $success ?></div>
<?php endif; ?>

<form method="POST">
    <input type="text" name="name" placeholder="Full Name" class="border p-2 w-full mb-4 rounded" value="<?= htmlspecialchars($name_val) ?>" required>
    <input type="email" name="email" placeholder="Email" class="border p-2 w-full mb-4 rounded" value="<?= htmlspecialchars($email_val) ?>" required>
    <input type="password" name="password" placeholder="Password" class="border p-2 w-full mb-4 rounded" required>
    <input type="password" name="confirm_password" placeholder="Confirm Password" class="border p-2 w-full mb-4 rounded" required>

    <select name="site" class="border p-2 w-full mb-4 rounded" required>
        <option value="">Select Site</option>
        <?php foreach($sites as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $site_val === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" name="signup" class="bg-green-600 hover:bg-green-700 text-white w-full p-2 rounded transition">Signup</button>
</form>

<p class="text-sm text-center mt-4">
    Already have an account? 
    <a href="login.php" class="text-blue-600 font-semibold">Login</a>
</p>

</div>

</body>
</html>
