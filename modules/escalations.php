<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

require '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// Fetch user data
$userStmt = $conn->prepare("SELECT name, site FROM users WHERE id=?");
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
if (!$userData) die("User not found.");
$site = $userData['site'];

/* ===============================
   FETCH ENGINEERS FOR DROPDOWN
================================ */
if ($role === 'super_admin') {
    $engStmt = $conn->prepare("SELECT id, name FROM users WHERE role='user' AND position='Engineer'");
    $engStmt->execute();
} else {
    $engStmt = $conn->prepare("SELECT id, name FROM users WHERE role='user' AND position='Engineer' AND site=?");
    $engStmt->bind_param("s", $site);
    $engStmt->execute();
}
$result    = $engStmt->get_result();
$engineers = [];
while ($row = $result->fetch_assoc()) {
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
    $edit_id          = intval($_POST['edit_id'] ?? 0);
    $ar_number        = $_POST['ar_number'] ?? '';
    $engineer_number  = $_POST['engineer_number'] ?? strval($user_id);
    $dispatch_id      = $_POST['dispatch_id'] ?? '';
    $serial_number    = $_POST['serial_number'] ?? '';
    $unit_description = $_POST['unit_description'] ?? '';
    $css_response     = $_POST['css_response'] ?? '';
    $remarks          = $_POST['remarks'] ?? '';
    $status           = $_POST['status'] ?? 'Open';
    $type             = $_POST['type'] ?? 'Normal';

    $approval_status = ($role === 'super_admin' || $role === 'admin')
        ? ($_POST['approval_status'] ?? 'Pending')
        : 'Pending';

    // Handle file upload
    $attachment = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === 0) {
        $filename   = time() . '_' . basename($_FILES['attachment']['name']);
        $targetFile = $uploadDir . $filename;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
            $attachment = $filename;
        } else {
            die("File upload failed.");
        }
    }

    $esc_site = $site;
    if ($edit_id > 0) {
        if ($role === 'super_admin') {
            $fetch = $conn->prepare("SELECT site, attachment FROM escalations WHERE id=?");
            $fetch->bind_param("i", $edit_id);
        } else {
            $fetch = $conn->prepare("SELECT site, attachment FROM escalations WHERE id=? AND site=?");
            $fetch->bind_param("is", $edit_id, $site);
        }
        $fetch->execute();
        $escData = $fetch->get_result()->fetch_assoc();
        if (!$escData) die("Escalation not found or permission denied.");
        $esc_site = $escData['site'];
        if (!$attachment) $attachment = $escData['attachment'];
    }

    if ($edit_id > 0) {
        if ($attachment) {
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
        if (!$stmt->execute()) die("Update failed: " . $stmt->error);
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
        if (!$stmt->execute()) die("Insert failed: " . $stmt->error);
        $activity = "Added escalation: AR=$ar_number, Serial=$serial_number, Type=$type";
    }

    // ===============================
    // ROLE-BASED AUTO CHAT NOTIFICATION
    // ===============================
    $escalation_id    = $stmt->insert_id ?: $edit_id;
    $notification_msg = "New escalation (#$escalation_id) added by "
        . htmlspecialchars($userData['name'])
        . ": AR=$ar_number, Serial=$serial_number, Type=$type";

    if ($role === 'user') {
        $notifyStmt = $conn->prepare("SELECT id FROM users WHERE role='admin' AND site=? AND status='active'");
        $notifyStmt->bind_param("s", $site);
        $notifyStmt->execute();
        $receivers = $notifyStmt->get_result();
    } elseif ($role === 'admin') {
        $notifyStmt = $conn->prepare("SELECT id FROM users WHERE role='super_admin' AND status='active'");
        $notifyStmt->execute();
        $receivers = $notifyStmt->get_result();
    } else {
        $receivers = null;
    }

    if ($receivers) {
        while ($admin = $receivers->fetch_assoc()) {
            $receiver_id = $admin['id'];
            if ($receiver_id == $user_id) continue;
            $chatStmt   = $conn->prepare("
                INSERT INTO chats (sender_id, receiver_id, message, file_path, created_at, status, is_read)
                VALUES (?,?,?,?,?,?,?)
            ");
            $statusChat = 'sent';
            $is_read    = 0;
            $now        = date('Y-m-d H:i:s');
            $chatStmt->bind_param("iissssi", $user_id, $receiver_id, $notification_msg, $attachment, $now, $statusChat, $is_read);
            $chatStmt->execute();
        }
    }

    // Log activity
    $module = 'Escalation';
    $log    = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
    $log->bind_param("isss", $user_id, $module, $activity, $esc_site);
    $log->execute();

    header("Location: escalations.php");
    exit();
}

/* ===============================
   SOFT DELETE
================================ */
if (isset($_GET['soft_delete']) && ($role === 'user' || $role === 'admin')) {
    $id   = intval($_GET['soft_delete']);
    $stmt = $conn->prepare("UPDATE escalations SET is_deleted=1 WHERE id=? AND site=?");
    $stmt->bind_param("is", $id, $site);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $activity = "Soft deleted escalation ID $id";
        $log      = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
        $module   = 'Escalation';
        $log->bind_param("isss", $user_id, $module, $activity, $site);
        $log->execute();
    }
    header("Location: escalations.php");
    exit();
}

