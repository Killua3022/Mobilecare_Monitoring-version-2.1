<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

/* ===============================
   FETCH USER SITE
================================ */
$userStmt = $conn->prepare("SELECT site,name FROM users WHERE id=?");
if(!$userStmt) die("Prepare failed: ".$conn->error);
$userStmt->bind_param("i",$user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if(!$userData) die("User not found.");
$site = $userData['site'];

/* ===============================
   ADD INVENTORY
================================ */
if(isset($_POST['add_inventory'])){
    $type = $_POST['item_type'] ?? '';
    $ownership = $_POST['ownership'] ?? NULL;
    $part = $_POST['part_number'] ?? NULL;
    $serial = $_POST['serial_number'] ?? '';
    $desc = $_POST['description'] ?? '';
    $qty = intval($_POST['quantity'] ?? 1);

    if($type === "Asset" && empty($ownership)){
        die("Ownership required for Asset.");
    }

    $stmt = $conn->prepare("
        INSERT INTO inventory
        (site,item_type,ownership,part_number,serial_number,description,quantity,created_by)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    if(!$stmt) die("Prepare failed: ".$conn->error);
    $stmt->bind_param("ssssssii",$site,$type,$ownership,$part,$serial,$desc,$qty,$user_id);
    $stmt->execute();

    // Activity log
    $activity = "Added inventory: Serial=$serial, Type=$type, Qty=$qty";
    $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
    if(!$log) die("Prepare failed (log): ".$conn->error);
    $module = 'Inventory';
    $log->bind_param("isss",$user_id,$module,$activity,$site);
    $log->execute();

    header("Location: inventory.php");
    exit();
}

/* ===============================
   UPDATE INVENTORY
================================ */
if(isset($_POST['update_inventory'])){
    $id = intval($_POST['edit_id']);
    $type = $_POST['item_type'] ?? '';
    $ownership = $_POST['ownership'] ?? NULL;
    $part = $_POST['part_number'] ?? NULL;
    $serial = $_POST['serial_number'] ?? '';
    $desc = $_POST['description'] ?? '';
    $qty = intval($_POST['quantity'] ?? 1);

    if($role==='super_admin'){
        $sql = "UPDATE inventory SET item_type=?, ownership=?, part_number=?, serial_number=?, description=?, quantity=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        if(!$stmt) die("Prepare failed: ".$conn->error);
        $stmt->bind_param("ssssssi",$type,$ownership,$part,$serial,$desc,$qty,$id);
    } else {
        $sql = "UPDATE inventory SET item_type=?, ownership=?, part_number=?, serial_number=?, description=?, quantity=? WHERE id=? AND site=?";
        $stmt = $conn->prepare($sql);
        if(!$stmt) die("Prepare failed: ".$conn->error);
        $stmt->bind_param("sssssssi",$type,$ownership,$part,$serial,$desc,$qty,$id,$site);
    }
    $stmt->execute();

    // Activity log
    $activity = "Updated inventory ID $id: Serial=$serial, Type=$type, Qty=$qty";
    $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
    if(!$log) die("Prepare failed (log): ".$conn->error);
    $module = 'Inventory';
    $log->bind_param("isss",$user_id,$module,$activity,$site);
    $log->execute();

    header("Location: inventory.php");
    exit();
}

/* ===============================
   DELETE INVENTORY
================================ */
if(isset($_GET['soft_delete']) && $role==='user'){
    $id = intval($_GET['soft_delete']);
    $stmt = $conn->prepare("UPDATE inventory SET is_deleted=1 WHERE id=? AND site=?");
    if(!$stmt) die("Prepare failed: ".$conn->error);
    $stmt->bind_param("is",$id,$site);
    $stmt->execute();

    $activity = "Soft deleted inventory ID $id";
    $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
    if(!$log) die("Prepare failed (log): ".$conn->error);
    $module = 'Inventory';
    $log->bind_param("isss",$user_id,$module,$activity,$site);
    $log->execute();

    header("Location: inventory.php");
    exit();
}

if(isset($_GET['hard_delete']) && ($role==='admin' || $role==='super_admin')){
    $id = intval($_GET['hard_delete']);

    // Fetch details for activity log
    if($role==='super_admin'){
        $fetch = $conn->prepare("SELECT serial_number,item_type,quantity,site FROM inventory WHERE id=?");
        if(!$fetch) die("Prepare failed: ".$conn->error);
        $fetch->bind_param("i",$id);
    } else {
        $fetch = $conn->prepare("SELECT serial_number,item_type,quantity,site FROM inventory WHERE id=? AND site=?");
        if(!$fetch) die("Prepare failed: ".$conn->error);
        $fetch->bind_param("is",$id,$site);
    }
    $fetch->execute();
    $inv = $fetch->get_result()->fetch_assoc();
    if(!$inv) die("Inventory not found or you don't have permission.");

    // Delete
    if($role==='super_admin'){
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id=?");
        if(!$stmt) die("Prepare failed: ".$conn->error);
        $stmt->bind_param("i",$id);
    } else {
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id=? AND site=?");
        if(!$stmt) die("Prepare failed: ".$conn->error);
        $stmt->bind_param("is",$id,$site);
    }
    $stmt->execute();

    $activity = "Hard deleted inventory ID $id: Serial={$inv['serial_number']}, Type={$inv['item_type']}, Qty={$inv['quantity']}";
    $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
    if(!$log) die("Prepare failed (log): ".$conn->error);
    $module = 'Inventory';
    $log->bind_param("isss",$user_id,$module,$activity,$inv['site']);
    $log->execute();

    header("Location: inventory.php");
    exit();
}

/* ===============================
   SEARCH INVENTORY
================================ */
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

if($role==='super_admin'){
    $stmt = $conn->prepare("
        SELECT i.*, u.name AS creator
        FROM inventory i
        LEFT JOIN users u ON u.id=i.created_by
        WHERE i.is_deleted=0
        AND (i.serial_number LIKE ? OR i.description LIKE ? OR i.part_number LIKE ? OR i.item_type LIKE ? OR i.site LIKE ?)
        ORDER BY i.id DESC
    ");
    if(!$stmt) die("Prepare failed: ".$conn->error);
    $stmt->bind_param("sssss",$search_param,$search_param,$search_param,$search_param,$search_param);
} else {
    $stmt = $conn->prepare("
        SELECT i.*, u.name AS creator
        FROM inventory i
        LEFT JOIN users u ON u.id=i.created_by
        WHERE i.site=? AND i.is_deleted=0
        AND (i.serial_number LIKE ? OR i.description LIKE ? OR i.part_number LIKE ? OR i.item_type LIKE ?)
        ORDER BY i.id DESC
    ");
    if(!$stmt) die("Prepare failed: ".$conn->error);
    $stmt->bind_param("sssss",$site,$search_param,$search_param,$search_param,$search_param);
}

$stmt->execute();
$items = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Inventory</title>
<link href="../assets/css/output.css" rel="stylesheet">
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.loader {
    border: 4px solid #3b82f6;
    border-top-color: transparent;
    border-radius: 50%;
    width: 3rem;
    height: 3rem;
    animation: spin 1s linear infinite;
}
#loadingOverlay {
    display: none;
    position: fixed; inset:0;
    background: rgba(0,0,0,0.5);
    z-index: 50;
    justify-content: center;
    align-items: center;
}
</style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include '../layouts/navbar.php'; ?>

<div id="loadingOverlay" class="flex">
    <div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center gap-3">
        <div class="loader"></div>
        <span class="font-semibold text-gray-700">Please wait…</span>
    </div>
</div>

<div class="container mx-auto p-6">
<div class="flex justify-between items-center mb-6">
<h1 class="text-2xl font-bold">
Inventory <?= $role==='super_admin' ? '(All Sites)' : '— '.htmlspecialchars($site) ?>
</h1>
<button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700">
<i class='bx bx-plus'></i> Add Item
</button>
</div>

<form method="GET" class="mb-4 flex gap-2" onsubmit="showLoading()">
    <input type="text" name="search" placeholder="Search Serial, Description, Part #, Type, Site"
        value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded w-full">
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
    <?php if($search): ?>
    <a href="inventory.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Reset</a>
    <?php endif; ?>
</form>

<div class="bg-white shadow rounded-lg overflow-x-auto">
<table class="w-full text-sm text-left">
<thead class="bg-gray-200">
<tr>
<th class="p-3">Site</th><th class="p-3">Type</th><th class="p-3">Ownership</th>
<th class="p-3">Part #</th><th class="p-3">Serial</th><th class="p-3">Description</th>
<th class="p-3">Qty</th><th class="p-3">Added By</th><th class="p-3">Action</th>
</tr>
</thead>
<tbody>
<?php while($row=$items->fetch_assoc()): ?>
<tr class="border-t hover:bg-gray-50"
    data-id="<?= $row['id'] ?>"
    data-type="<?= $row['item_type'] ?>"
    data-ownership="<?= htmlspecialchars($row['ownership'] ?? '') ?>"
    data-part="<?= htmlspecialchars($row['part_number'] ?? '') ?>"
    data-serial="<?= htmlspecialchars($row['serial_number']) ?>"
    data-desc="<?= htmlspecialchars($row['description']) ?>"
    data-qty="<?= $row['quantity'] ?>"
>
<td class="p-3"><?= htmlspecialchars($row['site']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['item_type']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['ownership'] ?? '-') ?></td>
<td class="p-3"><?= htmlspecialchars($row['part_number'] ?? '-') ?></td>
<td class="p-3"><?= htmlspecialchars($row['serial_number']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['description']) ?></td>
<td class="p-3"><?= $row['quantity'] ?></td>
<td class="p-3"><?= htmlspecialchars($row['creator']) ?></td>
<td class="p-3 flex gap-2">
<?php if($role==='user'): ?>
<a href="?soft_delete=<?= $row['id'] ?>" class="text-yellow-600" onclick="return confirm('Are you sure you want to temporarily delete this?')"><i class='bx bx-trash'></i></a>
<?php endif; ?>
<?php if($role==='admin'||$role==='super_admin'): ?>
<a href="?hard_delete=<?= $row['id'] ?>" class="text-red-600" onclick="return confirm('Are you sure you want to permanently delete this?')"><i class='bx bx-x-circle'></i></a>
<?php endif; ?>
<button onclick="openEditModal(<?= $row['id'] ?>)" class="text-green-600"><i class='bx bx-edit'></i></button>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- Add Inventory Modal -->
<div id="inventoryModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
<div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
<h2 class="text-xl font-semibold mb-4">Add Inventory</h2>
<form method="POST" class="space-y-4" onsubmit="showLoading()">
<select name="item_type" id="item_type" onchange="toggleOwnership()" class="w-full border p-2 rounded" required>
<option value="">Select Type</option>
<option value="Asset">Asset</option>
<option value="Consumables">Consumables</option>
<option value="Adhesive">Adhesive</option>
<option value="Others">Others</option>
</select>
<div id="ownership_div" class="hidden">
<input type="text" name="ownership" placeholder="Ownership" class="w-full border p-2 rounded">
</div>
<input type="text" name="part_number" placeholder="Part Number" class="w-full border p-2 rounded">
<input type="text" name="serial_number" placeholder="Serial Number" class="w-full border p-2 rounded" required>
<textarea name="description" placeholder="Description" class="w-full border p-2 rounded" required></textarea>
<input type="number" name="quantity" value="1" min="1" class="w-full border p-2 rounded">
<div class="flex justify-end gap-2">
<button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
<button type="submit" name="add_inventory" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
</div>
</form>
</div>
</div>

<!-- Edit Inventory Modal -->
<div id="editInventoryModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
<div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6">
<h2 class="text-xl font-semibold mb-4">Edit Inventory</h2>
<form method="POST" id="editInventoryForm" class="space-y-4" onsubmit="showLoading()">
<input type="hidden" name="edit_id" id="edit_id">
<select name="item_type" id="edit_item_type" onchange="toggleEditOwnership()" class="w-full border p-2 rounded" required>
<option value="">Select Type</option>
<option value="Asset">Asset</option>
<option value="Consumables">Consumables</option>
<option value="Adhesive">Adhesive</option>
<option value="Others">Others</option>
</select>
<div id="edit_ownership_div" class="hidden">
<input type="text" name="ownership" id="edit_ownership" placeholder="Ownership" class="w-full border p-2 rounded">
</div>
<input type="text" name="part_number" id="edit_part_number" placeholder="Part Number" class="w-full border p-2 rounded">
<input type="text" name="serial_number" id="edit_serial_number" placeholder="Serial Number" class="w-full border p-2 rounded" required>
<textarea name="description" id="edit_description" placeholder="Description" class="w-full border p-2 rounded" required></textarea>
<input type="number" name="quantity" id="edit_quantity" value="1" min="1" class="w-full border p-2 rounded">
<div class="flex justify-end gap-2">
<button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
<button type="submit" name="update_inventory" class="px-4 py-2 bg-green-600 text-white rounded">Update</button>
</div>
</form>
</div>
</div>

<script>
function openModal(){ document.getElementById("inventoryModal").classList.remove("hidden"); }
function closeModal(){ document.getElementById("inventoryModal").classList.add("hidden"); }
function toggleOwnership(){ let type=document.getElementById("item_type").value; document.getElementById("ownership_div").classList.toggle("hidden", type!=="Asset"); }

function openEditModal(id){
    let row = document.querySelector(`tr[data-id='${id}']`);
    if(!row) return alert("Row not found");
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_item_type').value = row.dataset.type;
    document.getElementById('edit_part_number').value = row.dataset.part;
    document.getElementById('edit_serial_number').value = row.dataset.serial;
    document.getElementById('edit_description').value = row.dataset.desc;
    document.getElementById('edit_quantity').value = row.dataset.qty;
    document.getElementById('edit_ownership').value = row.dataset.ownership;
    toggleEditOwnership();
    document.getElementById('editInventoryModal').classList.remove('hidden');
}
function closeEditModal(){ document.getElementById("editInventoryModal").classList.add("hidden"); }
function toggleEditOwnership(){ let type=document.getElementById("edit_item_type").value; document.getElementById("edit_ownership_div").classList.toggle("hidden", type!=="Asset"); }
function showLoading(){ document.getElementById("loadingOverlay").style.display='flex'; }
</script>

</body>
</html>
