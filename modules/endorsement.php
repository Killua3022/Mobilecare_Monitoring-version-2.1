<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role']; // admin, super_admin, user

// Get date filters
$filter_day = $_GET['day'] ?? '';      // format: YYYY-MM-DD
$filter_month = $_GET['month'] ?? '';  // format: YYYY-MM

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
// Product Categories Mapping
// ============================
$categoryMap = [
    'iPhone' => ["IPHONE","IPAD","IPOD","IPHONE ACCS","IPAD ACCS","IPOD ACCS","BEATS","WATCH","WATCH ACCS","APPLE ID","ITUNES","ICLOUD","BACKUP"],
    'MacBook' => ["PORTABLE"],
    'iMac' => ["DESKTOP","MAC ACCS","SHUFFLE"]
];

// ============================
// Fetch Engineers
// ============================
$engQuery = $conn->query("SELECT id, name, site, status, position FROM users WHERE role='user' AND position='Engineer'");
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
// Fetch frontline tally for each engineer
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

// Prepare query dynamically
$frQueryStr = "SELECT engineer, product FROM frontline WHERE type IN ('RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)') AND engineer IS NOT NULL $dateCondition";
$stmt = $conn->prepare($frQueryStr);

if(!empty($params)){
    $stmt->bind_param("s", $params[0]);
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
// Separate available and unavailable engineers
// ============================
$availableEng = array_filter($engineers, fn($e)=>$e['status']==='active');
$unavailableEng = array_filter($engineers, fn($e)=>$e['status']!=='active');

?>
<!DOCTYPE html>
<html>
<head>
<title>Engineer Endorsement</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 10px; border: 1px solid #ddd; text-align: center; }
    th { background-color: #f3f4f6; }
    .status-active { color: green; font-weight: bold; }
    .status-inactive { color: red; font-weight: bold; }
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
        <input type="date" name="day" value="<?= htmlspecialchars($filter_day) ?>" class="border p-2 rounded">
        <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>" class="border p-2 rounded">
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
                    <td><?= htmlspecialchars($eng['name']) ?></td>
                    <td><?= htmlspecialchars($eng['site']) ?></td>
                    <td class="<?= $statusClass ?>"><?= ucfirst($eng['status']) ?></td>
                    <td class="text-red-600 font-bold"><?= $eng['iPhone'] ?></td>
                    <td class="text-blue-600 font-bold"><?= $eng['MacBook'] ?></td>
                    <td class="text-green-600 font-bold"><?= $eng['iMac'] ?></td>
                    <td class="font-semibold"><?= $total ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Engineer Availability Table -->
    <div class="bg-white shadow rounded-lg overflow-x-auto mb-6">
        <h2 class="text-xl font-semibold p-4 border-b">Engineer Availability</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-200">
                <tr>
                    <th>Engineer</th>
                    <th>Site</th>
                    <th>Status</th>
                    <th>Next Available / Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($engineers as $id => $eng): ?>
                <tr class="hover:bg-gray-50">
                    <td><?= htmlspecialchars($eng['name']) ?></td>
                    <td><?= htmlspecialchars($eng['site']) ?></td>
                    <td class="<?= ($eng['status']==='active') ? 'status-active' : 'status-inactive' ?>"><?= ucfirst($eng['status']) ?></td>
                    <td>
                        <?php if($eng['status']==='active'): ?>
                            <span class="text-green-600 font-semibold">Available</span>
                            <button class="px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 text-sm status-btn" 
                                    data-id="<?= $id ?>" data-status="inactive" type="button">
                                <i class='bx bx-user-x'></i> Mark Unavailable
                            </button>
                        <?php else: ?>
                            <span class="text-red-600 font-semibold">Unavailable</span>
                            <button class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 text-sm status-btn" 
                                    data-id="<?= $id ?>" data-status="active" type="button">
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
                location.reload(); // refresh to show updated availability
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
