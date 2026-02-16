<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

function e($value){ return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch user info
$userStmt = $conn->prepare("SELECT name, site FROM users WHERE id=?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if(!$userData) die("User not found.");
$site = $userData['site'];
$user_name = $userData['name'];

// Frontline types & products
$type_of_transactions = [
    "RECEIVED (APPOINTMENT)","RECEIVED (WALK-IN)","RELEASED: SAF L1 (APPOINTMENT)","RELEASED: SAF L1 (WALK-IN)",
    "RELEASED: REPLACED","RELEASED: REPAIRED","RELEASED: NRS","RELEASED: NTF",
    "RELEASED: PULL OUT","RELEASED: IFS","PAYMENT CONCERNS","STATUS UPDATE",
    "SERVICE INQUIRY (APPOINTMENT)","SERVICE INQUIRY (WALK-IN)",
    "TECHNICAL ASSISTANCE (APPOINTMENT)","TECHNICAL ASSISTANCE (WALK-IN)",
    "JOB ORDER (APPOINTMENT)","JOB ORDER (WALK-IN)","COMPLAINT","RE-DIAGNOSIS"
];

$product_divisions = [
    "DESKTOP","PORTABLE","MAC ACCS","SHUFFLE","IPHONE","IPAD","IPOD","IPHONE ACCS",
    "IPAD ACCS","IPOD ACCS","BEATS","WATCH","WATCH ACCS","APPLE ID","ITUNES","ICLOUD","BACKUP"
];

// Fetch active engineers for site (for modal dropdown)
$engineerOptions = [];
$engStmt = $conn->prepare("SELECT name FROM users WHERE role='user' AND position='Engineer' AND site=? AND status='active' ORDER BY name ASC");
$engStmt->bind_param("s", $site);
$engStmt->execute();
$res = $engStmt->get_result();
while($row = $res->fetch_assoc()){
    $engineerOptions[] = $row['name'];
}

// POST actions
if($_SERVER['REQUEST_METHOD']==='POST'){
    // START
    if(isset($_POST['start'])){
        $type = $_POST['type'];
        $product = $_POST['product'];
        $ar = $_POST['ar'];
        $serial = $_POST['serial_number'];
        $start_time = date("H:i:s");
        $end_time = null;
        $aht = null;
        $assignedEngineer = null;

        // Insert row first
        $stmt = $conn->prepare("INSERT INTO frontline (site,start_time,end_time,aht,type,product,ar,cso,serial_number,engineer) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssssss", $site, $start_time, $end_time, $aht, $type, $product, $ar, $user_name, $serial, $assignedEngineer);
        $stmt->execute();
        $last_id = $conn->insert_id;

        // Assign engineer if RECEIVED
        if(in_array($type, ['RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)'])){
            if(!empty($engineerOptions)){
                $last = $conn->query("SELECT engineer FROM frontline WHERE engineer IS NOT NULL AND engineer != '' ORDER BY id DESC LIMIT 1")->fetch_assoc();
                $lastEngineer = $last['engineer'] ?? null;
                if($lastEngineer && in_array($lastEngineer, $engineerOptions)){
                    $currentIndex = array_search($lastEngineer, $engineerOptions);
                    $nextIndex = ($currentIndex + 1) % count($engineerOptions);
                    $assignedEngineer = $engineerOptions[$nextIndex];
                } else {
                    $assignedEngineer = $engineerOptions[0];
                }

                // Update row with engineer
                $upd = $conn->prepare("UPDATE frontline SET engineer=? WHERE id=?");
                $upd->bind_param("si",$assignedEngineer,$last_id);
                $upd->execute();
            }
        }

        header("Location: frontline.php"); exit();
    }

    // STOP
    if(isset($_POST['stop_id'])){
        $id=intval($_POST['stop_id']);
        $row=$conn->query("SELECT start_time FROM frontline WHERE id=$id")->fetch_assoc();
        $start=$row['start_time']??date("H:i:s");
        $end=date("H:i:s"); $diff=(strtotime($end)-strtotime($start))/60;
        if($diff<0) $diff+=1440;
        $stmt=$conn->prepare("UPDATE frontline SET end_time=?, aht=? WHERE id=?");
        $stmt->bind_param("sdi",$end,$diff,$id); $stmt->execute();
        header("Location: frontline.php"); exit();
    }

    // DELETE
    if(isset($_POST['delete_id'])){
        $id=intval($_POST['delete_id']);
        if($role==='super_admin') $stmt=$conn->prepare("DELETE FROM frontline WHERE id=?");
        else $stmt=$conn->prepare("UPDATE frontline SET is_deleted=1 WHERE id=?");
        $stmt->bind_param("i",$id); $stmt->execute();
        header("Location: frontline.php"); exit();
    }

    // EDIT
    if(isset($_POST['edit_id'])){
        $id = intval($_POST['edit_id']);
        $type = $_POST['type'];
        $product = $_POST['product'];
        $ar = $_POST['ar'];
        $serial = $_POST['serial_number'];
        $chosenEngineer = $_POST['assignedEngineer'] ?? '';

        // Automatic assignment if left blank
        if(!$chosenEngineer && in_array($type, ['RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)'])){
            if(!empty($engineerOptions)){
                $last = $conn->query("SELECT engineer FROM frontline WHERE engineer IS NOT NULL AND engineer != '' ORDER BY id DESC LIMIT 1")->fetch_assoc();
                $lastEngineer = $last['engineer'] ?? null;
                if($lastEngineer && in_array($lastEngineer, $engineerOptions)){
                    $currentIndex = array_search($lastEngineer, $engineerOptions);
                    $nextIndex = ($currentIndex + 1) % count($engineerOptions);
                    $chosenEngineer = $engineerOptions[$nextIndex];
                } else {
                    $chosenEngineer = $engineerOptions[0];
                }
            }
        }

        $stmt = $conn->prepare("UPDATE frontline SET type=?, product=?, ar=?, serial_number=?, engineer=? WHERE id=?");
        $stmt->bind_param("sssssi",$type,$product,$ar,$serial,$chosenEngineer,$id);
        $stmt->execute();

        header("Location: frontline.php"); exit();
    }
}

// FETCH
$search = $_GET['search']??'';
$search_param="%$search%";
if($role==='super_admin'){
    $stmt=$conn->prepare("SELECT * FROM frontline WHERE is_deleted=0 AND (site LIKE ? OR product LIKE ? OR type LIKE ? OR ar LIKE ? OR cso LIKE ? OR serial_number LIKE ?) ORDER BY id DESC");
    $stmt->bind_param("ssssss",$search_param,$search_param,$search_param,$search_param,$search_param,$search_param);
} else {
    $stmt=$conn->prepare("SELECT * FROM frontline WHERE site=? AND is_deleted=0 AND (product LIKE ? OR type LIKE ? OR ar LIKE ? OR cso LIKE ? OR serial_number LIKE ?) ORDER BY id DESC");
    $stmt->bind_param("ssssss",$site,$search_param,$search_param,$search_param,$search_param,$search_param);
}
$stmt->execute(); $frontlines=$stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Frontline Management</title>
<link rel="icon" type="image/x-icon" href="/assets/favicon_io/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon_io/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon_io/favicon-16x16.png">
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
#overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:50;justify-content:center;align-items:center;}
.loader{border:4px solid #3b82f6;border-top-color:transparent;border-radius:50%;width:3rem;height:3rem;animation:spin 1s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../layouts/navbar.php'; ?>
<div id="overlay" class="flex"><div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center gap-3"><div class="loader"></div><span class="font-semibold text-gray-700">Please wait…</span></div></div>

<div class="container mx-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Frontline <?= $role==='super_admin'?'(All Sites)':'— '.e($site) ?></h1>
        <button onclick="openModal('start')" class="bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2 hover:bg-blue-700"><i class='bx bx-plus'></i> Start Frontline</button>
    </div>

    <form method="GET" class="mb-4 flex gap-2" onsubmit="document.getElementById('overlay').style.display='flex'">
        <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search..." class="border p-2 rounded w-full">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
        <?php if($search): ?><a href="frontline.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Reset</a><?php endif;?>
    </form>

    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-200"><tr>
                <th class="p-3">Site</th><th class="p-3">Start</th><th class="p-3">End</th><th class="p-3">AHT</th><th class="p-3">Type</th>
                <th class="p-3">Product</th><th class="p-3">AR</th><th class="p-3">CSO</th><th class="p-3">Serial</th>
                <th class="p-3">Engineer</th><th class="p-3">Action</th>
            </tr></thead>
            <tbody>
            <?php while($f=$frontlines->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
                <td class="p-3"><?= e($f['site']) ?></td>
                <td class="p-3"><?= e($f['start_time']) ?></td>
                <td class="p-3"><?= e($f['end_time']?:'-') ?></td>
                <td class="p-3"><?= e($f['aht']?:'-') ?></td>
                <td class="p-3"><?= e($f['type']) ?></td>
                <td class="p-3"><?= e($f['product']) ?></td>
                <td class="p-3"><?= e($f['ar']) ?></td>
                <td class="p-3"><?= e($f['cso']) ?></td>
                <td class="p-3"><?= e($f['serial_number']) ?></td>
                <td class="p-3"><?= e($f['engineer']?:'-') ?></td>
                <td class="p-3 flex flex-col gap-1">
                    <?php if(empty($f['end_time'])||$f['end_time']==''): ?>
                    <form method="POST" class="flex gap-1">
                        <input type="hidden" name="stop_id" value="<?= $f['id'] ?>">
                        <button class="text-red-600 hover:text-red-800 flex items-center gap-1"><i class='bx bx-stop-circle'></i> Stop</button>
                    </form>
                    <?php endif;?>
                    <button onclick="openModal('edit',<?= $f['id'] ?>,'<?= e($f['type']) ?>','<?= e($f['product']) ?>','<?= e($f['ar']) ?>','<?= e($f['serial_number']) ?>','<?= e($f['engineer']) ?>')" class="text-blue-600 hover:text-blue-800 flex items-center gap-1"><i class='bx bx-edit'></i> Edit</button>
                    <form method="POST" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="delete_id" value="<?= $f['id'] ?>">
                        <button class="text-gray-700 hover:text-black flex items-center gap-1"><i class='bx bx-trash'></i> Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile;?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="modal" class="fixed inset-0 hidden z-50 bg-black/50 flex items-center justify-center">
    <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md mx-2 sm:mx-0">
        <h2 class="text-xl font-semibold mb-4" id="modalTitle">Start Frontline</h2>
        <form method="POST" id="modalForm" class="space-y-3">
            <input type="hidden" name="edit_id" id="edit_id">
            <select name="type" id="modal_type" class="w-full border p-2 rounded" required>
                <option value="">Select Type</option>
                <?php foreach($type_of_transactions as $t): ?><option value="<?= e($t) ?>"><?= e($t) ?></option><?php endforeach;?>
            </select>
            <select name="product" id="modal_product" class="w-full border p-2 rounded" required>
                <option value="">Select Product</option>
                <?php foreach($product_divisions as $p): ?><option value="<?= e($p) ?>"><?= e($p) ?></option><?php endforeach;?>
            </select>
            <input type="text" name="ar" id="modal_ar" placeholder="A/R Number" required class="w-full border p-2 rounded">
            <input type="text" value="<?= e($user_name) ?>" readonly class="w-full border p-2 rounded bg-gray-100">
            <input type="text" name="serial_number" id="modal_serial" placeholder="Serial Number" class="w-full border p-2 rounded">
            <select name="assignedEngineer" id="modal_engineer" class="w-full border p-2 rounded">
                <option value="">-- Auto Assign --</option>
                <?php foreach($engineerOptions as $eng): ?>
                    <option value="<?= e($eng) ?>"><?= e($eng) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeModal()" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                <button type="submit" id="modalBtn" class="bg-blue-600 text-white px-4 py-2 rounded flex items-center gap-2"><i class='bx bx-play-circle'></i> <span id="modalBtnText">Start</span></button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(mode,id=null,type='',product='',ar='',serial='',engineer=''){
    const modal=document.getElementById('modal'); 
    modal.classList.remove('hidden'); modal.classList.add('flex');

    if(mode==='edit'){
        document.getElementById('modalTitle').innerText='Edit Frontline';
        document.getElementById('modal_type').value=type;
        document.getElementById('modal_product').value=product;
        document.getElementById('modal_ar').value=ar;
        document.getElementById('modal_serial').value=serial;
        document.getElementById('modal_engineer').value = engineer || '';
        document.getElementById('edit_id').value=id;
        document.getElementById('modalBtnText').innerText='Save';
        document.getElementById('modalBtn').removeAttribute('name');
    } else {
        document.getElementById('modalTitle').innerText='Start Frontline';
        document.getElementById('modalForm').reset();
        document.getElementById('edit_id').value='';
        document.getElementById('modal_engineer').value = '';
        document.getElementById('modalBtnText').innerText='Start';
        document.getElementById('modalBtn').setAttribute('name','start');
    }
}
function closeModal(){ const modal=document.getElementById('modal'); modal.classList.add('hidden'); modal.classList.remove('flex'); }
</script>
</body>
</html>
