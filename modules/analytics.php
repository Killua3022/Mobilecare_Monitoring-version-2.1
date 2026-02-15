<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

if($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin'){
    die("Access Denied.");
}

/* ================= USER DATA ================= */
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$activeUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='active'")->fetch_assoc()['total'];
$inactiveUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='inactive'")->fetch_assoc()['total'];

$roleData = [];
$roleQuery = $conn->query("SELECT role, COUNT(*) as total FROM users GROUP BY role");
while($row = $roleQuery->fetch_assoc()){
    $roleData[$row['role']] = $row['total'];
}

/* ================= ESCALATION DATA ================= */
$totalEscalations = $conn->query("SELECT COUNT(*) as total FROM escalations")->fetch_assoc()['total'];
$resoCount = $conn->query("SELECT COUNT(*) as total FROM escalations WHERE type='RESO'")->fetch_assoc()['total'];
$normalCount = $conn->query("SELECT COUNT(*) as total FROM escalations WHERE type='Normal'")->fetch_assoc()['total'];

/* ================= INVENTORY DATA ================= */
$totalInventory = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE is_deleted=0")->fetch_assoc()['total'];
$totalQuantity = $conn->query("SELECT SUM(quantity) as total FROM inventory WHERE is_deleted=0")->fetch_assoc()['total'] ?? 0;
$lowStock = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE quantity <=5 AND is_deleted=0")->fetch_assoc()['total'];

/* ================= FRONTLINE DATA ================= */
$totalFrontline = $conn->query("SELECT COUNT(*) as total FROM frontline")->fetch_assoc()['total'];

/* ================= ENGINEER DATA ================= */
$categoryMap = [
    'iPhone'=>["IPHONE","IPAD","IPOD","IPHONE ACCS","IPAD ACCS","IPOD ACCS","BEATS","WATCH","WATCH ACCS","APPLE ID","ITUNES","ICLOUD","BACKUP"],
    'MacBook'=>["PORTABLE"],
    'iMac'=>["DESKTOP","MAC ACCS","SHUFFLE"]
];

$engQuery = "SELECT id, name, site, status FROM users WHERE role='user' AND position='Engineer'";
$engResult = $conn->query($engQuery);

$engineers = [];
$engineerNameIndex = [];
while($row = $engResult->fetch_assoc()){
    $engineers[$row['id']] = [
        'name'=>$row['name'],
        'site'=>$row['site'],
        'status'=>$row['status'],
        'iPhone'=>0,
        'MacBook'=>0,
        'iMac'=>0
    ];
    $engineerNameIndex[$row['name']] = $row['id'];
}

$frQuery = "SELECT engineer, product FROM frontline WHERE type IN ('RECEIVED (APPOINTMENT)','RECEIVED (WALK-IN)') AND engineer IS NOT NULL";
$frResult = $conn->query($frQuery);

while($row = $frResult->fetch_assoc()){
    if(isset($engineerNameIndex[$row['engineer']])){
        $id = $engineerNameIndex[$row['engineer']];
        foreach($categoryMap as $cat=>$products){
            if(in_array($row['product'],$products)){
                $engineers[$id][$cat]++;
                break;
            }
        }
    }
}

$activeEng = count(array_filter($engineers, fn($e)=>$e['status']==='active'));
$inactiveEng = count($engineers) - $activeEng;

/* ================= CSO AHT DATA (Pagination + Site Filter) ================= */
$perPage = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

$filterSite = isset($_GET['site']) && !empty($_GET['site']) ? $_GET['site'] : '';
$siteResult = $conn->query("SELECT DISTINCT site FROM frontline WHERE is_deleted=0 ORDER BY site ASC");
$sites = [];
while($row = $siteResult->fetch_assoc()) $sites[] = $row['site'];

$countQuery = "SELECT COUNT(*) as total FROM frontline WHERE is_deleted=0";
if($filterSite) $countQuery .= " AND site='".$conn->real_escape_string($filterSite)."'";
$totalRows = $conn->query($countQuery)->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

$csoQuery = "
    SELECT cso, site, DATE_FORMAT(created_at, '%Y-%m') as month, SUM(aht) as total_aht
    FROM frontline
    WHERE is_deleted=0
";
if($filterSite) $csoQuery .= " AND site='".$conn->real_escape_string($filterSite)."'";
$csoQuery .= " GROUP BY month, cso, site
               ORDER BY month ASC, cso ASC, site ASC
               LIMIT $offset, $perPage";

