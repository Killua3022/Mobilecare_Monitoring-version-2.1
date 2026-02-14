<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // user, admin, super_admin

// Fetch logged user info
$userStmt = $conn->prepare("SELECT name, site FROM users WHERE id=?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if(!$userData) die("User not found.");
$site = $userData['site'];
$user_name = $userData['name'];

// Type and Product lists
$type_of_transactions = ["RECEIVED (APPOINTMENT)","RECEIVED (WALK-IN)","RELEASED: SAF L1 (APPOINTMENT)","RELEASED: SAF L1 (WALK-IN)","RELEASED: REPLACED","RELEASED: REPAIRED","RELEASED: NRS","RELEASED: NTF","RELEASED: PULL OUT","RELEASED: IFS","PAYMENT CONCERNS","STATUS UPDATE","SERVICE INQUIRY (APPOINTMENT)","SERVICE INQUIRY (WALK-IN)","TECHNICAL ASSISTANCE (APPOINTMENT)","TECHNICAL ASSISTANCE (WALK-IN)","JOB ORDER (APPOINTMENT)","JOB ORDER (WALK-IN)","COMPLAINT","RE-DIAGNOSIS"];
$product_divisions = ["DESKTOP","PORTABLE","MAC ACCS","SHUFFLE","IPHONE","IPAD","IPOD","IPHONE ACCS","IPAD ACCS","IPOD ACCS","BEATS","WATCH","WATCH ACCS","APPLE ID","ITUNES","ICLOUD","BACKUP"];

// ============================
// Handle POST actions
// ============================
if($_SERVER['REQUEST_METHOD']==='POST'){

    // START a new record
    if(isset($_POST['start'])){
        $type = $_POST['type'] ?? '';
        $product = $_POST['product'] ?? '';
        $ar = $_POST['ar'] ?? '';
        $cso = $user_name;
        $serial_number = $_POST['serial_number'] ?? '';
        $aht = ''; // will calculate when stopping

        $stmt = $conn->prepare("INSERT INTO frontline (site,start_time,end_time,aht,type,product,ar,cso,serial_number) VALUES (?,?,NULL,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssss",$site, date("H:i:s"), $aht, $type, $product, $ar, $cso, $serial_number);
        $stmt->execute();
        header("Location: frontline.php");
        exit();
    }

    // STOP an ongoing record
    if(isset($_POST['stop_id'])){
        $id = intval($_POST['stop_id']);
        // Fetch start_time
        $row = $conn->query("SELECT start_time FROM frontline WHERE id=$id")->fetch_assoc();
        $start = $row['start_time'];
        $end = date("H:i:s");

        // Calculate AHT (in minutes)
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);
        $diff_min = round(($end_ts - $start_ts)/60,2);

        $stmt = $conn->prepare("UPDATE frontline SET end_time=?, aht=? WHERE id=?");
        $stmt->bind_param("sdi",$end,$diff_min,$id);
        $stmt->execute();
        header("Location: frontline.php");
        exit();
    }
}

// ============================
// Fetch frontline records
// ============================
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

if($role==='super_admin'){
    $stmt = $conn->prepare("SELECT * FROM frontline WHERE is_deleted=0 AND (site LIKE ? OR product LIKE ? OR type LIKE ? OR ar LIKE ? OR cso LIKE ? OR serial_number LIKE ?) ORDER BY id DESC");
    $stmt->bind_param("ssssss",$search_param,$search_param,$search_param,$search_param,$search_param,$search_param);
} else {
    $stmt = $conn->prepare("SELECT * FROM frontline WHERE site=? AND is_deleted=0 AND (product LIKE ? OR type LIKE ? OR ar LIKE ? OR cso LIKE ? OR serial_number LIKE ?) ORDER BY id DESC");
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
<h1 class="text-2xl font-bold">Frontline <?= $role==='super_admin' ? '(All Sites)' : '— '.htmlspecialchars($site) ?></h1>
<button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700"><i class='bx bx-plus'></i> Start Frontline</button>
</div>

<form method="GET" class="mb-4 flex gap-2" onsubmit="document.getElementById('overlay').style.display='flex'">
<input type="text" name="search" placeholder="Search Product, Type, AR, CSO, Serial" value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded w-full">
<button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
<?php if($search): ?><a href="frontline.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Reset</a><?php endif; ?>
</form>

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
<th class="p-3">Action</th>
</tr>
</thead>
<tbody>
<?php while($f=$frontlines->fetch_assoc()): ?>
<tr class="border-t hover:bg-gray-50">
<td class="p-3"><?= htmlspecialchars($f['site']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['start_time']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['end_time']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['aht']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['type']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['product']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['ar']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['cso']) ?></td>
<td class="p-3"><?= htmlspecialchars($f['serial_number']) ?></td>
<td class="p-3 flex gap-2">
<?php if(!$f['end_time']): ?>
<form method="POST" class="inline">
<input type="hidden" name="stop_id" value="<?= $f['id'] ?>">
<button class="text-red-600" type="submit"><i class='bx bx-stop-circle'></i> Stop</button>
</form>
<?php else: ?>
<span class="text-gray-600">Completed</span>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
<div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
<h2 class="text-xl font-semibold mb-4">Start Frontline</h2>
<form method="POST" id="form" onsubmit="document.getElementById('overlay').style.display='flex'" class="space-y-4">
<select name="type" id="type" class="w-full border p-2 rounded" required>
<option value="">-- Select Type --</option>
<?php foreach($type_of_transactions as $t): ?>
<option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
<?php endforeach; ?>
</select>

<select name="product" id="product" class="w-full border p-2 rounded" required>
<option value="">-- Select Product --</option>
<?php foreach($product_divisions as $p): ?>
<option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
<?php endforeach; ?>
</select>

<input type="text" name="ar" id="ar" placeholder="A/R Number" class="w-full border p-2 rounded" required>
<input type="text" name="cso" id="cso" placeholder="CSO" class="w-full border p-2 rounded" value="<?= htmlspecialchars($user_name) ?>" readonly>
<input type="text" name="serial_number" id="serial_number" placeholder="Serial Number" class="w-full border p-2 rounded">

<div class="flex justify-end gap-2">
<button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
<button type="submit" name="start" id="save_btn" class="px-4 py-2 bg-blue-600 text-white rounded"><i class='bx bx-play-circle'></i> Start</button>
</div>
</form>
</div>
</div>

<script>
function openModal(){ document.getElementById('modal').classList.remove('hidden'); document.getElementById('form').reset(); }
function closeModal(){ document.getElementById('modal').classList.add('hidden'); }
</script>
</body>
</html>
