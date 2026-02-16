<?php
session_start();
require '../config/database.php';

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT name, role, site FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();

$name = $user_data['name'] ?? 'User';
$role = $user_data['role'] ?? 'user';
$site = $user_data['site'] ?? 'No site assigned';

// Dashboard modules
$modules = [
    'Frontline' => ['href'=>'../modules/frontline.php','icon'=>'bxs-user-voice','color'=>'blue'],
    'Escalations' => ['href'=>'../modules/escalations.php','icon'=>'bxs-bell','color'=>'yellow'],
    'Inventory' => ['href'=>'../modules/inventory.php','icon'=>'bxs-box','color'=>'green'],
];

if($role === 'user') $modules['Endorsement'] = ['href'=>'../modules/endorsement.php','icon'=>'bxs-file','color'=>'purple'];
if($role === 'admin' || $role === 'super_admin') $modules['Analytics'] = ['href'=>'../modules/analytics.php','icon'=>'bxs-bar-chart-alt-2','color'=>'red'];
if($role === 'super_admin'){
    $modules['System Settings'] = ['href'=>'../dashboard/system_settings.php','icon'=>'bxs-cog','color'=>'indigo'];
    $modules['User Management'] = ['href'=>'../dashboard/user_management.php','icon'=>'bxs-user-detail','color'=>'pink'];
}

