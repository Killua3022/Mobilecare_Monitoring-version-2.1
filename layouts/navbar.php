<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require '../config/database.php';

$role = $_SESSION['role'] ?? 'user';
$user_id = $_SESSION['user_id'] ?? 0;

// Default profile image
$profile_img = '../assets/images/default_avatar.png';

// Array to hold notifications
$notifications = [];
$notifCount = 0;

if ($role === 'super_admin' || $role==='admin' || $role==='user') {
    // 1️⃣ Fetch unread notifications from notifications table
    $stmt = $conn->prepare("SELECT id,message,link FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC");
    $stmt->bind_param("i",$user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($n = $res->fetch_assoc()){
        $notifications[] = $n;
    }

    // Count
    $notifCount = count($notifications);

    // 2️⃣ Check pending escalations (RESO or Normal) for the current user or site
    if($role==='super_admin'){
        $pendStmt = $conn->prepare("SELECT COUNT(*) AS pending_count FROM escalations WHERE approval_status='Pending'");
        $pendStmt->execute();
        $pendCount = $pendStmt->get_result()->fetch_assoc()['pending_count'];
    } elseif($role==='admin'){
        $site = $_SESSION['site'] ?? '';
        $pendStmt = $conn->prepare("SELECT COUNT(*) AS pending_count FROM escalations WHERE approval_status='Pending' AND site=?");
        $pendStmt->bind_param("s",$site);
        $pendStmt->execute();
        $pendCount = $pendStmt->get_result()->fetch_assoc()['pending_count'];
    } else { // user
        $pendStmt = $conn->prepare("SELECT COUNT(*) AS pending_count FROM escalations WHERE approval_status='Pending' AND created_by=?");
        $pendStmt->bind_param("i",$user_id);
        $pendStmt->execute();
        $pendCount = $pendStmt->get_result()->fetch_assoc()['pending_count'];
    }

    if($pendCount > 0){
        $notifications[] = [
            'id' => 0, // fake ID
            'message' => "You have $pendCount pending escalation(s)!",
            'link' => "../modules/escalations.php"
        ];
        $notifCount += $pendCount;
    }
}
?>


<nav class="bg-gray-800 text-white shadow-lg px-6 py-3 flex justify-between items-center">
    <div class="text-lg font-bold">
        Mobilecare Monitoring System
    </div>

    <div class="hidden md:flex items-center space-x-4">

        <a href="../dashboard/dashboard.php" class="px-3 py-1 rounded hover:bg-gray-700 transition">Dashboard</a>

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

        <!-- Super Admin Notifications -->
        <?php if($role === 'super_admin'): ?>
        <div class="relative">
            <button id="notifBtn" class="flex items-center px-3 py-1 rounded hover:bg-gray-700 transition focus:outline-none">
                <i class='bx bx-bell text-xl'></i>
                <?php if($notifCount > 0): ?>
                    <span id="notifBadge" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full"><?= $notifCount ?></span>
                <?php endif; ?>
            </button>

            <div id="notifDropdown" class="absolute right-0 mt-2 w-80 bg-white text-gray-800 rounded shadow-lg hidden z-50 max-h-96 overflow-y-auto">
                <p id="noNotif" class="p-4 text-gray-500 <?= $notifCount>0?'hidden':'' ?>">No new notifications</p>
                <div id="notifList">
                    <?php foreach($notifications as $n): ?>
                        <a href="<?= $n['link'] ?>" class="block px-4 py-2 border-b hover:bg-gray-100 transition notif-item" data-id="<?= $n['id'] ?>">
                            <?= htmlspecialchars($n['message']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Dropdown -->
        <div class="relative">
            <button id="profileBtn" class="flex items-center space-x-2 rounded hover:bg-gray-700 px-3 py-1 transition focus:outline-none">
                <img src="<?= $profile_img ?>" alt="Avatar" class="w-8 h-8 rounded-full border-2 border-blue-500">
                <span><?= htmlspecialchars($_SESSION['name']) ?></span>
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
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
    // Dropdown toggles
    const profileBtn = document.getElementById('profileBtn');
    const profileDropdown = document.getElementById('profileDropdown');
    profileBtn?.addEventListener('click', () => { profileDropdown.classList.toggle('hidden'); });

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    mobileMenuBtn?.addEventListener('click', () => { mobileMenu.classList.toggle('hidden'); });

    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    notifBtn?.addEventListener('click', () => { notifDropdown.classList.toggle('hidden'); });

    // Hide dropdowns when clicking outside
    window.addEventListener('click', function(e){
        if(profileBtn && !profileBtn.contains(e.target) && !profileDropdown.contains(e.target)){
            profileDropdown.classList.add('hidden');
        }
        if(notifBtn && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)){
            notifDropdown.classList.add('hidden');
        }
    });

    // AJAX mark notification as read
    document.querySelectorAll('.notif-item').forEach(item => {
        item.addEventListener('click', function(e){
            e.preventDefault();
            const notifId = this.dataset.id;
            const url = this.getAttribute('href');

            fetch('../modules/mark_notif_read.php', {
                method: 'POST',
                headers: { 'Content-Type':'application/x-www-form-urlencoded' },
                body: 'notif_id=' + notifId
            }).then(() => {
                this.remove(); // Remove notification from list
                const badge = document.getElementById('notifBadge');
                if(document.querySelectorAll('.notif-item').length === 0){
                    badge?.remove(); // Remove badge if no notifications left
                    document.getElementById('noNotif').classList.remove('hidden');
                } else {
                    badge.textContent = document.querySelectorAll('.notif-item').length;
                }
                window.location.href = url; // Redirect to target
            });
        });
    });
</script>
