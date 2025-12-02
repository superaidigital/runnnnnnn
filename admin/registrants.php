<?php
// admin/registrants.php
// หน้าจัดการข้อมูลผู้สมัคร (Complete Version: Multi-Event Staff + Stats Cards + Thai Date + Quick Approve)

// --- CORE BOOTSTRAP ---
require_once '../config.php';
require_once '../functions.php';

// 1. Check Login
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$is_super_admin = ($staff_info['role'] === 'admin');

// เตรียมรายการ Event IDs ที่ Staff คนนี้ดูแล (รองรับทั้งแบบ Array และ Single ID เก่า)
$allowed_events = [];
if ($is_super_admin) {
    // Admin ดูได้หมด (ข้ามการเช็ค)
} else {
    if (isset($staff_info['assigned_event_ids']) && is_array($staff_info['assigned_event_ids'])) {
        $allowed_events = $staff_info['assigned_event_ids'];
    } elseif (isset($staff_info['assigned_event_id'])) {
        $allowed_events = [$staff_info['assigned_event_id']];
    }
}
// --- END BOOTSTRAP ---

$page_title = 'จัดการข้อมูลผู้สมัคร';

// --- 2. Get Event ID ---
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    include 'partials/header.php'; 
    echo "<div class='p-6'><div class='bg-red-100 text-red-700 p-4 rounded'>Error: Invalid Event ID. <a href='index.php'>Back</a></div></div>"; 
    include 'partials/footer.php'; 
    exit;
}
$event_id = intval($_GET['event_id']);

// --- 3. Permission Check ---
// ถ้าไม่ใช่ Admin และ Event ID ที่ขอไม่อยู่ในรายการที่ได้รับมอบหมาย -> ดีดออก
if (!$is_super_admin && !in_array($event_id, $allowed_events)) {
    include 'partials/header.php'; 
    echo "<div class='p-6'><div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-md'>
            <h3 class='font-bold'><i class='fa-solid fa-ban'></i> ไม่มีสิทธิ์เข้าถึง</h3>
            <p>คุณไม่ได้รับอนุญาตให้จัดการข้อมูลของกิจกรรมนี้</p>
            <a href='index.php' class='underline mt-2 inline-block'>กลับสู่หน้าหลัก</a>
          </div></div>"; 
    include 'partials/footer.php'; 
    exit;
}

// --- 4. Fetch Event Info ---
$event_stmt = $mysqli->prepare("SELECT name, event_code FROM events WHERE id = ?");
$event_stmt->bind_param("i", $event_id);
$event_stmt->execute();
$event_res = $event_stmt->get_result();
if ($event_res->num_rows === 0) {
    die("Event not found.");
}
$event = $event_res->fetch_assoc();
$event_name = $event['name'];

