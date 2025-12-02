<?php
// admin/staff_management.php
// หน้าจัดการเจ้าหน้าที่ (รองรับหลายกิจกรรม)

require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['staff_id']) || $_SESSION['staff_info']['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$page_title = 'จัดการเจ้าหน้าที่';

// --- Fetch All Staff with their assigned events ---
// ใช้ GROUP_CONCAT เพื่อรวบรวมชื่อกิจกรรมทั้งหมดที่ staff ดูแลมาไว้ใน field เดียว
$staffs = $mysqli->query("
    SELECT s.*, 
           GROUP_CONCAT(e.name SEPARATOR ', ') as assigned_event_names,
           GROUP_CONCAT(e.id) as assigned_event_ids_str
    FROM staff s
    LEFT JOIN staff_assignments sa ON s.id = sa.staff_id
    LEFT JOIN events e ON sa.event_id = e.id
    GROUP BY s.id
    ORDER BY s.role ASC, s.username ASC
")->fetch_all(MYSQLI_ASSOC);

// Fetch All Active Events for dropdown
$events = $mysqli->query("SELECT id, name FROM events WHERE is_cancelled = 0 ORDER BY sort_order ASC")->fetch_all(MYSQLI_ASSOC);

$success_message = isset($_SESSION['update_success']) ? $_SESSION['update_success'] : null; unset($_SESSION['update_success']);
$error_message = isset($_SESSION['update_error']) ? $_SESSION['update_error'] : null; unset($_SESSION['update_error']);

include 'partials/header.php';
?>

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">จัดการบัญชีเจ้าหน้าที่</h1>
        <p class="text-gray-600">เพิ่ม, แก้ไข, และมอบหมายกิจกรรมให้เจ้าหน้าที่ดูแล (เลือกได้หลายกิจกรรม)</p>
    </div>
</div>

<?php if ($success_message): ?>
<div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert"><p><?= e($success_message) ?></p></div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert"><p><?= e($error_message) ?></p></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Left: Staff List -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-md">
        <h2 class="text-xl font-bold mb-4">รายชื่อบัญชีทั้งหมด</h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">ชื่อเต็ม</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">สิทธิ์</th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">กิจกรรมที่ดูแล</th>
                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($staffs as $s): ?>
                    <tr>
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-mono text-gray-600"><?= e($s['username']) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= e($s['full_name']) ?></td>
                        <td class="px-3 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $s['role'] === 'admin' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' ?>">
                                <?= $s['role'] === 'admin' ? 'Admin' : 'Staff' ?>
                            </span>
                        </td>
                        <td class="px-3 py-4 text-sm text-gray-500">
                            <?php if($s['role'] === 'admin'): ?>
                                <span class="text-gray-400">- (ดูแลทั้งหมด) -</span>
                            <?php else: ?>
                                <?= $s['assigned_event_names'] ? e($s['assigned_event_names']) : '<span class="text-red-400">ยังไม่ระบุ</span>' ?>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button onclick='editStaff(<?= json_encode($s) ?>)' class="text-blue-600 hover:text-blue-900 mr-3"><i class="fa-solid fa-pencil"></i></button>
                            
                            <?php if($s['id'] != $_SESSION['staff_id']): ?>
                            <form action="../actions/manage_staff.php" method="POST" class="inline-block" onsubmit="return confirm('ยืนยันการลบบัญชีนี้?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="staff_id" value="<?= e($s['id']) ?>">
                                <button type="submit" class="text-red-600 hover:text-red-900"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right: Add/Edit Form -->
    <div class="bg-white p-6 rounded-xl shadow-md h-fit">
        <div class="flex justify-between items-center mb-4">
            <h2 id="form-title" class="text-xl font-bold">เพิ่มเจ้าหน้าที่ใหม่</h2>
            <button id="cancel-btn" onclick="resetForm()" class="text-sm text-red-500 hover:underline hidden"><i class="fa-solid fa-times"></i> ยกเลิกแก้ไข</button>
        </div>
        
        <form id="staff-form" action="../actions/manage_staff.php" method="POST" class="space-y-4">
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="staff_id" id="staff_id" value="">
            
            <div>
                <label class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" name="username" id="username" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2 bg-gray-50 focus:bg-white">
                <p class="text-xs text-gray-400 mt-1" id="username-hint">ใช้สำหรับเข้าสู่ระบบ (เปลี่ยนไม่ได้เมื่อสร้างแล้ว)</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">ชื่อเต็ม (Full Name)</label>
                <input type="text" name="full_name" id="full_name" required class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">รหัสผ่าน</label>
                <input type="password" name="password" id="password" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2" placeholder="กรอกเฉพาะเมื่อต้องการเปลี่ยน">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">ระดับสิทธิ์</label>
                <select name="role" id="role" class="mt-1 block w-full border border-gray-300 rounded-lg shadow-sm p-2" onchange="toggleEventSelect()">
                    <option value="staff">Staff (เจ้าหน้าที่ทั่วไป)</option>
                    <option value="admin">Admin (ผู้ดูแลระบบสูงสุด)</option>
                </select>
            </div>

            <div id="event-select-container" class="border-t pt-4 mt-2">
                <label class="block text-sm font-bold text-gray-700 mb-2">มอบหมายให้ดูแลกิจกรรม (เลือกได้มากกว่า 1)</label>
                <div class="space-y-2 max-h-60 overflow-y-auto border p-2 rounded bg-gray-50">
                    <?php foreach($events as $ev): ?>
                    <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-1 rounded">
                        <input type="checkbox" name="event_ids[]" value="<?= $ev['id'] ?>" class="form-checkbox h-4 w-4 text-blue-600 event-checkbox">
                        <span class="text-sm text-gray-700"><?= e($ev['name']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <p class="text-xs text-gray-500 mt-1">* หากเลือก Admin จะสามารถเข้าถึงได้ทุกกิจกรรมโดยอัตโนมัติ</p>
            </div>

            <button type="submit" id="submit-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition mt-4">
                <i class="fa-solid fa-plus-circle mr-2"></i> บันทึกข้อมูล
            </button>
        </form>
    </div>

</div>

<script>
function toggleEventSelect() {
    const role = document.getElementById('role').value;
    const container = document.getElementById('event-select-container');
    // ถ้าเป็น Admin ซ่อนการเลือก Event (เพราะดูได้หมด) หรือจะเปิดไว้ก็ได้แต่ไม่มีผล
    if (role === 'admin') {
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        // Uncheck all
        document.querySelectorAll('.event-checkbox').forEach(cb => cb.checked = false);
    } else {
        container.style.opacity = '1';
        container.style.pointerEvents = 'auto';
    }
}

function editStaff(staff) {
    document.getElementById('form-title').innerText = 'แก้ไขข้อมูลบัญชี';
    document.getElementById('form-action').value = 'update';
    document.getElementById('staff_id').value = staff.id;
    
    const usernameInput = document.getElementById('username');
    usernameInput.value = staff.username;
    usernameInput.readOnly = true;
    usernameInput.classList.add('bg-gray-200', 'cursor-not-allowed');
    
    document.getElementById('full_name').value = staff.full_name;
    document.getElementById('role').value = staff.role;
    document.getElementById('password').required = false;
    document.getElementById('password').placeholder = 'เว้นว่างไว้หากไม่ต้องการเปลี่ยน';
    
    document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-save mr-2"></i> บันทึกการเปลี่ยนแปลง';
    document.getElementById('submit-btn').classList.remove('bg-blue-600', 'hover:bg-blue-700');
    document.getElementById('submit-btn').classList.add('bg-yellow-500', 'hover:bg-yellow-600');
    
    document.getElementById('cancel-btn').classList.remove('hidden');

    // Reset checkboxes
    document.querySelectorAll('.event-checkbox').forEach(cb => cb.checked = false);

    // Check assigned events
    if (staff.assigned_event_ids_str) {
        const ids = staff.assigned_event_ids_str.split(',');
        ids.forEach(id => {
            const cb = document.querySelector(`.event-checkbox[value="${id}"]`);
            if (cb) cb.checked = true;
        });
    }

    toggleEventSelect();
    window.scrollTo(0, 0);
}

function resetForm() {
    document.getElementById('staff-form').reset();
    document.getElementById('form-title').innerText = 'เพิ่มเจ้าหน้าที่ใหม่';
    document.getElementById('form-action').value = 'create';
    document.getElementById('staff_id').value = '';
    
    const usernameInput = document.getElementById('username');
    usernameInput.readOnly = false;
    usernameInput.classList.remove('bg-gray-200', 'cursor-not-allowed');
    
    document.getElementById('password').required = true; // Create needs password
    document.getElementById('password').placeholder = '';
    
    document.getElementById('submit-btn').innerHTML = '<i class="fa-solid fa-plus-circle mr-2"></i> บันทึกข้อมูล';
    document.getElementById('submit-btn').classList.add('bg-blue-600', 'hover:bg-blue-700');
    document.getElementById('submit-btn').classList.remove('bg-yellow-500', 'hover:bg-yellow-600');
    
    document.getElementById('cancel-btn').classList.add('hidden');
    
    // Uncheck all
    document.querySelectorAll('.event-checkbox').forEach(cb => cb.checked = false);
    toggleEventSelect();
}
</script>

<?php include 'partials/footer.php'; ?>