/* ===============================
   HARD DELETE
================================ */
if (isset($_GET['hard_delete']) && ($role === 'admin' || $role === 'super_admin')) {
    $id = intval($_GET['hard_delete']);
    if ($role === 'super_admin') {
        $fetch = $conn->prepare("SELECT ar_number, serial_number, type, site FROM escalations WHERE id=?");
        $fetch->bind_param("i", $id);
    } else {
        $fetch = $conn->prepare("SELECT ar_number, serial_number, type, site FROM escalations WHERE id=? AND site=?");
        $fetch->bind_param("is", $id, $site);
    }
    $fetch->execute();
    $esc = $fetch->get_result()->fetch_assoc();
    if (!$esc) die("Escalation not found or permission denied.");

    if ($role === 'super_admin') {
        $stmt = $conn->prepare("DELETE FROM escalations WHERE id=?");
        $stmt->bind_param("i", $id);
    } else {
        $stmt = $conn->prepare("DELETE FROM escalations WHERE id=? AND site=?");
        $stmt->bind_param("is", $id, $site);
    }
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        $module   = 'Escalation';
        $activity = "Hard deleted escalation ID $id: AR={$esc['ar_number']}, Serial={$esc['serial_number']}, Type={$esc['type']}";
        $log      = $conn->prepare("INSERT INTO activity_logs(user_id,module,action,site) VALUES (?,?,?,?)");
        $log->bind_param("isss", $user_id, $module, $activity, $esc['site']);
        $log->execute();
    }
    header("Location: escalations.php");
    exit();
}

/* ===============================
   PAGINATION + FILTER SETUP
================================ */
$limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit  = in_array($limit, [10, 25, 50]) ? $limit : 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$search          = $_GET['search'] ?? '';
$filter_status   = $_GET['filter_status'] ?? '';
$filter_type     = $_GET['filter_type'] ?? '';
$filter_approval = $_GET['filter_approval'] ?? '';
$filter_site     = ($role === 'super_admin') ? ($_GET['filter_site'] ?? '') : $site;
$search_param    = "%$search%";

// Fetch distinct sites for super_admin site filter dropdown
$allSites = [];
if ($role === 'super_admin') {
    $siteRes = $conn->query("SELECT DISTINCT site FROM escalations WHERE is_deleted=0 ORDER BY site");
    while ($sr = $siteRes->fetch_assoc()) $allSites[] = $sr['site'];
}