// --- 5. Fetch Statistics (การ์ดสถิติ) ---
$stats_stmt = $mysqli->prepare("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'ชำระเงินแล้ว' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'รอตรวจสอบ' THEN 1 ELSE 0 END) as verify_count,
        SUM(CASE WHEN status = 'รอชำระเงิน' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'ชำระเงินแล้ว' THEN total_amount ELSE 0 END) as total_revenue
    FROM registrations 
    WHERE event_id = ?
");
$stats_stmt->bind_param("i", $event_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// --- 6. Pagination & Search ---
$limit = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query Building
$base_sql = "FROM registrations r
             LEFT JOIN distances d ON r.distance_id = d.id
             LEFT JOIN race_categories rc ON r.race_category_id = rc.id
             WHERE r.event_id = ?";
$params = [$event_id];
$types = "i";

if (!empty($search_term)) {
    $base_sql .= " AND (r.first_name LIKE ? OR r.last_name LIKE ? OR r.email LIKE ? OR r.phone LIKE ? OR r.registration_code LIKE ? OR r.bib_number LIKE ? OR r.thai_id LIKE ?)";
    $like = "%{$search_term}%";
    for($i=0; $i<7; $i++) { $params[] = $like; $types .= "s"; }
}

// Count Total
$count_stmt = $mysqli->prepare("SELECT COUNT(r.id) " . $base_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_records / $limit);

// Fetch Data (รวม shipping_option เพื่อใช้ในปุ่ม Quick Approve)
$data_sql = "SELECT r.id, r.registration_code, r.bib_number, r.title, r.first_name, r.last_name, r.email, r.phone, r.status, r.registered_at, r.payment_slip_url, r.shipping_option, d.name as distance_name, rc.name as category_name 
             " . $base_sql . " ORDER BY r.registered_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$data_stmt = $mysqli->prepare($data_sql);
$data_stmt->bind_param($types, ...$params);
$data_stmt->execute();
$registrants = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get Session Messages
$msg_success = $_SESSION['update_success'] ?? null; unset($_SESSION['update_success']);
$msg_error = $_SESSION['update_error'] ?? null; unset($_SESSION['update_error']);

include 'partials/header.php';
?>

<!-- Header Section -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
    <div>
        <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
            <a href="index.php" class="hover:underline"><i class="fa-solid fa-home"></i> หน้าหลัก</a>
            <i class="fa-solid fa-chevron-right text-xs"></i>
            <span>ผู้สมัคร</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900"><?= e($event_name) ?></h1>
        <p class="text-sm text-gray-600">รหัสกิจกรรม: <?= e($event['event_code']) ?></p>
    </div>
    <div class="flex gap-2">
        <a href="../index.php?page=microsite&event_code=<?= e($event['event_code']) ?>" target="_blank" class="bg-white border hover:bg-gray-50 text-gray-700 font-semibold py-2 px-4 rounded-lg text-sm transition shadow-sm">
            <i class="fa-solid fa-eye mr-1"></i> หน้าเว็บ
        </a>
        <a href="../actions/export_registrants.php?event_id=<?= e($event_id) ?>&search=<?= urlencode($search_term) ?>" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg text-sm shadow transition">
            <i class="fa-solid fa-file-excel mr-1"></i> Export CSV
        </a>
    </div>
</div>

<!-- Alerts -->
<?php if ($msg_success): ?>
    <div class="mb-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-sm relative">
        <span class="block sm:inline"><i class="fa-solid fa-check-circle mr-1"></i> <?= e($msg_success) ?></span>
    </div>
<?php endif; ?>
<?php if ($msg_error): ?>
    <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded shadow-sm relative">
        <span class="block sm:inline"><i class="fa-solid fa-exclamation-circle mr-1"></i> <?= e($msg_error) ?></span>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">ผู้สมัครทั้งหมด</p>
        <p class="text-2xl font-bold text-gray-800 mt-1"><?= number_format($stats['total_count']) ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">ชำระเงินแล้ว</p>
        <p class="text-2xl font-bold text-green-600 mt-1"><?= number_format($stats['paid_count']) ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-yellow-400 relative overflow-hidden">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">รอตรวจสอบ (มีสลิป)</p>
        <p class="text-2xl font-bold text-yellow-600 mt-1"><?= number_format($stats['verify_count']) ?></p>
        <?php if($stats['verify_count'] > 0): ?>
            <span class="absolute top-3 right-3 flex h-3 w-3">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
            </span>
        <?php endif; ?>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-red-500">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">ยังไม่ชำระเงิน</p>
        <p class="text-2xl font-bold text-red-600 mt-1"><?= number_format($stats['pending_count']) ?></p>
    </div>
    <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-indigo-500 md:col-span-1 col-span-2">
        <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">รายได้รวม</p>
        <p class="text-2xl font-bold text-indigo-700 mt-1">฿<?= number_format($stats['total_revenue'], 2) ?></p>
    </div>
</div>

<!-- Search Bar -->
<div class="bg-white p-4 rounded-t-xl border-b border-gray-200 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
    <h2 class="text-lg font-bold text-gray-800 flex items-center"><i class="fa-solid fa-users-viewfinder mr-2 text-gray-400"></i> รายชื่อผู้สมัคร</h2>
    <form action="registrants.php" method="GET" class="flex w-full md:w-auto relative">
        <input type="hidden" name="event_id" value="<?= e($event_id) ?>">
        <div class="relative w-full md:w-72">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa-solid fa-magnifying-glass text-gray-400"></i>
            </div>
            <input type="text" name="search" value="<?= e($search_term) ?>" 
                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-l-lg leading-5 bg-gray-50 placeholder-gray-500 focus:outline-none focus:bg-white focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm transition" 
                   placeholder="ค้นหา ชื่อ, BIB, เบอร์โทร...">
        </div>
        <button type="submit" class="bg-gray-800 hover:bg-gray-900 text-white font-bold py-2 px-4 rounded-r-lg text-sm transition">
            ค้นหา
        </button>
    </form>
</div>

<!-- Registrants Table -->
<div class="bg-white rounded-b-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">รหัส / BIB</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ผู้สมัคร</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ประเภท</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">สถานะ / สลิป</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">วันที่สมัคร</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">จัดการ</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($registrants)): ?>
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500 bg-gray-50 italic">ไม่พบข้อมูลผู้สมัครตามเงื่อนไข</td></tr>
                <?php else: ?>
                    <?php foreach($registrants as $reg):
                        $status_bg = 'bg-gray-100 text-gray-800';
                        $status_icon = '<i class="fa-solid fa-circle-question mr-1"></i>';
                        
                        if ($reg['status'] == 'ชำระเงินแล้ว') {
                            $status_bg = 'bg-green-100 text-green-800';
                            $status_icon = '<i class="fa-solid fa-circle-check mr-1"></i>';
                        } elseif ($reg['status'] == 'รอตรวจสอบ') {
                            $status_bg = 'bg-yellow-100 text-yellow-800';
                            $status_icon = '<i class="fa-solid fa-clock mr-1"></i>';
                        } elseif ($reg['status'] == 'รอชำระเงิน') {
                            $status_bg = 'bg-red-100 text-red-800';
                            $status_icon = '<i class="fa-solid fa-circle-xmark mr-1"></i>';
                        }
                    ?>
                    <tr class="hover:bg-indigo-50 transition duration-150 group">
                        <!-- Column 1: ID & BIB -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm font-mono font-bold text-indigo-600"><?= e($reg['registration_code']) ?></div>
                            <div class="text-xs text-gray-500 mt-1">BIB: 
                                <?php if($reg['bib_number']): ?>
                                    <span class="font-mono font-bold text-gray-800 bg-gray-200 px-1 rounded"><?= e($reg['bib_number']) ?></span>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Column 2: Name & Contact -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?= e($reg['title'] . $reg['first_name'] . ' ' . $reg['last_name']) ?></div>
                            <div class="text-xs text-gray-500 flex flex-col gap-0.5 mt-1">
                                <span><i class="fa-solid fa-envelope w-4 text-center text-gray-400"></i> <?= e($reg['email']) ?></span>
                                <span><i class="fa-solid fa-phone w-4 text-center text-gray-400"></i> <?= e($reg['phone']) ?></span>
                            </div>
                        </td>

                        <!-- Column 3: Distance & Category -->
                        <td class="px-4 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 font-semibold"><?= e($reg['distance_name']) ?></div>
                            <div class="text-xs text-gray-500"><?= e($reg['category_name'] ?: 'ไม่ระบุรุ่น') ?></div>
                            <div class="text-xs text-gray-400 mt-0.5"><?= $reg['shipping_option'] == 'delivery' ? '<i class="fa-solid fa-truck"></i> จัดส่ง' : '<i class="fa-solid fa-person-walking"></i> รับเอง' ?></div>
                        </td>

                        <!-- Column 4: Status & Slip -->
                        <td class="px-4 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $status_bg ?>">
                                <?= $status_icon . e($reg['status']) ?>
                            </span>
                            <?php if(!empty($reg['payment_slip_url'])): ?>
                                <div class="mt-2">
                                    <a href="../<?= e($reg['payment_slip_url']) ?>" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 hover:underline flex items-center justify-center gap-1">
                                        <i class="fa-solid fa-image"></i> ดูสลิป
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="mt-2 text-xs text-gray-400">- ไม่มีสลิป -</div>
                            <?php endif; ?>
                        </td>

                        <!-- Column 5: Date (Thai Format) -->
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= formatThaiDateTime($reg['registered_at']) ?>
                        </td>

                        <!-- Column 6: Actions -->
                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end items-center gap-2">
                                <!-- ปุ่มดูรายละเอียด -->
                                <a href="registrant_detail.php?reg_id=<?= e($reg['id']) ?>" class="text-gray-600 hover:text-indigo-600 bg-white border border-gray-300 hover:border-indigo-500 hover:bg-indigo-50 py-1 px-3 rounded transition shadow-sm" title="แก้ไข/ดูรายละเอียด">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>

                                <!-- ปุ่มอนุมัติด่วน (แสดงเฉพาะเมื่อยังไม่อนุมัติ) -->
                                <?php if($reg['status'] !== 'ชำระเงินแล้ว'): ?>
                                <form action="../actions/update_registration.php" method="POST" onsubmit="return confirm('ยืนยันการอนุมัติผู้สมัครรายนี้? \n(สถานะจะเปลี่ยนเป็น \'ชำระเงินแล้ว\' และระบบจะรันเลข BIB อัตโนมัติ)');" style="display:inline;">
                                    <input type="hidden" name="reg_id" value="<?= e($reg['id']) ?>">
                                    <input type="hidden" name="event_id" value="<?= e($event_id) ?>">
                                    <input type="hidden" name="status" value="ชำระเงินแล้ว">
                                    <!-- ส่งค่า shipping_option เดิมไปด้วย เพื่อป้องกันค่าหาย -->
                                    <input type="hidden" name="shipping_option" value="<?= e($reg['shipping_option']) ?>">
                                    <input type="hidden" name="redirect_url" value="admin/registrants.php?event_id=<?= e($event_id) ?>&page=<?= $page ?>&search=<?= urlencode($search_term) ?>">
                                    
                                    <button type="submit" class="text-white bg-green-500 hover:bg-green-600 py-1 px-3 rounded shadow-sm transition" title="อนุมัติทันที">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-white px-4 py-3 border-t border-gray-200 flex items-center justify-between sm:px-6">
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    แสดง <span class="font-medium"><?= $offset + 1 ?></span> ถึง <span class="font-medium"><?= min($offset + $limit, $total_records) ?></span> จาก <span class="font-medium"><?= number_format($total_records) ?></span> รายการ
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <!-- Prev Button -->
                    <a href="?event_id=<?= $event_id ?>&page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="sr-only">Previous</span>
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    
                    <?php
                    // Logic การแสดงเลขหน้าแบบย่อ (1 2 ... 5 6 7 ... 10)
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?event_id='.$event_id.'&page=1&search='.urlencode($search_term).'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                        if ($start_page > 2) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                    }

                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?event_id=<?= $event_id ?>&page=<?= $i ?>&search=<?= urlencode($search_term) ?>" aria-current="page" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium <?= $i == $page ? 'text-indigo-600 bg-indigo-50 z-10 border-indigo-500' : 'text-gray-500 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor;

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                        echo '<a href="?event_id='.$event_id.'&page='.$total_pages.'&search='.urlencode($search_term).'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
                    }
                    ?>

                    <!-- Next Button -->
                    <a href="?event_id=<?= $event_id ?>&page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                        <span class="sr-only">Next</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Mobile Pagination View -->
         <div class="flex items-center justify-between w-full sm:hidden">
            <a href="?event_id=<?= $event_id ?>&page=<?= max(1, $page - 1) ?>&search=<?= urlencode($search_term) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                ก่อนหน้า
            </a>
            <span class="text-sm text-gray-700">หน้า <?= $page ?> / <?= $total_pages ?></span>
            <a href="?event_id=<?= $event_id ?>&page=<?= min($total_pages, $page + 1) ?>&search=<?= urlencode($search_term) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $page >= $total_pages ? 'pointer-events-none opacity-50' : '' ?>">
                ถัดไป
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal for Slip Image (Optional) -->
<div id="image-modal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-75 flex items-center justify-center p-4" onclick="this.classList.add('hidden')">
    <div class="relative max-w-3xl w-full bg-white rounded-lg overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
        <button class="absolute top-2 right-2 bg-gray-200 hover:bg-gray-300 rounded-full p-2 w-8 h-8 flex items-center justify-center" onclick="document.getElementById('image-modal').classList.add('hidden')"><i class="fa-solid fa-times"></i></button>
        <img id="modal-img" src="" class="w-full h-auto max-h-[80vh] object-contain">
    </div>
</div>

<script>
// Script เล็กน้อยสำหรับคลิกดูรูปสลิปแบบ Modal (ถ้าต้องการใช้)
document.querySelectorAll('a[target="_blank"]').forEach(link => {
    if(link.href.match(/\.(jpeg|jpg|gif|png)$/) != null) {
        link.onclick = function(e) {
            e.preventDefault();
            document.getElementById('modal-img').src = this.href;
            document.getElementById('image-modal').classList.remove('hidden');
        }
    }
});
</script>

<?php include 'partials/footer.php'; ?>