$csoAHT = [];
$result = $conn->query($csoQuery);
while($row = $result->fetch_assoc()){
    $csoAHT[$row['month']][] = [
        'cso'=>$row['cso'],
        'site'=>$row['site'],
        'total_aht'=>(float)$row['total_aht']
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
<title>System Analytics</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: #f8fafc; }
.card-hover:hover { transform: translateY(-4px); transition: .3s ease; }
table { border-collapse: collapse; width: 100%; }
th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
th { background-color: #f3f4f6; }
</style>
</head>
<body class="min-h-screen">

<?php include '../layouts/navbar.php'; ?>

<div class="p-8 max-w-7xl mx-auto">
<!-- HEADER -->
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Analytics Dashboard</h1>
        <p class="text-gray-500">System Overview & Performance Metrics</p>
    </div>
    <div class="text-gray-600">
        <i class='bx bx-time-five'></i> <?= date("F d, Y") ?>
    </div>
</div>

<!-- SUMMARY CARDS -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-10">
    <div class="bg-white p-6 rounded-2xl shadow card-hover border-l-4 border-blue-500 flex flex-col justify-between">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-500 text-sm">Users</p>
                <p class="text-3xl font-bold"><?= $totalUsers ?></p>
            </div>
            <i class='bx bx-user text-4xl text-blue-500'></i>
        </div>
        <a href="download.php?module=users" class="mt-4 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-blue-100 text-blue-600 text-sm font-semibold hover:bg-blue-200">
            <i class='bx bx-download'></i> Download
        </a>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow card-hover border-l-4 border-red-500 flex flex-col justify-between">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-500 text-sm">Escalations</p>
                <p class="text-3xl font-bold"><?= $totalEscalations ?></p>
            </div>
            <i class='bx bx-error-circle text-4xl text-red-500'></i>
        </div>
        <a href="download.php?module=escalations" class="mt-4 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-red-100 text-red-600 text-sm font-semibold hover:bg-red-200">
            <i class='bx bx-download'></i> Download
        </a>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow card-hover border-l-4 border-green-500 flex flex-col justify-between">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-500 text-sm">Inventory</p>
                <p class="text-3xl font-bold"><?= $totalInventory ?></p>
                <p class="text-xs text-gray-400">Qty: <?= $totalQuantity ?></p>
            </div>
            <i class='bx bx-box text-4xl text-green-500'></i>
        </div>
        <a href="download.php?module=inventory" class="mt-4 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-green-100 text-green-600 text-sm font-semibold hover:bg-green-200">
            <i class='bx bx-download'></i> Download
        </a>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow card-hover border-l-4 border-purple-500 flex flex-col justify-between">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-500 text-sm">Frontline Records</p>
                <p class="text-3xl font-bold"><?= $totalFrontline ?></p>
            </div>
            <i class='bx bx-briefcase text-4xl text-purple-500'></i>
        </div>
        <a href="download.php?module=frontline" class="mt-4 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-purple-100 text-purple-600 text-sm font-semibold hover:bg-purple-200">
            <i class='bx bx-download'></i> Download
        </a>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow card-hover border-l-4 border-teal-500 flex flex-col justify-between">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-gray-500 text-sm">Engineers</p>
                <p class="text-3xl font-bold"><?= count($engineers) ?></p>
            </div>
            <i class='bx bx-user-check text-4xl text-teal-500'></i>
        </div>
        <a href="endorsement.php" class="mt-4 inline-flex items-center gap-1 px-3 py-1 rounded-full bg-teal-100 text-teal-600 text-sm font-semibold hover:bg-teal-200">
            <i class='bx bx-link'></i> Manage
        </a>
    </div>
</div>

<!-- CHARTS -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- User Roles -->
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-semibold mb-4 text-gray-700 flex items-center">
            <i class='bx bx-pie-chart-alt mr-2'></i> User Roles
        </h3>
        <canvas id="roleChart" class="h-48"></canvas>
    </div>

    <!-- Escalation Doughnut -->
    <div class="bg-white p-6 rounded-2xl shadow flex flex-col items-center gap-4">
        <p class="text-gray-500 font-semibold text-sm uppercase tracking-wide">Escalations (Last 30 Days)</p>
        <div class="w-40 h-40">
            <canvas id="typeChart"></canvas>
        </div>
        <div class="flex gap-6 mt-2">
            <div class="flex flex-col items-center">
                <span class="text-green-600 text-2xl font-bold"><?= $resoCount ?></span>
                <span class="text-gray-500 text-sm">RESO (<?= $totalEscalations>0 ? round($resoCount/$totalEscalations*100) : 0 ?>%)</span>
            </div>
            <div class="flex flex-col items-center">
                <span class="text-red-600 text-2xl font-bold"><?= $normalCount ?></span>
                <span class="text-gray-500 text-sm">Normal (<?= $totalEscalations>0 ? round($normalCount/$totalEscalations*100) : 0 ?>%)</span>
            </div>
        </div>
    </div>

    <!-- Engineer Status Doughnut -->
    <div class="bg-white p-6 rounded-2xl shadow flex flex-col items-center gap-4">
        <p class="text-gray-500 font-semibold text-sm uppercase tracking-wide">Engineer Status</p>
        <div class="w-40 h-40">
            <canvas id="engineerStatusChart"></canvas>
        </div>
        <div class="flex gap-6 mt-2">
            <div class="flex flex-col items-center">
                <span class="text-green-600 text-2xl font-bold"><?= $activeEng ?></span>
                <span class="text-gray-500 text-sm">Active</span>
            </div>
            <div class="flex flex-col items-center">
                <span class="text-red-600 text-2xl font-bold"><?= $inactiveEng ?></span>
                <span class="text-gray-500 text-sm">Inactive</span>
            </div>
        </div>
    </div>
</div>

<!-- LOWER SECTION -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-10">
    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-semibold text-gray-700 mb-3">Active vs Inactive Users</h3>
        <p class="text-green-600 font-bold">Active: <?= $activeUsers ?></p>
        <p class="text-red-600 font-bold">Inactive: <?= $inactiveUsers ?></p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-semibold text-gray-700 mb-3">Low Stock Alert</h3>
        <p class="text-red-600 text-2xl font-bold"><?= $lowStock ?></p>
        <p class="text-gray-400 text-sm">Items with quantity ≤ 5</p>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow">
        <h3 class="font-semibold text-gray-700 mb-3">System Health</h3>
        <p class="text-gray-600">All modules operational</p>
        <p class="text-green-500 font-bold mt-2">✔ Stable</p>
    </div>
</div>

<!-- CSO AHT TABLE -->
<div class="bg-white p-6 rounded-2xl shadow mt-10">
    <div class="flex justify-between items-center mb-4">
        <h3 class="font-semibold text-gray-700">Monthly CSO AHT Summary</h3>
        <form method="GET" class="flex items-center gap-2">
            <select name="site" class="border rounded px-2 py-1 text-sm">
                <option value="">All Sites</option>
                <?php foreach($sites as $siteOption): ?>
                    <option value="<?= htmlspecialchars($siteOption) ?>" <?= $siteOption==$filterSite?'selected':'' ?>><?= htmlspecialchars($siteOption) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-3 py-1 bg-indigo-100 text-indigo-600 rounded hover:bg-indigo-200 text-sm">Filter</button>
        </form>
        <a href="download.php?module=cso_aht<?= $filterSite?'&site='.urlencode($filterSite):'' ?>" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-indigo-100 text-indigo-600 text-sm font-semibold hover:bg-indigo-200">
            <i class='bx bx-download'></i> Download
        </a>
    </div>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th>CSO</th>
                <th>Site</th>
                <th>Total AHT</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($csoAHT as $month => $entries): ?>
            <?php foreach($entries as $entry): ?>
            <tr>
                <td><?= $month ?></td>
                <td><?= htmlspecialchars($entry['cso']) ?></td>
                <td><?= htmlspecialchars($entry['site']) ?></td>
                <td><?= number_format($entry['total_aht'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="mt-4 flex justify-center gap-2">
        <?php for($i=1;$i<=$totalPages;$i++): ?>
            <a href="?page=<?= $i ?>&site=<?= urlencode($filterSite) ?>" class="px-3 py-1 border rounded <?= $i==$page?'bg-indigo-500 text-white':'bg-white text-gray-700 hover:bg-gray-100' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</div>

<script>
/* USER ROLE DOUGHNUT */
new Chart(document.getElementById('roleChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_keys($roleData)) ?>,
        datasets: [{
            data: <?= json_encode(array_values($roleData)) ?>,
            backgroundColor: ['#3b82f6','#10b981','#f59e0b'],
            borderWidth: 2
        }]
    },
    options: {
        cutout: '60%',
        plugins: { legend: { position:'bottom' } },
        responsive: true
    }
});

/* ESCALATION TYPE DOUGHNUT */
new Chart(document.getElementById('typeChart'), {
    type:'doughnut',
    data:{
        labels:['RESO','Normal'],
        datasets:[{
            data:[<?= $resoCount ?>,<?= $normalCount ?>],
            backgroundColor:['#22c55e','#ef4444'],
            borderWidth:2
        }]
    },
    options:{
        cutout:'70%',
        plugins:{ legend:{ display:false } },
        responsive:true,
        maintainAspectRatio:false
    }
});

/* ENGINEER STATUS DOUGHNUT */
new Chart(document.getElementById('engineerStatusChart'),{
    type:'doughnut',
    data:{
        labels:['Active','Inactive'],
        datasets:[{
            data:[<?= $activeEng ?>,<?= $inactiveEng ?>],
            backgroundColor:['#10b981','#ef4444'],
            borderWidth:2
        }]
    },
    options:{cutout:'60%', plugins:{legend:{position:'bottom'}}}
});
</script>

</body>
</html>