// Count unread messages for badge
$unread_count = 0;
if($role==='user'){
    $res = $conn->query("SELECT COUNT(*) AS unread FROM chats WHERE receiver_id=$user_id AND is_read=0");
    $unread_count = $res->fetch_assoc()['unread'] ?? 0;
} elseif($role==='admin' || $role==='super_admin'){
    $res = $conn->query("SELECT COUNT(*) AS unread FROM chats WHERE receiver_id=$user_id AND is_read=0");
    $unread_count = $res->fetch_assoc()['unread'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>


<meta charset="UTF-8">
<title>Dashboard</title>
 <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon_io/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon_io/favicon-32x32.png">
    <link rel="shortcut icon" href="../assets/favicon_io/favicon.ico" type="image/x-icon">

    <!-- Apple Touch Icon for iOS -->
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon_io/apple-touch-icon.png">

    <!-- Android Chrome -->
    <link rel="manifest" href="/assets/favicon_io/site.webmanifest">
<link href="../assets/css/output.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
.card {
    @apply bg-white rounded-xl shadow-md hover:shadow-xl transition-transform transform hover:-translate-y-1 hover:scale-105;
}

/* Chat scrollbar */
#chatMessages::-webkit-scrollbar { width: 6px; }
#chatMessages::-webkit-scrollbar-thumb { background-color: rgba(107,114,128,0.5); border-radius: 3px; }
</style>
</head>
<body class="bg-gray-100 min-h-screen">

<?php include '../layouts/navbar.php'; ?>

<div class="container mx-auto p-6">
    <!-- Welcome -->
    <h1 class="text-3xl font-bold mb-2 text-gray-800 text-center">Welcome, <?= htmlspecialchars($name) ?>!</h1>
    <p class="text-gray-600 mb-8 text-center">
        Site: <span class="font-semibold text-gray-800"><?= htmlspecialchars($site) ?></span>
    </p>

    <!-- Dashboard Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        <?php foreach($modules as $name_module => $module): ?>
            <a href="<?= $module['href'] ?>" class="card group">
                <div class="p-6 flex flex-col items-center justify-center">
                    <i class='bx <?= $module['icon'] ?> text-6xl mb-3 text-<?= $module['color'] ?>-500 group-hover:scale-110 transition-transform duration-300'></i>
                    <h2 class="text-xl font-semibold mb-1 text-gray-800 group-hover:text-<?= $module['color'] ?>-600 transition-colors duration-300"><?= $name_module ?></h2>
                    <span class="text-gray-500 group-hover:text-gray-700 text-sm">Click to open</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Chat Icon -->
<div id="chatIcon" class="fixed bottom-6 right-6 bg-blue-600 hover:bg-blue-700 text-white w-16 h-16 rounded-full flex items-center justify-center cursor-pointer shadow-lg z-50">
    <i class='bx bxs-message text-3xl'></i>
    <?php if($unread_count>0): ?>
        <span id="chatBadge" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full"><?= $unread_count ?></span>
    <?php else: ?>
        <span id="chatBadge" class="hidden"></span>
    <?php endif; ?>
</div>

<!-- Chat Modal -->
<div id="chatModal" class="fixed inset-0 hidden z-50 bg-black/40 p-4 flex items-center justify-center">
    <div id="chatBox" class="bg-white w-full max-w-md h-[80vh] rounded-xl shadow-xl flex flex-col overflow-hidden relative cursor-move">
        <!-- Header -->
        <div class="flex justify-between items-center p-4 border-b bg-blue-600 text-white">
            <h3 class="text-lg font-semibold">Chat</h3>
            <button id="closeChat" class="hover:text-gray-200"><i class='bx bx-x text-2xl'></i></button>
        </div>

        <!-- User Selection -->
        <div class="p-2 border-b">
            <select id="chatUser" class="w-full border p-2 rounded">
                <option value="">Select User</option>
                <?php
                $current_id = $_SESSION['user_id'];
                if($role==='user'){
                    $users_query = $conn->query("SELECT id,name,role FROM users WHERE role='admin' AND status='active'");
                } elseif($role==='admin'){
                    $users_query = $conn->query("SELECT id,name,role FROM users WHERE role IN ('user','super_admin') AND status='active'");
                } else {
                    $users_query = $conn->query("SELECT id,name,role FROM users WHERE id!=$current_id AND status='active'");
                }
                while($u = $users_query->fetch_assoc()){
    // Count unread messages from this user
    $uid = $u['id'];
    $unreadRes = $conn->query("SELECT COUNT(*) AS unread FROM chats WHERE sender_id=$uid AND receiver_id=$user_id AND is_read=0");
    $unreadCount = $unreadRes->fetch_assoc()['unread'] ?? 0;

    $badge = $unreadCount > 0 ? "  You have unread message(s): $unreadCount": '';
    echo "<option value='{$u['id']}'>".htmlspecialchars($u['name'])." (".htmlspecialchars($u['role']).")$badge</option>";
}

                ?>
            </select>
        </div>

        <!-- Messages -->
        <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-2 bg-gray-50">
            <p class="text-gray-400 text-center mt-8">Select a user to start chatting</p>
        </div>

        <!-- Send Message -->
        <div class="p-4 border-t flex items-center space-x-2 bg-white sticky bottom-0">
            <input type="text" id="chatInput" placeholder="Type a message..." class="flex-1 border p-2 rounded" disabled>
            <input type="file" id="chatFile" class="hidden">
            <button id="sendFile" class="bg-gray-200 hover:bg-gray-300 p-2 rounded"><i class='bx bxs-file'></i></button>
            <button id="sendMessage" class="bg-blue-600 hover:bg-blue-700 text-white px-4 rounded disabled:opacity-50" disabled>Send</button>
        </div>
    </div>
</div>

<script>
// Basic Chat JS
const chatIcon = document.getElementById('chatIcon');
const chatModal = document.getElementById('chatModal');
const closeChat = document.getElementById('closeChat');
const chatBox = document.getElementById('chatBox');
const chatUser = document.getElementById('chatUser');
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');
const sendMessage = document.getElementById('sendMessage');
const sendFile = document.getElementById('sendFile');
const chatFile = document.getElementById('chatFile');
const chatBadge = document.getElementById('chatBadge');

let chatInterval;
let selectedUser = null;

// Open/Close
chatIcon.addEventListener('click', ()=> chatModal.classList.remove('hidden'));
closeChat.addEventListener('click', ()=> { chatModal.classList.add('hidden'); clearInterval(chatInterval); });

// Select user
chatUser.addEventListener('change', ()=>{
    selectedUser = chatUser.value;
    if(selectedUser){
        chatInput.disabled=false; sendMessage.disabled=false;
        loadMessages();
        clearInterval(chatInterval);
        chatInterval=setInterval(loadMessages,3600);
    } else {
        chatInput.disabled=true; sendMessage.disabled=true;
        chatMessages.innerHTML='<p class="text-gray-400 text-center">Select a user to start chatting</p>';
    }
});
function loadMessages(){
    if(!selectedUser) return;

    // Check if user is at bottom before loading new messages
    const atBottom = chatMessages.scrollHeight - chatMessages.scrollTop <= chatMessages.clientHeight + 5;

    fetch('chat_actions.php?action=get_messages&user_id=' + selectedUser)
    .then(res => res.json())
    .then(data => {
        chatMessages.innerHTML = '';
        let unread = 0;

        // Sort messages by created_at ascending just in case
        data.sort((a,b) => new Date(a.created_at) - new Date(b.created_at));

        data.forEach(msg => {
            const div = document.createElement('div');
            div.classList.add('p-2', 'rounded', 'max-w-[70%]');
            const timestamp = `<div class="text-xs text-gray-400 mt-1">${msg.created_at}</div>`;
            let status = '';

            if(msg.sender_id == <?= $user_id ?>){
                div.classList.add('bg-blue-100', 'ml-auto', 'text-right');
                status = `<span class="text-xs text-gray-500 ml-1">${msg.status}</span>`;
            } else {
                div.classList.add('bg-gray-200', 'mr-auto', 'text-left');
                if(msg.is_read == 0) unread++;
            }

            let content = msg.message ? msg.message : '';
            if(msg.file_path){
                content += `<br><a href="uploads/${msg.file_path}" target="_blank" class="text-blue-600 underline">Download File</a>`;
            }

            div.innerHTML = content + timestamp + status;
            chatMessages.appendChild(div); // oldest → newest
        });

        // Scroll to bottom only if user was at the bottom
        if(atBottom){
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Update badge
        if(unread > 0){
            chatBadge.innerText = unread;
            chatBadge.classList.remove('hidden');
        } else {
            chatBadge.classList.add('hidden');
        }
    });
}
// Send message on Enter key
chatInput.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault(); // Prevent new line
        sendMessage.click(); // Trigger the send button
    }
});


// Send message
sendMessage.addEventListener('click', ()=>{
    const msg=chatInput.value.trim();
    if(!msg && !chatFile.files[0]) return;
    const fd=new FormData();
    fd.append('receiver_id',selectedUser);
    fd.append('message',msg);
    if(chatFile.files[0]) fd.append('file',chatFile.files[0]);
    fetch('chat_actions.php?action=send',{method:'POST',body:fd})
    .then(res=>res.json())
    .then(data=>{
        if(data.status==='success'){ chatInput.value=''; chatFile.value=''; loadMessages(); }
        else alert('Error sending message');
    });
});
sendFile.addEventListener('click',()=>chatFile.click());
</script>
</body>
</html>
