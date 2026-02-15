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
$role = $_SESSION['role'];

// Fetch user data
$userStmt = $conn->prepare("SELECT name, site FROM users WHERE id=?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if(!$userData) die("User not found.");
$site = $userData['site'];

/* ===============================
   FETCH ENGINEERS FOR DROPDOWN
================================ */
if($role==='super_admin'){
    $engStmt = $conn->prepare("SELECT id, name FROM users WHERE role='user' AND position='Engineer'");
    $engStmt->execute();
} else {
    $engStmt = $conn->prepare("SELECT id, name FROM users WHERE role='user' AND position='Engineer' AND site=?");
    $engStmt->bind_param("s", $site);
    $engStmt->execute();
}
$result = $engStmt->get_result();
$engineers = [];
while($row = $result->fetch_assoc()){
    $engineers[] = $row;
}

/* ===============================
   FILE UPLOAD PATH
================================ */
$uploadDir = __DIR__ . '/uploads/';

/* ===============================
   ADD OR UPDATE ESCALATION
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $edit_id = intval($_POST['edit_id'] ?? 0);
    $ar_number = $_POST['ar_number'] ?? '';
    $engineer_number = $_POST['engineer_number'] ?? strval($user_id);
    $dispatch_id = $_POST['dispatch_id'] ?? '';
    $serial_number = $_POST['serial_number'] ?? '';
    $unit_description = $_POST['unit_description'] ?? '';
    $css_response = $_POST['css_response'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $status = $_POST['status'] ?? 'Open';
    $type = $_POST['escalation_type'] ?? (($role==='admin') ? 'Reso' : 'Normal');
    $approval_status = ($role==='super_admin' || $role==='admin') ? ($_POST['approval_status'] ?? 'Pending') : 'Pending';

    // Handle file upload
    $attachment = null;
    if(isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0){
        $filename = time().'_'.basename($_FILES['attachment']['name']);
        $targetFile = $uploadDir . $filename;
        if(move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)){
            $attachment = $filename;
        } else {
            die("File upload failed.");
        }
    }

    $esc_site = $site;
    if($edit_id > 0){
        if($role === 'super_admin'){
            $fetch = $conn->prepare("SELECT site, attachment FROM escalations WHERE id=?");
            $fetch->bind_param("i",$edit_id);
        } else {
            $fetch = $conn->prepare("SELECT site, attachment FROM escalations WHERE id=? AND site=?");
            $fetch->bind_param("is",$edit_id,$site);
        }
        $fetch->execute();
        $escData = $fetch->get_result()->fetch_assoc();
        if(!$escData) die("Escalation not found or permission denied.");
        $esc_site = $escData['site'];
        if(!$attachment) $attachment = $escData['attachment'];
    }

    if($edit_id > 0 && $type !== 'Reso') {
        if($attachment){
            $stmt = $conn->prepare("
                UPDATE escalations SET 
                ar_number=?, engineer_number=?, dispatch_id=?, serial_number=?, 
                unit_description=?, css_response=?, remarks=?, status=?, type=?, approval_status=?, attachment=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->bind_param(
                "sssssssssssi",
                $ar_number, $engineer_number, $dispatch_id, $serial_number,
                $unit_description, $css_response, $remarks, $status, $type, $approval_status,
                $attachment, $edit_id
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE escalations SET
                ar_number=?, engineer_number=?, dispatch_id=?, serial_number=?,
                unit_description=?, css_response=?, remarks=?, status=?, type=?, approval_status=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->bind_param(
                "ssssssssssi",
                $ar_number, $engineer_number, $dispatch_id, $serial_number,
                $unit_description, $css_response, $remarks, $status, $type, $approval_status,
                $edit_id
            );
        }
        if(!$stmt->execute()) die("Update failed: ".$stmt->error);
        $activity = "Updated escalation ID $edit_id: AR=$ar_number, Serial=$serial_number, Type=$type";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO escalations 
            (ar_number, engineer_number, dispatch_id, serial_number, unit_description, css_response, remarks, site, status, type, approval_status, created_by, attachment, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $stmt->bind_param(
            "sssssssssssss",
            $ar_number, $engineer_number, $dispatch_id, $serial_number,
            $unit_description, $css_response, $remarks, $site, $status, $type, $approval_status, $user_id, $attachment
        );
        if(!$stmt->execute()) die("Insert failed: ".$stmt->error);
        $activity = "Added escalation: AR=$ar_number, Serial=$serial_number, Type=$type";
    }
// ===============================
// ROLE-BASED AUTO CHAT NOTIFICATION
// ===============================
$escalation_id = $stmt->insert_id ?: $edit_id;

$notification_msg = "New escalation (#$escalation_id) added by "
    . htmlspecialchars($userData['name']) .
    ": AR=$ar_number, Serial=$serial_number, Type=$type";

// Determine receivers based on role
if($role === 'user') {

    // USER → Notify ADMIN of same site
    $notifyStmt = $conn->prepare("
        SELECT id FROM users 
        WHERE role='admin' 
        AND site=? 
        AND status='active'
    ");
    $notifyStmt->bind_param("s", $site);
    $notifyStmt->execute();
    $receivers = $notifyStmt->get_result();

} elseif($role === 'admin') {

    // ADMIN → Notify SUPER_ADMIN (all)
    $notifyStmt = $conn->prepare("
        SELECT id FROM users 
        WHERE role='super_admin' 
        AND status='active'
    ");
    $notifyStmt->execute();
    $receivers = $notifyStmt->get_result();

} else {
    $receivers = null;
}

// Send chat notification
if($receivers){
    while($admin = $receivers->fetch_assoc()){

        $receiver_id = $admin['id'];

        if($receiver_id == $user_id) continue;

        $chatStmt = $conn->prepare("
            INSERT INTO chats 
            (sender_id, receiver_id, message, file_path, created_at, status, is_read)
            VALUES (?,?,?,?,?,?,?)
        ");

        $statusChat = 'sent';
        $is_read = 0;
        $now = date('Y-m-d H:i:s');

        $chatStmt->bind_param(
            "iissssi",
            $user_id,
            $receiver_id,
            $notification_msg,
            $attachment, // reuse uploaded file if any
            $now,
            $statusChat,
            $is_read
        );

        $chatStmt->execute();
    }
}




    // Log activity
    $module = 'Escalation';
    $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
    $log->bind_param("isss",$user_id,$module,$activity,$esc_site);
    $log->execute();

    header("Location: escalations.php");
    exit();
}

/* ===============================
   SOFT DELETE
================================ */
if(isset($_GET['soft_delete']) && ($role==='user' || $role==='admin')){
    $id = intval($_GET['soft_delete']);
    $stmt = $conn->prepare("UPDATE escalations SET is_deleted=1 WHERE id=? AND site=?");
    $stmt->bind_param("is",$id,$site);
    $stmt->execute();
    if($stmt->affected_rows > 0){
        $activity = "Soft deleted escalation ID $id";
        $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
        $module = 'Escalation';
        $log->bind_param("isss",$user_id,$module,$activity,$site);
        $log->execute();
    }
    header("Location: escalations.php");
    exit();
}

/* ===============================
   HARD DELETE
================================ */
if(isset($_GET['hard_delete']) && ($role==='admin' || $role==='super_admin')){
    $id = intval($_GET['hard_delete']);
    if($role === 'super_admin'){
        $fetch = $conn->prepare("SELECT ar_number, serial_number, type, site FROM escalations WHERE id=?");
        $fetch->bind_param("i",$id);
    } else {
        $fetch = $conn->prepare("SELECT ar_number, serial_number, type, site FROM escalations WHERE id=? AND site=?");
        $fetch->bind_param("is",$id,$site);
    }
    $fetch->execute();
    $esc = $fetch->get_result()->fetch_assoc();
    if(!$esc) die("Escalation not found or permission denied.");

    if($role === 'super_admin'){
        $stmt = $conn->prepare("DELETE FROM escalations WHERE id=?");
        $stmt->bind_param("i",$id);
    } else {
        $stmt = $conn->prepare("DELETE FROM escalations WHERE id=? AND site=?");
        $stmt->bind_param("is",$id,$site);
    }
    $stmt->execute();
    if($stmt->affected_rows > 0){
        $module = 'Escalation';
        $activity = "Hard deleted escalation ID $id: AR={$esc['ar_number']}, Serial={$esc['serial_number']}, Type={$esc['type']}";
        $log = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
        $log->bind_param("isss",$user_id,$module,$activity,$esc['site']);
        $log->execute();
    }
    header("Location: escalations.php");
    exit();
}
/* ===============================
   PAGINATION SETUP
================================ */
$limit = 10; // rows per page
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Search term
$search = $_GET['search'] ?? '';
$search_param = "%$search%";

// ----------------------
// COUNT TOTAL ROWS
// ----------------------
if($role==='super_admin'){
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total FROM escalations 
        WHERE is_deleted = 0 AND (
            site LIKE ? OR ar_number LIKE ? OR serial_number LIKE ? OR remarks LIKE ? OR type LIKE ? OR status LIKE ? OR approval_status LIKE ?
        )
    ");
    $countStmt->bind_param("sssssss", $search_param,$search_param,$search_param,$search_param,$search_param,$search_param,$search_param);
} else {
    $countStmt = $conn->prepare("
        SELECT COUNT(*) as total FROM escalations
        WHERE site=? AND is_deleted=0 AND (
            ar_number LIKE ? OR serial_number LIKE ? OR remarks LIKE ? OR type LIKE ? OR status LIKE ? OR approval_status LIKE ?
        )
    ");
    $countStmt->bind_param("sssssss",$site,$search_param,$search_param,$search_param,$search_param,$search_param,$search_param);
}

$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// ----------------------
// FETCH ESCALATIONS
// ----------------------
if($role==='super_admin'){
    $stmt = $conn->prepare("
        SELECT e.*, u.name AS engineer_name, u2.role AS creator_role
        FROM escalations e
        LEFT JOIN users u ON u.id = e.engineer_number
        LEFT JOIN users u2 ON u2.id = e.created_by
        WHERE e.is_deleted = 0 AND (
            e.site LIKE ? OR 
            e.ar_number LIKE ? OR 
            e.serial_number LIKE ? OR 
            e.remarks LIKE ? OR 
            e.type LIKE ? OR 
            e.status LIKE ? OR 
            e.approval_status LIKE ?
        )
        ORDER BY e.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("sssssssii",$search_param,$search_param,$search_param,$search_param,$search_param,$search_param,$search_param,$limit,$offset);
} else {
    $stmt = $conn->prepare("
        SELECT e.*, u.name AS engineer_name, u2.role AS creator_role
        FROM escalations e
        LEFT JOIN users u ON u.id = e.engineer_number
        LEFT JOIN users u2 ON u2.id = e.created_by
        WHERE e.site=? AND e.is_deleted = 0 AND (
            e.ar_number LIKE ? OR 
            e.serial_number LIKE ? OR 
            e.remarks LIKE ? OR 
            e.type LIKE ? OR 
            e.status LIKE ? OR 
            e.approval_status LIKE ?
        )
        ORDER BY e.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssssssiii",$site,$search_param,$search_param,$search_param,$search_param,$search_param,$search_param,$limit,$offset);
}

$stmt->execute();
$escalations = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>Escalations</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.loader { border:4px solid #3b82f6; border-top-color:transparent; border-radius:50%; width:3rem; height:3rem; animation:spin 1s linear infinite; }
#loadingOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; justify-content:center; align-items:center; }

.status-approved { color: #16a34a; } 
.status-pending { color: #facc15; } 
.status-rejected { color: #dc2626; } 
.status-investigation { color: #f97316; }

</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../layouts/navbar.php'; ?>

<!-- LOADING OVERLAY -->
<div id="loadingOverlay" class="flex">
<div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center gap-3">
<div class="loader"></div>
<span class="font-semibold text-gray-700">Please wait…</span>
</div>
</div>

<div class="container mx-auto p-6">
<div class="flex justify-between items-center mb-6">
<h1 class="text-2xl font-bold">Escalations <?= $role==='super_admin' ? '(All Sites)' : '— '.htmlspecialchars($site) ?></h1>
<button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700"><i class='bx bx-plus'></i> Add Escalation</button>
</div>

<!-- SEARCH FORM -->
<form method="GET" class="mb-4 flex gap-2" onsubmit="showLoading()">
<input type="text" name="search" placeholder="Search AR, Serial, Remarks, Status" value="<?= htmlspecialchars($search) ?>" class="border p-2 rounded w-full">
<button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Search</button>
<?php if($search): ?><a href="escalations.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Reset</a><?php endif; ?>
</form>

<!-- ESCALATIONS TABLE -->
<div class="bg-white shadow rounded-lg overflow-x-auto">
<table class="w-full text-sm text-left">
<thead class="bg-gray-200">
<tr>
<th class="p-3">Site</th>
<th class="p-3">AR Number</th>
<th class="p-3">Engineer</th>
<th class="p-3">Dispatch ID</th>
<th class="p-3">Serial</th>
<th class="p-3">Unit Desc</th>
<th class="p-3">CSS Response</th>
<th class="p-3">Remarks</th>
<th class="p-3">Status</th>
<th class="p-3">Type</th>
<th class="p-3">Approval</th>
<th class="p-3">Attachment</th>
<th class="p-3">Action</th>
</tr>
</thead>
<tbody>
<?php while($row=$escalations->fetch_assoc()): 
$statusClass = '';
switch($row['approval_status']){
    case 'Approved': $statusClass='status-approved'; break;
    case 'Pending': $statusClass='status-pending'; break;
    case 'Rejected': $statusClass='status-rejected'; break;
    case 'Under Investigation': $statusClass='status-investigation'; break;
}
?>
<tr class="border-t hover:bg-gray-50" 
    data-id="<?= $row['id'] ?>" 
    data-ar="<?= htmlspecialchars($row['ar_number']) ?>"
    data-engineer="<?= htmlspecialchars($row['engineer_number']) ?>"
    data-dispatch="<?= htmlspecialchars($row['dispatch_id']) ?>"
    data-serial="<?= htmlspecialchars($row['serial_number']) ?>"
    data-unit="<?= htmlspecialchars($row['unit_description']) ?>"
    data-css="<?= htmlspecialchars($row['css_response']) ?>"
    data-remarks="<?= htmlspecialchars($row['remarks']) ?>"
    data-status="<?= $row['status'] ?>"
    data-approval="<?= $row['approval_status'] ?>"
    data-type="<?= $row['type'] ?>"
    data-creator-role="<?= $row['creator_role'] ?>"
>
<td class="p-3"><?= htmlspecialchars($row['site']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['ar_number']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['engineer_name']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['dispatch_id']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['serial_number']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['unit_description']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['css_response']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['remarks']) ?></td>
<td class="p-3"><?= htmlspecialchars($row['status']) ?></td>
<td class="p-3 font-semibold <?= $row['type'] === 'Reso' ? 'text-purple-600' : '' ?>">
    <?= htmlspecialchars($row['type']) ?>
</td>

<td class="p-3 <?= $statusClass ?> font-semibold"><?= htmlspecialchars($row['approval_status']) ?></td>
<td class="p-3">
<?php if($row['attachment']): ?>
<a href="uploads/<?= htmlspecialchars($row['attachment']) ?>" target="_blank" class="text-blue-600 underline">View</a>
<?php else: ?>-<?php endif; ?>
</td>
<td class="p-3 flex gap-2">
<?php if($role==='user'): ?>
<a href="?soft_delete=<?= $row['id'] ?>" class="text-yellow-600" onclick="return confirm('Soft delete?')"><i class='bx bx-trash'></i></a>
<?php endif; ?>
<?php if($role==='admin'||$role==='super_admin'): ?>
<a href="?hard_delete=<?= $row['id'] ?>" class="text-red-600" onclick="return confirm('Hard delete?')"><i class='bx bx-x-circle'></i></a>
<?php endif; ?>
<button type="button" onclick="openEditModal(<?= $row['id'] ?>)" class="text-green-600"><i class='bx bx-edit'></i></button>
<?php if($role==='admin' && $row['type']==='Normal'): ?>
<button type="button" onclick="makeReso(<?= $row['id'] ?>)" class="text-blue-600"><i class='bx bx-up-arrow-circle'></i></button>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
<div class="mt-4 flex justify-center gap-2">
<?php if($totalPages > 1): ?>
    <?php for($p=1;$p<=$totalPages;$p++): ?>
        <a href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"
           class="px-3 py-1 rounded <?= $p==$page?'bg-blue-600 text-white':'bg-gray-200 hover:bg-gray-300' ?>">
           <?= $p ?>
        </a>
    <?php endfor; ?>
<?php endif; ?>
</div>

</div>

<!-- MODAL HTML (ADD/EDIT) -->
<div id="escalationModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50 p-2">
<div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
<h2 class="text-xl font-semibold mb-4" id="modalTitle">Add Escalation</h2>
<form method="POST" id="escalationForm" enctype="multipart/form-data" onsubmit="showLoading()" class="space-y-4">
<input type="hidden" name="edit_id" id="edit_id">
<input type="hidden" name="escalation_type" id="escalation_type" value="Normal">
<input type="text" name="site" id="site" value="<?= htmlspecialchars($site) ?>" class="w-full border p-2 rounded bg-gray-200" readonly>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">AR Number</label>
<input type="text" name="ar_number" id="ar_number" class="w-full border p-2 rounded" required>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Engineer</label>
<select name="engineer_number" id="engineer_number" class="w-full border p-2 rounded" required>
<option value="">Select Engineer</option>
<?php foreach($engineers as $eng): ?>
<option value="<?= $eng['id'] ?>"><?= htmlspecialchars($eng['name']) ?></option>
<?php endforeach; ?>
</select>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Dispatch ID</label>
<input type="text" name="dispatch_id" id="dispatch_id" class="w-full border p-2 rounded">
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Serial Number</label>
<input type="text" name="serial_number" id="serial_number" class="w-full border p-2 rounded">
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Unit Description</label>
<input type="text" name="unit_description" id="unit_description" class="w-full border p-2 rounded">
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">CSS Response</label>
<textarea name="css_response" id="css_response" rows="3" class="w-full border p-2 rounded"></textarea>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
<textarea name="remarks" id="remarks" rows="3" class="w-full border p-2 rounded"></textarea>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
<select name="status" id="status" class="w-full border p-2 rounded">
<option value="Open">Open</option>
<option value="Closed">Closed</option>
<option value="Pending">Pending</option>
</select>
</div>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
<select name="type" id="type" class="w-full border p-2 rounded">
<option value="Normal">Normal</option>
<option value="Reso">Reso</option>
</select>
</div>

<?php if($role==='admin' || $role==='super_admin'): ?>
<div class="hidden" id="approvalWrapper">
<label class="block text-sm font-medium text-gray-700 mb-1">Approval Status</label>
<select name="approval_status" id="approval_status" class="w-full border p-2 rounded"></select>
</div>
<?php endif; ?>

<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Attachment</label>
<input type="file" name="attachment" class="w-full border p-2 rounded">
</div>

<div class="flex justify-end space-x-2 pt-4">
<button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
<button type="submit" id="submitBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
</div>
</form>
</div>
</div>

<!-- ===================== JS ===================== -->
<script>
// LOADING OVERLAY
function showLoading() {
    document.getElementById('loadingOverlay').style.display='flex';
}

// OPEN ADD MODAL
function openModal() {
    document.getElementById('escalationForm').reset();
    document.getElementById('edit_id').value='';
    document.getElementById('escalation_type').value='Normal';
    document.getElementById('modalTitle').innerText='Add Escalation';
    document.getElementById('submitBtn').innerText='Save';
    const approvalWrapper = document.getElementById('approvalWrapper');
    if(approvalWrapper) approvalWrapper.classList.add('hidden');
    document.getElementById('escalationModal').classList.remove('hidden');
    document.getElementById('escalationModal').classList.add('flex');
}

// CLOSE MODAL
function closeModal() {
    document.getElementById('escalationModal').classList.add('hidden');
}

// EDIT MODAL
function openEditModal(id){
    let row=document.querySelector('tr[data-id="'+id+'"]');
    if(!row) return;
    document.getElementById('edit_id').value=row.dataset.id;
    document.getElementById('ar_number').value=row.dataset.ar;
    document.getElementById('engineer_number').value=row.dataset.engineer;
    document.getElementById('dispatch_id').value=row.dataset.dispatch;
    document.getElementById('serial_number').value=row.dataset.serial;
    document.getElementById('unit_description').value=row.dataset.unit;
    document.getElementById('css_response').value=row.dataset.css;
    document.getElementById('remarks').value=row.dataset.remarks;
    document.getElementById('status').value=row.dataset.status;
    document.getElementById('type').value=row.dataset.type;
    document.getElementById('modalTitle').innerText='Edit Escalation';
    document.getElementById('submitBtn').innerText='Update';

    // Approval dropdown logic
    let approvalWrapper=document.getElementById('approvalWrapper');
    let approvalSelect=document.getElementById('approval_status');
    if(approvalWrapper && approvalSelect){
        approvalSelect.innerHTML='';
        approvalWrapper.classList.add('hidden');
        let creatorRole=row.dataset.creatorRole;
        let currentApproval=row.dataset.approval;
        let loggedRole='<?= $role ?>';
        if(loggedRole==='admin' && creatorRole==='user'){
            approvalWrapper.classList.remove('hidden');
            ['Pending','Approved','Rejected'].forEach(status=>{
                let option=new Option(status,status);
                if(status===currentApproval) option.selected=true;
                approvalSelect.add(option);
            });
        }
        if(loggedRole==='super_admin' && creatorRole==='admin'){
            approvalWrapper.classList.remove('hidden');
            ['Pending','Approved','Rejected','Under Investigation'].forEach(status=>{
                let option=new Option(status,status);
                if(status===currentApproval) option.selected=true;
                approvalSelect.add(option);
            });
        }
    }
    document.getElementById('escalationModal').classList.remove('hidden');
    document.getElementById('escalationModal').classList.add('flex');
}

// MAKE RESO
function makeReso(id){
    let row=document.querySelector('tr[data-id="'+id+'"]');
    if(!row) return;
    document.getElementById('escalationForm').reset();
    document.getElementById('edit_id').value='';
    document.getElementById('ar_number').value=row.dataset.ar;
    document.getElementById('engineer_number').value=row.dataset.engineer;
    document.getElementById('dispatch_id').value=row.dataset.dispatch;
    document.getElementById('serial_number').value=row.dataset.serial;
    document.getElementById('unit_description').value=row.dataset.unit;
    document.getElementById('css_response').value=row.dataset.css;
    document.getElementById('remarks').value=row.dataset.remarks;
    document.getElementById('status').value='Open';
    document.getElementById('type').value='Reso';
    document.getElementById('escalation_type').value='Reso';
    document.getElementById('modalTitle').innerText='Create Reso Escalation';
    document.getElementById('submitBtn').innerText='Save Reso';
    const approvalWrapper=document.getElementById('approvalWrapper');
    if(approvalWrapper) approvalWrapper.classList.add('hidden');
    document.getElementById('escalationModal').classList.remove('hidden');
    document.getElementById('escalationModal').classList.add('flex');
}
</script>

</body>
</html>