function buildWhere(string $role, string $site, string $filter_site, string $search_param, string $filter_status, string $filter_type, string $filter_approval): array {
    $conditions = [];
    $params     = [];
    $types      = '';

    if ($role === 'super_admin') {
        if ($filter_site !== '') {
            $conditions[] = 'e.site = ?';
            $params[]     = $filter_site;
            $types       .= 's';
        }
    } else {
        $conditions[] = 'e.site = ?';
        $params[]     = $site;
        $types       .= 's';
    }

    $conditions[] = 'e.is_deleted = 0';

    // Search across text fields
    $conditions[] = '(e.site LIKE ? OR e.ar_number LIKE ? OR e.serial_number LIKE ? OR e.remarks LIKE ? OR e.unit_description LIKE ? OR e.css_response LIKE ?)';
    $params = array_merge($params, array_fill(0, 6, $search_param));
    $types .= 'ssssss';

    // Exact filters
    if ($filter_status !== '') {
        $conditions[] = 'e.status = ?';
        $params[]     = $filter_status;
        $types       .= 's';
    }
    if ($filter_type !== '') {
        $conditions[] = 'e.type = ?';
        $params[]     = $filter_type;
        $types       .= 's';
    }
    if ($filter_approval !== '') {
        $conditions[] = 'e.approval_status = ?';
        $params[]     = $filter_approval;
        $types       .= 's';
    }

    $where = implode(' AND ', $conditions);
    return [$where, $types, $params];
}

[$where, $types, $params] = buildWhere($role, $site, $filter_site, $search_param, $filter_status, $filter_type, $filter_approval);

// COUNT TOTAL ROWS
$countSql  = "SELECT COUNT(*) as total FROM escalations e WHERE $where";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = (int) ceil($totalRows / $limit);

// FETCH ESCALATIONS
$fetchSql  = "
    SELECT e.*, u.name AS engineer_name, u2.role AS creator_role
    FROM escalations e
    LEFT JOIN users u  ON u.id  = e.engineer_number
    LEFT JOIN users u2 ON u2.id = e.created_by
    WHERE $where
    ORDER BY e.id DESC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($fetchSql);
$fetchTypes  = $types . 'ii';
$fetchParams = array_merge($params, [$limit, $offset]);
$stmt->bind_param($fetchTypes, ...$fetchParams);
$stmt->execute();
$escalations = $stmt->get_result();

/* ===============================
   PAGINATION HELPER
================================ */
function paginationLinks(int $currentPage, int $totalPages, string $search, int $limit, string $filter_status = '', string $filter_type = '', string $filter_approval = '', string $filter_site = ''): string
{
    $baseUrl  = '?search=' . urlencode($search)
                . '&limit=' . $limit
                . '&filter_status=' . urlencode($filter_status)
                . '&filter_type=' . urlencode($filter_type)
                . '&filter_approval=' . urlencode($filter_approval)
                . '&filter_site=' . urlencode($filter_site)
                . '&page=';
    $btnBase  = 'inline-flex items-center px-4 py-2 rounded text-sm font-medium transition border ';
    $active   = $btnBase . 'bg-blue-600 text-white border-blue-600';
    $inactive = $btnBase . 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50';
    $disabled = $btnBase . 'bg-gray-50 border-gray-200 text-gray-300 cursor-not-allowed pointer-events-none';

    $html = '<div class="flex items-center gap-1 flex-wrap">';

    // Prev
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . ($currentPage - 1) . '" class="' . $inactive . '">&#8592; Prev</a>';
    } else {
        $html .= '<span class="' . $disabled . '">&#8592; Prev</span>';
    }

    // Page numbers with ellipsis
    if ($totalPages > 0) {
        $pages = [];
        if ($totalPages <= 7) {
            $pages = range(1, $totalPages);
        } else {
            $pages[] = 1;
            if ($currentPage > 3) $pages[] = '...';
            for ($i = max(2, $currentPage - 1); $i <= min($totalPages - 1, $currentPage + 1); $i++) {
                $pages[] = $i;
            }
            if ($currentPage < $totalPages - 2) $pages[] = '...';
            $pages[] = $totalPages;
        }
        foreach ($pages as $p) {
            if ($p === '...') {
                $html .= '<span class="px-2 py-2 text-gray-400 text-sm select-none">…</span>';
            } elseif ($p == $currentPage) {
                $html .= '<span class="' . $active . '">' . $p . '</span>';
            } else {
                $html .= '<a href="' . $baseUrl . $p . '" class="' . $inactive . '">' . $p . '</a>';
            }
        }
    }

    // Next
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . ($currentPage + 1) . '" class="' . $inactive . '">Next &#8594;</a>';
    } else {
        $html .= '<span class="' . $disabled . '">Next &#8594;</span>';
    }

    $html .= '</div>';
    return $html;
}

