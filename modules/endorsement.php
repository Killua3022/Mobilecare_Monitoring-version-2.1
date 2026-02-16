<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

// Safe output helper
function e($value){
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // admin, super_admin, user

// Get date filters
$filter_day = $_GET['day'] ?? '';      // YYYY-MM-DD
$filter_month = $_GET['month'] ?? '';  // YYYY-MM

// ============================
// Handle AJAX status update
// ============================
if(isset($_POST['ajax']) && $_POST['ajax']==='update_status'){
    $eng_id = intval($_POST['id']);
    $new_status = $_POST['status'] === 'active' ? 'active' : 'inactive';
    $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
    $stmt->bind_param("si", $new_status, $eng_id);
    if($stmt->execute()){
        echo json_encode(['success'=>true,'status'=>$new_status]);
    } else {
        echo json_encode(['success'=>false]);
    }
    exit;
}

// ============================
// Get user site if not super_admin
// ============================
if($role !== 'super_admin') {
    $stmt = $conn->prepare("SELECT site FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $siteData = $stmt->get_result()->fetch_assoc();
    $siteFilter = $siteData['site'] ?? '';
} else {
    $siteFilter = null; // super_admin sees all sites
}

// ============================
// Product Categories Mapping
// ============================
$categoryMap = [
    'iPhone' => ["IPHONE","IPAD","IPOD","IPHONE ACCS","IPAD ACCS","IPOD ACCS","BEATS","WATCH","WATCH ACCS","APPLE ID","ITUNES","ICLOUD","BACKUP"],
    'MacBook' => ["PORTABLE"],
    'iMac' => ["DESKTOP","MAC ACCS","SHUFFLE"]
];

// ============================
// Fetch engineers per site
// ============================
if($role === 'super_admin') {
    $engQuery = $conn->query("SELECT id, name, site, status, position FROM users WHERE role='user' AND position='Engineer'");
} else {
    $stmt = $conn->prepare("SELECT id, name, site, status, position FROM users WHERE role='user' AND position='Engineer' AND site=?");
    $stmt->bind_param("s", $siteFilter);
    $stmt->execute();
    $engQuery = $stmt->get_result();
}

$engineers = [];
while($row = $engQuery->fetch_assoc()){
    $engineers[$row['id']] = [
        'name'=>$row['name'],
        'site'=>$row['site'],
        'status'=>$row['status'],
        'iPhone'=>0,
        'MacBook'=>0,
        'iMac'=>0
    ];
}

// ============================
// Fetch frontline tally per engineer per site
// ============================
$dateCondition = "";
$params = [];
$types = ['RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)'];

if($filter_day){
    $dateCondition = "AND DATE(start_time) = ?";
    $params[] = $filter_day;
} elseif($filter_month){
    $dateCondition = "AND DATE_FORMAT(start_time,'%Y-%m') = ?";
    $params[] = $filter_month;
}

// Filter by site if not super_admin
$siteSQL = '';
if($role !== 'super_admin' && $siteFilter){
    $siteSQL = "AND site=?";
    $params[] = $siteFilter;
}

// Prepare query
$frQueryStr = "SELECT engineer, product FROM frontline WHERE type IN ('RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)') AND engineer IS NOT NULL $dateCondition $siteSQL";
$stmt = $conn->prepare($frQueryStr);
if(!empty($params)){
    $typesStr = str_repeat("s", count($params));
    $stmt->bind_param($typesStr, ...$params);
}
$stmt->execute();
$frResult = $stmt->get_result();

while($row = $frResult->fetch_assoc()){
    foreach($engineers as $id => $eng){
        if($eng['name'] === $row['engineer']){
            $product = $row['product'];
            foreach($categoryMap as $cat => $products){
                if(in_array($product, $products)){
                    $engineers[$id][$cat]++;
                    break;
                }
            }
            break;
        }
    }
}

// ============================
// Separate available/unavailable engineers
// ============================
$availableEng = array_filter($engineers, fn($e)=>$e['status']==='active');
$unavailableEng = array_filter($engineers, fn($e)=>$e['status']!=='active');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Engineer Endorsement</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
    th { background-color: #f3f4f6; }
    .status-active { color: green; font-weight: bold; }
    .status-inactive { color: red; font-weight: bold; }
    .btn { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.875rem; cursor: pointer; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php include '../layouts/navbar.php'; ?>

<div class="container mx-auto p-6">

    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Engineer Endorsement & Schedule</h1>
        <span class="text-sm text-gray-600">Auto tally updates from frontline</span>
    </div>

    <!-- Filters -->
    <form method="GET" class="flex gap-2 mb-4">
        <input type="date" name="day" value="<?= e($filter_day) ?>" class="border p-2 rounded">
        <input type="month" name="month" value="<?= e($filter_month) ?>" class="border p-2 rounded">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
        <a href="endorsement.php" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Reset</a>
    </form>

    <!-- Engineer Tally Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto mb-6">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Engineer</th>
                    <th>Site</th>
                    <th>Status</th>
                    <th>iPhone / iOS & Accessories</th>
                    <th>MacBook</th>
                    <th>iMac</th>
                    <th>Total Tasks</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($engineers as $id => $eng): 
                $total = $eng['iPhone'] + $eng['MacBook'] + $eng['iMac'];
                $statusClass = ($eng['status']==='active') ? 'status-active' : 'status-inactive';
            ?>
                <tr class="hover:bg-gray-50">
                    <td><?= e($eng['name']) ?></td>
                    <td><?= e($eng['site']) ?></td>
                    <td class="<?= $statusClass ?>"><?= ucfirst(e($eng['status'])) ?></td>
                    <td class="text-red-600 font-bold"><?= $eng['iPhone'] ?></td>
                    <td class="text-blue-600 font-bold"><?= $eng['MacBook'] ?></td>
                    <td class="text-green-600 font-bold"><?= $eng['iMac'] ?></td>
                    <td class="font-semibold"><?= $total ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Engineer Availability -->
    <div class="bg-white shadow rounded-lg overflow-x-auto mb-6">
        <h2 class="text-xl font-semibold p-4 border-b">Engineer Availability</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>Engineer</th>
                    <th>Site</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($engineers as $id => $eng): ?>
                <tr class="hover:bg-gray-50">
                    <td><?= e($eng['name']) ?></td>
                    <td><?= e($eng['site']) ?></td>
                    <td class="<?= ($eng['status']==='active') ? 'status-active' : 'status-inactive' ?>"><?= ucfirst(e($eng['status'])) ?></td>
                    <td>
                        <?php if($eng['status']==='active'): ?>
                            <span class="text-green-600 font-semibold">Available</span>
                            <button class="btn bg-red-600 text-white hover:bg-red-700 status-btn" 
                                    data-id="<?= $id ?>" data-status="inactive">
                                <i class='bx bx-user-x'></i> Mark Unavailable
                            </button>
                        <?php else: ?>
                            <span class="text-red-600 font-semibold">Unavailable</span>
                            <button class="btn bg-green-600 text-white hover:bg-green-700 status-btn" 
                                    data-id="<?= $id ?>" data-status="active">
                                <i class='bx bx-user-check'></i> Mark Available
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
// AJAX status update
document.querySelectorAll('.status-btn').forEach(btn=>{
    btn.addEventListener('click', function(){
        const engId = this.dataset.id;
        const newStatus = this.dataset.status;

        fetch('endorsement.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=update_status&id=${engId}&status=${newStatus}`
        })
        .then(res=>res.json())
        .then(data=>{
            if(data.success){
                location.reload();
            } else {
                alert('Failed to update status.');
            }
        })
        .catch(err=>alert('Error: '+err));
    });
});
</script>
</body>
</html>
