
<?php
if(session_status() == PHP_SESSION_NONE){
    session_start();
}


$role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'] ?? 0;

$profile_img = '../assets/default_avatar.jpg'; // default

// Fetch user profile image if exists
$stmt = $conn->prepare("SELECT profile_image FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result()->fetch_assoc();
if(!empty($res['profile_image']) && file_exists("../uploads/profiles/" . $res['profile_image'])){
    $profile_img = "../uploads/profiles/" . $res['profile_image'];
}

?>

<nav class="bg-gray-800 text-white shadow-lg px-6 py-3 flex justify-between items-center">

    <!-- Logo / Title -->
    <div class="text-lg font-bold">
        Mobilecare Monitoring System
    </div>

    <!-- Desktop Navigation -->
    <div class="hidden md:flex items-center space-x-4">

        <a href="../dashboard/dashboard.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Dashboard</a>

        <!-- Role-based modules -->
        <?php if($role === 'user'): ?>
            <a href="../modules/frontline.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Frontline</a>
            <a href="../modules/escalations.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Escalations</a>
            <a href="../modules/inventory.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Inventory</a>
            <a href="../modules/endorsement.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Endorsement</a>
        <?php endif; ?>

        <?php if($role === 'admin' || $role === 'super_admin'): ?>
            <a href="../modules/frontline.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Frontline</a>
            <a href="../modules/escalations.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Escalations</a>
            <a href="../modules/inventory.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Inventory</a>
            <a href="../modules/analytics.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Analytics</a>
        <?php endif; ?>

       
        <!-- Theme / Profile Dropdown -->
        <div class="relative">
    <button id="profileBtn" class="flex items-center space-x-2 rounded hover:bg-gray-700 px-3 py-1 transition focus:outline-none">
        <img src="<?= $profile_img ?>" alt="Avatar" class="w-8 h-8 rounded-full border-2 border-blue-500">
        <span><?= htmlspecialchars($_SESSION['name']) ?></span>
        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    <!-- Dropdown -->
    <div id="profileDropdown" class="absolute right-0 mt-2 w-56 bg-white text-gray-800 rounded shadow-lg hidden z-50">
        <a href="../settings/account_settings.php" class="block px-4 py-2 hover:bg-gray-100">Account Settings</a>

        <?php if($role === 'admin' || $role === 'super_admin'): ?>
        <a href="../dashboard/system_settings.php" class="block px-4 py-2 hover:bg-gray-100">System Settings</a>
        <?php endif; ?>

        <?php if($role === 'super_admin'): ?>
        <a href="../dashboard/user_management.php" class="block px-4 py-2 hover:bg-gray-100">User Management</a>
        <?php endif; ?>

        <button onclick="toggleTheme()" class="block px-4 py-2 hover:bg-gray-100 w-full text-left">Theme</button>

        <?php if($role === 'admin' || $role === 'super_admin'): ?>
        <a href="../dashboard/activity_logs.php" class="block px-4 py-2 hover:bg-gray-100">Activity logs</a>
        <?php endif; ?>

        <a href="../auth/logout.php" class="block px-4 py-2 hover:bg-gray-100 text-red-600">Logout</a>
    </div>
</div>


    </div>

    <!-- Mobile Hamburger -->
    <div class="md:hidden">
        <button id="mobileMenuBtn" class="focus:outline-none">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

</nav>

<!-- Mobile Menu -->
<div id="mobileMenu" class="hidden md:hidden bg-gray-800 text-white px-6 py-3 space-y-2">

    <a href="../dashboard/dashboard.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Dashboard</a>

    <?php if($role === 'user'): ?>
        <a href="../modules/frontline.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Frontline</a>
        <a href="../modules/escalations.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Escalations</a>
        <a href="../modules/inventory.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Inventory</a>
        <a href="../modules/endorsement.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Endorsement</a>
    <?php endif; ?>

    <?php if($role === 'admin' || $role === 'super_admin'): ?>
        <a href="../modules/frontline.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Frontline</a>
        <a href="../modules/escalations.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Escalations</a>
        <a href="../modules/inventory.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Inventory</a>
        <a href="../modules/analytics.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">Analytics</a>
    <?php endif; ?>

    <?php if($role === 'super_admin'): ?>
        <a href="../dashboard/system_settings.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">System Settings</a>
        <a href="../dashboard/user_management.php" class="block px-3 py-1 rounded hover:bg-gray-700 transition">User Management</a>
    <?php endif; ?>

    <a href="../auth/logout.php" class="block px-3 py-1 rounded hover:bg-red-600 transition">Logout</a>
</div>

<script>
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    profileBtn?.addEventListener('click', () => {
        profileDropdown.classList.toggle('hidden');
    });

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    mobileMenuBtn?.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });

    window.addEventListener('click', function(e){
        if(!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)){
            profileDropdown.classList.add('hidden');
        }
    });
</script>