// Helper: truncate text for table cells
function truncate(string $text, int $len = 30): string
{
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Escalations</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
.loader {
    border: 4px solid #3b82f6;
    border-top-color: transparent;
    border-radius: 50%;
    width: 3rem; height: 3rem;
    animation: spin 1s linear infinite;
}
#loadingOverlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 50;
    justify-content: center;
    align-items: center;
}
.status-approved    { color: #16a34a; }
.status-pending     { color: #b45309; }
.status-rejected    { color: #dc2626; }
.status-investigation { color: #f97316; }

/* Compact table cells */
.esc-table td, .esc-table th { vertical-align: middle; }
.truncate-cell {
    max-width: 140px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
</head>
<body data-role="<?= $role ?>" class="bg-gray-100 min-h-screen">
<?php include '../layouts/navbar.php'; ?>

<!-- LOADING OVERLAY -->
<div id="loadingOverlay">
    <div class="bg-white p-6 rounded-lg shadow-lg flex flex-col items-center gap-3">
        <div class="loader"></div>
        <span class="font-semibold text-gray-700">Please wait…</span>
    </div>
</div>

<div class="container mx-auto p-6">

    <!-- HEADER -->
    <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
        <h1 class="text-2xl font-bold">
            Escalations
            <?= $role === 'super_admin' ? '<span class="text-blue-600">(All Sites)</span>' : '— <span class="text-blue-600">' . htmlspecialchars($site) . '</span>' ?>
        </h1>
        <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 flex items-center gap-1">
            <i class='bx bx-plus'></i> Add Escalation
        </button>
    </div>

    <!-- SEARCH + FILTERS (single row) -->
    <form method="GET" class="mb-4 bg-white border rounded-lg p-3 shadow-sm" onsubmit="showLoading()">
        <div class="flex flex-wrap gap-2 items-center">

            <!-- Filter label -->
            <span class="text-sm text-gray-400 font-medium flex items-center gap-1 whitespace-nowrap">
                <i class='bx bx-filter-alt'></i> Filter:
            </span>

            <!-- Status -->
            <select name="filter_status" onchange="this.form.submit()" class="border p-2 rounded text-sm bg-white min-w-[120px]">
                <option value="">All Status</option>
                <?php foreach (['Open', 'Closed', 'Pending'] as $s): ?>
                    <option value="<?= $s ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= $s ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Type -->
            <select name="filter_type" onchange="this.form.submit()" class="border p-2 rounded text-sm bg-white min-w-[120px]">
                <option value="">All Types</option>
                <?php foreach (['Normal', 'Reso'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filter_type === $t ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Approval -->
            <select name="filter_approval" onchange="this.form.submit()" class="border p-2 rounded text-sm bg-white min-w-[150px]">
                <option value="">All Approvals</option>
                <?php foreach (['Pending', 'Approved', 'Rejected', 'Under Investigation'] as $a): ?>
                    <option value="<?= $a ?>" <?= $filter_approval === $a ? 'selected' : '' ?>><?= $a ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Site (super_admin only) -->
            <?php if ($role === 'super_admin' && !empty($allSites)): ?>
            <select name="filter_site" onchange="this.form.submit()" class="border p-2 rounded text-sm bg-white min-w-[130px]">
                <option value="">All Sites</option>
                <?php foreach ($allSites as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filter_site === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <!-- Divider -->
            <span class="text-gray-200 hidden sm:inline">|</span>

            <!-- Search input — normal width, not flex-1 -->
            <input type="text" name="search" placeholder="Search AR, serial, remarks…"
                   value="<?= htmlspecialchars($search) ?>"
                   class="border p-2 rounded text-sm w-48">

            <!-- Per page -->
            <select name="limit" class="border p-2 rounded text-sm bg-white">
                <?php foreach ([10, 25, 50] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?>/page</option>
                <?php endforeach; ?>
            </select>

            <!-- Search button -->
            <button type="submit" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700 flex items-center gap-1 text-sm">
                <i class='bx bx-search'></i> Search
            </button>

            <!-- Reset -->
            <?php
            $activeFilters = array_filter([$filter_status, $filter_type, $filter_approval, ($role === 'super_admin' ? $filter_site : '')]);
            if ($search || !empty($activeFilters)): ?>
                <a href="escalations.php" class="bg-gray-100 text-gray-600 px-3 py-2 rounded hover:bg-gray-200 flex items-center gap-1 text-sm border">
                    <i class='bx bx-x'></i> Reset
                </a>
            <?php endif; ?>

            <!-- Active filter badge -->
            <?php if (!empty($activeFilters)): ?>
                <span class="text-xs text-blue-600 bg-blue-50 border border-blue-200 px-2 py-1 rounded-full font-medium whitespace-nowrap">
                    <?= count($activeFilters) ?> filter<?= count($activeFilters) > 1 ? 's' : '' ?> active
                </span>
            <?php endif; ?>

        </div>
    </form>

    <!-- TABLE -->
    <div class="bg-white shadow rounded-lg overflow-x-auto">
        <table class="w-full text-sm text-left esc-table">
            <thead class="bg-gray-200 text-gray-700 uppercase tracking-wide text-xs">
                <tr>
                    <th class="p-3 whitespace-nowrap">Site</th>
                    <th class="p-3 whitespace-nowrap">AR Number</th>
                    <th class="p-3 whitespace-nowrap">Engineer</th>
                    <th class="p-3 whitespace-nowrap">Dispatch ID</th>
                    <th class="p-3 whitespace-nowrap">Serial</th>
                    <th class="p-3 whitespace-nowrap">Unit Desc</th>
                    <th class="p-3 whitespace-nowrap">CSS Response</th>
                    <th class="p-3 whitespace-nowrap">Remarks</th>
                    <th class="p-3 whitespace-nowrap">Status</th>
                    <th class="p-3 whitespace-nowrap">Type</th>
                    <th class="p-3 whitespace-nowrap">Approval</th>
                    <th class="p-3 whitespace-nowrap">File</th>
                    <th class="p-3 whitespace-nowrap">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php
            $rowCount = 0;
            while ($row = $escalations->fetch_assoc()):
                $rowCount++;
                $statusClass = '';
                switch ($row['approval_status']) {
                    case 'Approved':           $statusClass = 'status-approved'; break;
                    case 'Pending':            $statusClass = 'status-pending';  break;
                    case 'Rejected':           $statusClass = 'status-rejected'; break;
                    case 'Under Investigation':$statusClass = 'status-investigation'; break;
                }
            ?>
            <tr class="hover:bg-blue-50 transition"
                data-id="<?= $row['id'] ?>"
                data-ar="<?= htmlspecialchars($row['ar_number']) ?>"
                data-engineer="<?= htmlspecialchars($row['engineer_number']) ?>"
                data-dispatch="<?= htmlspecialchars($row['dispatch_id']) ?>"
                data-serial="<?= htmlspecialchars($row['serial_number']) ?>"
                data-unit="<?= htmlspecialchars($row['unit_description']) ?>"
                data-css="<?= htmlspecialchars($row['css_response']) ?>"
                data-remarks="<?= htmlspecialchars($row['remarks']) ?>"
                data-status="<?= htmlspecialchars($row['status']) ?>"
                data-approval="<?= htmlspecialchars($row['approval_status']) ?>"
                data-type="<?= htmlspecialchars($row['type']) ?>"
                data-creator-role="<?= htmlspecialchars($row['creator_role']) ?>"
            >
                <td class="p-3"><?= htmlspecialchars($row['site']) ?></td>
                <td class="p-3 font-mono"><?= htmlspecialchars($row['ar_number']) ?></td>
                <td class="p-3"><?= htmlspecialchars($row['engineer_name']) ?></td>
                <td class="p-3"><?= htmlspecialchars($row['dispatch_id']) ?></td>
                <td class="p-3 font-mono"><?= htmlspecialchars($row['serial_number']) ?></td>

                <!-- Unit Description -->
                <td class="p-3">
                    <?php $unit = $row['unit_description']; ?>
                    <?php if (mb_strlen($unit) > 25): ?>
                        <span class="truncate-cell inline-block align-bottom" title="<?= htmlspecialchars($unit) ?>"><?= htmlspecialchars(truncate($unit, 25)) ?></span>
                        <button class="text-blue-500 hover:underline text-[11px] ml-1 whitespace-nowrap"
                            onclick="viewRemarks(`<?= htmlspecialchars(addslashes($unit)) ?>`, 'Unit Description')">more</button>
                    <?php else: ?>
                        <?= htmlspecialchars($unit) ?>
                    <?php endif; ?>
                </td>

                <!-- CSS Response -->
                <td class="p-3">
                    <?php $css = $row['css_response']; ?>
                    <?php if (mb_strlen($css) > 25): ?>
                        <span class="truncate-cell inline-block align-bottom" title="<?= htmlspecialchars($css) ?>"><?= htmlspecialchars(truncate($css, 25)) ?></span>
                        <button class="text-blue-500 hover:underline text-[11px] ml-1 whitespace-nowrap"
                            onclick="viewRemarks(`<?= htmlspecialchars(addslashes($css)) ?>`, 'CSS Response')">more</button>
                    <?php else: ?>
                        <?= htmlspecialchars($css) ?>
                    <?php endif; ?>
                </td>

                <!-- Remarks -->
                <td class="p-3">
                    <?php $rmk = $row['remarks']; ?>
                    <?php if (mb_strlen($rmk) > 30): ?>
                        <span><?= htmlspecialchars(truncate($rmk, 30)) ?></span>
                        <button class="text-blue-500 hover:underline text-[11px] ml-1 whitespace-nowrap"
                            onclick="viewRemarks(`<?= htmlspecialchars(addslashes($rmk)) ?>`, 'Remarks')">View</button>
                    <?php else: ?>
                        <?= htmlspecialchars($rmk) ?>
                    <?php endif; ?>
                </td>

                <!-- Status badge -->
                <td class="p-3">
                    <?php
                    $badgeColor = match($row['status']) {
                        'Open'    => 'bg-green-100 text-green-700',
                        'Closed'  => 'bg-gray-200 text-gray-600',
                        'Pending' => 'bg-yellow-100 text-yellow-700',
                        default   => 'bg-gray-100 text-gray-500',
                    };
                    ?>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $badgeColor ?>">
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </td>

                <!-- Type badge -->
                <td class="p-3">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold <?= $row['type'] === 'Reso' ? 'bg-purple-100 text-purple-700' : 'bg-blue-50 text-blue-600' ?>">
                        <?= htmlspecialchars($row['type']) ?>
                    </span>
                </td>

                <!-- Approval -->
                <td class="p-3 <?= $statusClass ?> font-semibold whitespace-nowrap">
                    <?= htmlspecialchars($row['approval_status']) ?>
                </td>

                <!-- Attachment -->
                <td class="p-3 text-center">
                    <?php if ($row['attachment']): ?>
                        <a href="uploads/<?= htmlspecialchars($row['attachment']) ?>" target="_blank"
                           class="text-blue-500 hover:text-blue-700" title="View attachment">
                            <i class='bx bx-paperclip text-base'></i>
                        </a>
                    <?php else: ?>
                        <span class="text-gray-300">—</span>
                    <?php endif; ?>
                </td>

                <!-- Actions -->
                <td class="p-3">
                    <div class="flex gap-1 items-center">
                        <?php if ($role === 'user'): ?>
                        <a href="?soft_delete=<?= $row['id'] ?>"
                           class="text-yellow-500 hover:text-yellow-700"
                           onclick="return confirm('Soft delete this escalation?')"
                           title="Soft Delete">
                            <i class='bx bx-trash text-base'></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($role === 'admin' || $role === 'super_admin'): ?>
                        <a href="?hard_delete=<?= $row['id'] ?>"
                           class="text-red-500 hover:text-red-700"
                           onclick="return confirm('Permanently delete this escalation?')"
                           title="Hard Delete">
                            <i class='bx bx-x-circle text-base'></i>
                        </a>
                        <?php endif; ?>

                        <button type="button" onclick="openEditModal(<?= $row['id'] ?>)"
                                class="text-green-600 hover:text-green-800" title="Edit">
                            <i class='bx bx-edit text-base'></i>
                        </button>

                        <?php if ($role === 'admin' && $row['type'] === 'Normal'): ?>
                        <button type="button" onclick="makeReso(<?= $row['id'] ?>)"
                                class="text-blue-500 hover:text-blue-700" title="Create Reso">
                            <i class='bx bx-up-arrow-circle text-base'></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>

            <?php if ($rowCount === 0): ?>
            <tr>
                <td colspan="13" class="text-center py-10 text-gray-400">
                    <i class='bx bx-inbox text-4xl block mb-2'></i>
                    No escalations found.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- DEBUG BAR (remove after confirming counts are correct) -->
    <div class="mt-3 text-xs text-gray-400 bg-gray-50 border border-dashed border-gray-200 rounded px-3 py-2">
        🔍 Debug: totalRows=<strong><?= $totalRows ?></strong> | limit=<strong><?= $limit ?></strong> | page=<strong><?= $page ?></strong> | offset=<strong><?= $offset ?></strong> | totalPages=<strong><?= $totalPages ?></strong>
    </div>

    <!-- PAGINATION -->
    <div class="mt-3 flex flex-wrap justify-between items-center gap-3 border-t pt-4">
        <p class="text-sm text-gray-500">
            Showing
            <strong class="text-gray-700"><?= $totalRows > 0 ? min($offset + 1, $totalRows) : 0 ?>–<?= min($offset + $limit, $totalRows) ?></strong>
            of <strong class="text-gray-700"><?= $totalRows ?></strong> result<?= $totalRows != 1 ? 's' : '' ?>
            <?php if ($totalPages > 1): ?>
                &nbsp;&mdash;&nbsp; Page <strong class="text-gray-700"><?= $page ?></strong> of <strong class="text-gray-700"><?= $totalPages ?></strong>
            <?php endif; ?>
        </p>
        <?= paginationLinks($page, $totalPages, $search, $limit, $filter_status, $filter_type, $filter_approval, $filter_site) ?>
    </div>

</div>

<!-- ADD/EDIT MODAL -->
<div id="escalationModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-2">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg p-6 overflow-y-auto max-h-[90vh]">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold" id="modalTitle">Add Escalation</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" id="escalationForm" enctype="multipart/form-data" onsubmit="showLoading()" class="space-y-4">
            <input type="hidden" name="edit_id" id="edit_id">

            <input type="hidden" name="site" value="<?= htmlspecialchars($site) ?>">
            <div class="text-sm text-gray-500">Site: <strong><?= htmlspecialchars($site) ?></strong></div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">AR Number <span class="text-red-500">*</span></label>
                <input type="text" name="ar_number" id="ar_number" class="w-full border p-2 rounded" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Engineer <span class="text-red-500">*</span></label>
                <select name="engineer_number" id="engineer_number" class="w-full border p-2 rounded" required>
                    <option value="">Select Engineer</option>
                    <?php foreach ($engineers as $eng): ?>
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
            <?php if ($role === 'admin' || $role === 'super_admin'): ?>
            <div class="hidden" id="approvalWrapper">
                <label class="block text-sm font-medium text-gray-700 mb-1">Approval Status</label>
                <select name="approval_status" id="approval_status" class="w-full border p-2 rounded"></select>
            </div>
            <?php endif; ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Attachment</label>
                <input type="file" name="attachment" class="w-full border p-2 rounded text-sm">
            </div>
            <div class="flex justify-end space-x-2 pt-4 border-t">
                <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" id="submitBtn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- REMARKS / TEXT VIEW MODAL -->
<div id="remarksModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center px-5 py-4 border-b">
            <h2 class="text-base font-semibold text-gray-800" id="remarksModalTitle">Details</h2>
            <button onclick="closeRemarks()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div id="remarksContent" class="px-5 py-4 text-sm text-gray-700 whitespace-pre-wrap max-h-72 overflow-y-auto leading-relaxed"></div>
        <div class="flex justify-end px-5 py-3 border-t">
            <button onclick="closeRemarks()" class="px-4 py-2 bg-gray-100 rounded hover:bg-gray-200 text-sm">Close</button>
        </div>
    </div>
</div>

<script src="escalation.js"></script>
</body>
</html>