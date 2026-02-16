<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

function e($value){
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // user, admin, super_admin

// ============================
// Fetch logged-in user info
// ============================
$userStmt = $conn->prepare("SELECT name, site FROM users WHERE id=?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if(!$userData) die("User not found.");
$site = $userData['site'];
$user_name = $userData['name'];

// ============================
// Frontline types & products
// ============================
$type_of_transactions = [
    "RECEIVED (APPOINTMENT)","RECEIVED (WALK-IN)",
    "RELEASED: SAF L1 (APPOINTMENT)","RELEASED: SAF L1 (WALK-IN)",
    "RELEASED: REPLACED","RELEASED: REPAIRED",
    "RELEASED: NRS","RELEASED: NTF",
    "RELEASED: PULL OUT","RELEASED: IFS",
    "PAYMENT CONCERNS","STATUS UPDATE",
    "SERVICE INQUIRY (APPOINTMENT)","SERVICE INQUIRY (WALK-IN)",
    "TECHNICAL ASSISTANCE (APPOINTMENT)","TECHNICAL ASSISTANCE (WALK-IN)",
    "JOB ORDER (APPOINTMENT)","JOB ORDER (WALK-IN)",
    "COMPLAINT","RE-DIAGNOSIS"
];

$product_divisions = [
    "DESKTOP","PORTABLE","MAC ACCS","SHUFFLE",
    "IPHONE","IPAD","IPOD","IPHONE ACCS",
    "IPAD ACCS","IPOD ACCS","BEATS",
    "WATCH","WATCH ACCS","APPLE ID",
    "ITUNES","ICLOUD","BACKUP"
];

// ============================
// Handle POST actions
// ============================
if($_SERVER['REQUEST_METHOD'] === 'POST'){

    // --- START FRONTLINE ---
    if(isset($_POST['start'])){
        $type = $_POST['type'] ?? '';
        $product = $_POST['product'] ?? '';
        $ar = $_POST['ar'] ?? '';
        $serial_number = $_POST['serial_number'] ?? '';
        $start_time = date("H:i:s");
        $end_time = null;
        $aht = null;
        $assignedEngineer = null;

        // Assign engineer for RECEIVED types
        if(in_array($type, ['RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)'])){
            $engStmt = $conn->prepare("SELECT id, name FROM users WHERE role='user' AND position='Engineer' AND site=? AND status='active'");
            $engStmt->bind_param("s", $site);
            $engStmt->execute();
            $res = $engStmt->get_result();
            $engineers = [];
            while($row = $res->fetch_assoc()){
                $engineers[$row['id']] = $row['name'];
            }

            if(!empty($engineers)){
                $tallies = [];
                foreach($engineers as $id => $name){
                    $countRow = $conn->query("
                        SELECT COUNT(*) AS total FROM frontline 
                        WHERE engineer='". $conn->real_escape_string($name) ."' 
                        AND type IN ('RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)') 
                        AND end_time IS NULL
                    ")->fetch_assoc();
                    $tallies[$id] = $countRow['total'] ?? 0;
                }
                asort($tallies);
                $assignedEngineer = $engineers[key($tallies)];
            }
        }

        // Insert frontline record
        $stmt = $conn->prepare("INSERT INTO frontline 
            (site,start_time,end_time,aht,type,product,ar,cso,serial_number,engineer)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param(
            "ssssssssss",
            $site,
            $start_time,
            $end_time,
            $aht,
            $type,
            $product,
            $ar,
            $user_name,
            $serial_number,
            $assignedEngineer
        );
        $stmt->execute();
        header("Location: frontline.php");
        exit();
    }

    // --- STOP FRONTLINE ---
    if(isset($_POST['stop_id'])){
        $id = intval($_POST['stop_id']);
        $row = $conn->query("SELECT start_time FROM frontline WHERE id=$id")->fetch_assoc();
        $start = $row['start_time'] ?? date("H:i:s");
        $end = date("H:i:s");
        $diff = (strtotime($end) - strtotime($start))/60;
        if($diff < 0) $diff += 1440; // midnight fix

        $stmt = $conn->prepare("UPDATE frontline SET end_time=?, aht=? WHERE id=?");
        $stmt->bind_param("sdi", $end, $diff, $id);
        $stmt->execute();
        header("Location: frontline.php");
        exit();
    }
}

// ============================
// FETCH RECORDS
// ============================
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

if($role==='super_admin'){
    $stmt = $conn->prepare("
        SELECT * FROM frontline 
        WHERE is_deleted=0 AND 
        (site LIKE ? OR product LIKE ? OR type LIKE ? OR ar LIKE ? OR cso LIKE ? OR serial_number LIKE ?) 
        ORDER BY id DESC
    ");
    $stmt->bind_param("ssssss", $search_param,$search_param,$search_param,$search_param,$search_param,$search_param);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM frontline 
        WHERE site=? AND is_deleted=0 AND 
        (product LIKE ? OR type LIKE ? OR ar LIKE ? OR cso LIKE ? OR serial_number LIKE ?) 
        ORDER BY id DESC
    ");
    $stmt->bind_param("ssssss",$site,$search_param,$search_param,$search_param,$search_param,$search_param);
}

$stmt->execute();
$frontlines = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Frontline Management</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
#overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; justify-content:center; align-items:center; }
.loader { border:4px solid #3b82f6; border-top-color:transparent; border-radius:50%; width:3rem; height:3rem; animation:spin 1s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../layouts/navbar.php'; ?>

<div id="overlay" class="flex">
    <div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center gap-3">
        <div class="loader"></div>
        <span class="font-semibold text-gray-700">Please wait…</span>
    </div>
</div>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">
            Frontline <?= $role==='super_admin' ? '(All Sites)' : '— '.e($site) ?>
        </h1>
        <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            <i class='bx bx-plus'></i> Start Frontline
        </button>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-4 flex gap-2" onsubmit="document.getElementById('overlay').style.display='flex'">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search..." class="border p-2 rounded w-full">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
        <?php if($search): ?><a href="frontline.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Reset</a><?php endif; ?>
    </form>

    <!-- Frontline Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-200">
                <tr>
                    <th class="p-3">Site</th>
                    <th class="p-3">Start</th>
                    <th class="p-3">End</th>
                    <th class="p-3">AHT</th>
                    <th class="p-3">Type</th>
                    <th class="p-3">Product</th>
                    <th class="p-3">AR</th>
                    <th class="p-3">CSO</th>
                    <th class="p-3">Serial</th>
                    <th class="p-3">Engineer</th>
                    <th class="p-3">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while($f=$frontlines->fetch_assoc()): ?>
                <tr class="border-t hover:bg-gray-50">
                    <td class="p-3"><?= e($f['site']) ?></td>
                    <td class="p-3"><?= e($f['start_time']) ?></td>
                    <td class="p-3"><?= e($f['end_time']) ?></td>
                    <td class="p-3"><?= e($f['aht']) ?></td>
                    <td class="p-3"><?= e($f['type']) ?></td>
                    <td class="p-3"><?= e($f['product']) ?></td>
                    <td class="p-3"><?= e($f['ar']) ?></td>
                    <td class="p-3"><?= e($f['cso']) ?></td>
                    <td class="p-3"><?= e($f['serial_number']) ?></td>
                    <td class="p-3"><?= e($f['engineer'] ?? '-') ?></td>
                    <td class="p-3">
                        <?php if(empty($f['end_time'])): ?>
                            <form method="POST">
                                <input type="hidden" name="stop_id" value="<?= e($f['id']) ?>">
                                <button class="text-red-600"><i class='bx bx-stop-circle'></i> Stop</button>
                            </form>
                        <?php else: ?>
                            <span class="text-gray-500">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg w-full max-w-md">
        <h2 class="text-lg font-semibold mb-4">Start Frontline</h2>
        <form method="POST" onsubmit="document.getElementById('overlay').style.display='flex'" class="space-y-3">
            <select name="type" class="w-full border p-2 rounded" required>
                <option value="">Select Type</option>
                <?php foreach($type_of_transactions as $t): ?>
                    <option value="<?= e($t) ?>"><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="product" class="w-full border p-2 rounded" required>
                <option value="">Select Product</option>
                <?php foreach($product_divisions as $p): ?>
                    <option value="<?= e($p) ?>"><?= e($p) ?></option>
                <?php endforeach; ?>
            </select>

            <input type="text" name="ar" placeholder="A/R Number" required class="w-full border p-2 rounded">
            <input type="text" value="<?= e($user_name) ?>" readonly class="w-full border p-2 rounded">
            <input type="text" name="serial_number" placeholder="Serial Number" class="w-full border p-2 rounded">

            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="bg-gray-300 px-4 py-2 rounded">Cancel</button>
                <button name="start" class="bg-blue-600 text-white px-4 py-2 rounded"><i class='bx bx-play-circle'></i> Start</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(){ document.getElementById('modal').classList.remove('hidden'); }
function closeModal(){ document.getElementById('modal').classList.add('hidden'); }
</script>
</body>
</html>
