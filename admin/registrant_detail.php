<?php
// admin/registrant_detail.php
// หน้ารายละเอียดผู้สมัครรายบุคคล (Fixed: LEFT JOIN & Permission Logic)

// --- 1. CORE BOOTSTRAP ---
require_once '../config.php';
require_once '../functions.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['staff_id'])) {
    header('Location: login.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$is_super_admin = ($staff_info['role'] === 'admin');

// เตรียมรายการ Event IDs ที่ Staff คนนี้ดูแล
$allowed_events = [];
if (!$is_super_admin) {
    if (isset($staff_info['assigned_event_ids']) && is_array($staff_info['assigned_event_ids'])) {
        $allowed_events = array_map('intval', $staff_info['assigned_event_ids']);
    } elseif (isset($staff_info['assigned_event_id'])) {
        $allowed_events = [intval($staff_info['assigned_event_id'])];
    }
}
// --- END BOOTSTRAP ---

$page_title = 'รายละเอียดผู้สมัคร';

// --- 2. Get & Validate Registration ID ---
if (!isset($_GET['reg_id']) || !is_numeric($_GET['reg_id'])) {
    include 'partials/header.php'; 
    echo "<div class='p-6'><div class='bg-red-100 text-red-700 p-4 rounded shadow-sm border-l-4 border-red-500'>
            <strong>ข้อผิดพลาด:</strong> ไม่พบรหัสอ้างอิงการสมัคร (Invalid ID)
            <div class='mt-2'><a href='index.php' class='text-red-800 underline hover:text-red-900'>กลับสู่หน้าหลัก</a></div>
          </div></div>"; 
    include 'partials/footer.php'; 
    exit;
}
$reg_id = intval($_GET['reg_id']);

// --- 3. Fetch Data (ใช้ LEFT JOIN ป้องกัน Error หากข้อมูลแม่ข่ายหาย) ---
$stmt = $mysqli->prepare("
    SELECT
        r.*,
        COALESCE(e.name, 'ไม่พบข้อมูลกิจกรรม') AS event_name, 
        e.start_date, 
        e.event_code,
        COALESCE(d.name, 'ไม่ระบุ') AS distance_name, 
        d.price,
        rc.name AS category_name, 
        rc.minAge, 
        rc.maxAge
    FROM registrations r
    LEFT JOIN events e ON r.event_id = e.id
    LEFT JOIN distances d ON r.distance_id = d.id
    LEFT JOIN race_categories rc ON r.race_category_id = rc.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $reg_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    include 'partials/header.php'; 
    echo "<div class='p-6'><div class='bg-yellow-100 text-yellow-800 p-4 rounded shadow-sm border-l-4 border-yellow-500'>
            <strong>ไม่พบข้อมูล:</strong> ไม่มีใบสมัครหมายเลข ID: $reg_id ในระบบ
            <div class='mt-2'><a href='index.php' class='text-yellow-900 underline'>กลับสู่หน้าหลัก</a></div>
          </div></div>"; 
    include 'partials/footer.php'; 
    exit;
}
$reg = $result->fetch_assoc();
$stmt->close();

// --- 4. Permission Check (Multi-Event Support) ---
// หากไม่ใช่ Admin และ Event ID ของใบสมัครไม่อยู่ในรายการที่รับผิดชอบ
// และต้องเช็คด้วยว่า reg['event_id'] ไม่ใช่ NULL (กรณี Event ถูกลบไปแล้ว Admin ควรดูได้)
if (!$is_super_admin && !empty($reg['event_id']) && !in_array(intval($reg['event_id']), $allowed_events)) {
    include 'partials/header.php'; 
    echo "<div class='p-6'><div class='bg-red-100 text-red-700 p-4 rounded shadow-sm border-l-4 border-red-500'>
            <strong>ไม่มีสิทธิ์เข้าถึง:</strong> คุณไม่ได้รับอนุญาตให้จัดการข้อมูลของผู้สมัครในกิจกรรมนี้
            <div class='mt-2'><a href='index.php' class='text-red-900 underline'>กลับสู่หน้าหลัก</a></div>
          </div></div>"; 
    include 'partials/footer.php'; 
    exit;
}

// --- 5. Logic คำนวณอายุและตรวจสอบรุ่น ---
$age_at_event_str = '-';
$category_verification_html = '';

if (!empty($reg['birth_date'])) {
    $targetDateStr = !empty($reg['start_date']) ? $reg['start_date'] : date('Y-m-d');
    try {
        $birthDate = new DateTime($reg['birth_date']);
        $eventDate = new DateTime($targetDateStr);
        $age = $eventDate->diff($birthDate)->y;
        $age_at_event_str = $age . ' ปี';

        if (!empty($reg['category_name'])) {
            $minAge = isset($reg['minAge']) ? $reg['minAge'] : 0;
            $maxAge = isset($reg['maxAge']) ? $reg['maxAge'] : 999;

            if ($age >= $minAge && $age <= $maxAge) {
                $category_verification_html = '<span class="text-green-600 text-xs ml-2 bg-green-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-check-circle"></i> ตรงเกณฑ์</span>';
            } else {
                $category_verification_html = '<span class="text-red-600 text-xs ml-2 bg-red-100 px-2 py-0.5 rounded-full"><i class="fa-solid fa-exclamation-triangle"></i> ไม่ตรงรุ่น (เกณฑ์: '.$minAge.'-'.$maxAge.')</span>';
            }
        }
    } catch (Exception $e) { }
}

// รับข้อความแจ้งเตือนจาก Session
$msg_success = $_SESSION['update_success'] ?? null; unset($_SESSION['update_success']);
$msg_error = $_SESSION['update_error'] ?? null; unset($_SESSION['update_error']);

include 'partials/header.php';
?>

<!-- Header & Navigation -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
    <div>
        <div class="flex items-center gap-2 text-gray-500 text-sm mb-1">
            <a href="index.php" class="hover:text-indigo-600 transition"><i class="fa-solid fa-home"></i> หน้าหลัก</a>
            <i class="fa-solid fa-chevron-right text-xs"></i>
            <a href="registrants.php?event_id=<?= e($reg['event_id']) ?>" class="hover:text-indigo-600 transition">รายชื่อผู้สมัคร</a>
            <i class="fa-solid fa-chevron-right text-xs"></i>
            <span>รายละเอียด</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">รายละเอียดผู้สมัคร</h1>
        <p class="text-gray-600 font-mono text-sm mt-1">RUNNER ID: <span class="bg-gray-200 px-2 py-0.5 rounded text-gray-800"><?= e($reg['registration_code']) ?></span></p>
    </div>
    
    <div class="flex gap-2">
         <a href="registrants.php?event_id=<?= e($reg['event_id']) ?>" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold py-2 px-4 rounded-lg text-sm shadow-sm transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> กลับไปหน้ารายชื่อ
        </a>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($msg_success): ?>
    <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-r shadow-sm flex items-start gap-3">
        <i class="fa-solid fa-check-circle mt-1 text-lg"></i>
        <div><?= e($msg_success) ?></div>
    </div>
<?php endif; ?>
<?php if ($msg_error): ?>
    <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-r shadow-sm flex items-start gap-3">
        <i class="fa-solid fa-circle-exclamation mt-1 text-lg"></i>
        <div><?= e($msg_error) ?></div>
    </div>
<?php endif; ?>

<!-- Main Form -->
<form action="../actions/update_registration.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="reg_id" value="<?= e($reg['id']) ?>">
    <input type="hidden" name="event_id" value="<?= e($reg['event_id']) ?>">
    <!-- ส่ง redirect_url เพื่อให้กลับมาหน้านี้หลังบันทึก -->
    <input type="hidden" name="redirect_url" value="../admin/registrant_detail.php?reg_id=<?= e($reg['id']) ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Information -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Personal Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="font-bold text-gray-800"><i class="fa-solid fa-user-circle mr-2 text-indigo-500"></i> ข้อมูลส่วนตัว</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">ชื่อ-นามสกุล</label>
                        <div class="font-semibold text-gray-900 text-base"><?= e($reg['title'] . $reg['first_name'] . ' ' . $reg['last_name']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">เพศ</label>
                        <div class="font-medium text-gray-800"><?= e($reg['gender'] ?: '-') ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">เลขบัตรประชาชน / Passport</label>
                        <div class="font-mono text-gray-700 bg-gray-50 inline-block px-2 py-0.5 rounded border border-gray-200"><?= e($reg['thai_id']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">วันเกิด (พ.ศ.)</label>
                        <div class="font-medium text-gray-800"><?= e($reg['birth_date'] ? formatThaiDate($reg['birth_date']) : '-') ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">อายุ (ณ วันแข่งขัน)</label>
                        <div class="font-bold text-indigo-600 text-lg"><?= $age_at_event_str ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">เบอร์โทรศัพท์</label>
                        <div class="font-medium text-gray-800"><i class="fa-solid fa-phone text-gray-400 mr-1"></i> <?= e($reg['phone']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">อีเมล</label>
                        <div class="font-medium text-gray-800"><i class="fa-solid fa-envelope text-gray-400 mr-1"></i> <?= e($reg['email']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">Line ID</label>
                        <div class="font-medium text-green-600"><i class="fa-brands fa-line mr-1"></i> <?= e($reg['line_id'] ?: '-') ?></div>
                    </div>
                    
                    <div class="md:col-span-2 pt-4 border-t border-gray-100 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-red-50 p-3 rounded-lg border border-red-100">
                            <label class="block text-xs text-red-500 font-bold mb-1">โรคประจำตัว</label>
                            <div class="font-medium text-red-700"><?= e($reg['disease']) ?></div>
                            <?php if ($reg['disease_detail']): ?>
                                <div class="text-xs text-red-600 mt-1 bg-white p-1 rounded bg-opacity-50"><?= e($reg['disease_detail']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <label class="block text-xs text-gray-500 font-bold mb-1">ผู้ติดต่อฉุกเฉิน</label>
                            <div class="font-medium text-gray-800"><?= e($reg['emergency_contact_name'] ?: '-') ?></div>
                            <div class="text-sm text-gray-600"><i class="fa-solid fa-phone mr-1"></i> <?= e($reg['emergency_contact_phone'] ?: '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Race Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                    <h2 class="font-bold text-gray-800"><i class="fa-solid fa-flag-checkered mr-2 text-indigo-500"></i> ข้อมูลการแข่งขัน</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">กิจกรรม</label>
                        <div class="font-bold text-gray-900 text-lg"><?= e($reg['event_name']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">ระยะทาง</label>
                        <div class="font-bold text-xl text-indigo-600"><?= e($reg['distance_name']) ?></div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">รุ่นการแข่งขัน</label>
                        <div class="font-medium text-gray-800">
                            <?= e($reg['category_name'] ?: 'ไม่จำกัดรุ่น') ?>
                            <?= $category_verification_html ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">ขนาดเสื้อ</label>
                        <div class="inline-block font-bold bg-gray-100 px-3 py-1 rounded text-gray-800 border border-gray-200"><?= e($reg['shirt_size']) ?></div>
                    </div>
                     <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider mb-1">วันที่ทำรายการ</label>
                        <div class="text-gray-600"><?= formatThaiDateTime($reg['registered_at']) ?></div>
                    </div>
                    
                    <div class="md:col-span-2 mt-2 pt-2 border-t border-dashed border-gray-300">
                         <div class="flex items-center justify-between mb-2">
                             <label class="text-xs text-gray-500 uppercase font-bold">การรับอุปกรณ์ (Race Kit)</label>
                             <span class="font-bold text-gray-800"><?= e($reg['shipping_option'] === 'delivery' ? 'จัดส่งทางไปรษณีย์' : 'รับเองหน้างาน') ?></span>
                         </div>
                         <?php if ($reg['shipping_option'] === 'delivery'): ?>
                            <div class="p-3 bg-blue-50 rounded text-gray-700 text-sm border border-blue-100 flex items-start">
                                <i class="fa-solid fa-location-dot mt-1 mr-2 text-blue-500"></i>
                                <span><?= nl2br(e($reg['shipping_address'])) ?></span>
                            </div>
                         <?php endif; ?>
                    </div>
                    
                    <div class="md:col-span-2">
                        <div class="flex justify-between items-center p-4 bg-gray-800 rounded-lg text-white shadow-sm">
                            <span class="font-medium">ยอดชำระทั้งหมด</span>
                            <span class="font-bold text-2xl"><?= number_format((float)$reg['total_amount'], 2) ?> <span class="text-sm font-normal text-gray-400">THB</span></span>
                        </div>
                    </div>
                 </div>
            </div>
        </div>

        <!-- Right Column: Payment & Management -->
        <div class="space-y-6">
            
            <!-- Payment Slip Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
                    <h2 class="font-bold text-gray-800"><i class="fa-solid fa-file-invoice-dollar mr-2 text-indigo-500"></i> หลักฐานการชำระเงิน</h2>
                </div>
                <div class="p-6">
                    <?php if (!empty($reg['payment_slip_url'])): ?>
                        <div class="relative group cursor-pointer rounded-lg overflow-hidden border border-gray-200 shadow-sm">
                            <a href="../<?= e($reg['payment_slip_url']) ?>" target="_blank">
                                <img src="../<?= e($reg['payment_slip_url']) ?>" alt="Payment Slip" class="w-full h-auto object-contain max-h-80 bg-gray-100">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-40 transition flex items-center justify-center">
                                    <span class="text-white opacity-0 group-hover:opacity-100 font-bold flex items-center gap-2 bg-black bg-opacity-50 px-3 py-1 rounded-full">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i> คลิกเพื่อขยาย
                                    </span>
                                </div>
                            </a>
                        </div>
                        <div class="mt-3 text-center">
                             <a href="../<?= e($reg['payment_slip_url']) ?>" download class="text-xs text-indigo-600 hover:underline"><i class="fa-solid fa-download"></i> ดาวน์โหลดรูปภาพ</a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-10 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                            <i class="fa-solid fa-image text-gray-300 text-5xl mb-3"></i>
                            <p class="text-gray-500 font-medium">ยังไม่มีการแนบสลิป</p>
                            <p class="text-xs text-gray-400 mt-1">ผู้สมัครอาจเลือกชำระภายหลัง</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Management Actions Card -->
            <div class="bg-white rounded-xl shadow-md border border-indigo-100 overflow-hidden">
                 <div class="bg-indigo-600 px-6 py-3 border-b border-indigo-700">
                    <h2 class="font-bold text-white"><i class="fa-solid fa-sliders mr-2"></i> จัดการข้อมูล</h2>
                 </div>
                 <div class="p-6 space-y-5">
                     
                     <!-- Status Selector -->
                     <div>
                         <label for="status" class="block text-sm font-bold text-gray-700 mb-1">สถานะการสมัคร</label>
                         <div class="relative">
                             <select id="status" name="status" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                 <option value="รอชำระเงิน" <?= $reg['status'] == 'รอชำระเงิน' ? 'selected' : '' ?>>🔴 รอชำระเงิน</option>
                                 <option value="รอตรวจสอบ" <?= $reg['status'] == 'รอตรวจสอบ' ? 'selected' : '' ?>>🟡 รอตรวจสอบ (มีสลิป)</option>
                                 <option value="ชำระเงินแล้ว" <?= $reg['status'] == 'ชำระเงินแล้ว' ? 'selected' : '' ?>>🟢 ชำระเงินแล้ว (อนุมัติ)</option>
                             </select>
                         </div>
                     </div>

                     <!-- BIB Number -->
                     <div>
                         <label for="bib_number" class="block text-sm font-bold text-gray-700 mb-1">หมายเลข BIB</label>
                         <input type="text" id="bib_number" name="bib_number" value="<?= e($reg['bib_number']) ?>" 
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md font-mono bg-gray-50" 
                                placeholder="Auto-assign if empty">
                         <p class="text-xs text-gray-500 mt-1 italic">* เว้นว่างไว้ ระบบจะรันเลขให้อัตโนมัติเมื่ออนุมัติ</p>
                     </div>
                     
                     <!-- Corral -->
                      <div>
                         <label for="corral" class="block text-sm font-bold text-gray-700 mb-1">กลุ่มปล่อยตัว (Corral)</label>
                         <input type="text" id="corral" name="corral" value="<?= e($reg['corral']) ?>" 
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" 
                                placeholder="เช่น A, B, C">
                     </div>

                     <!-- Shipping Option -->
                     <div>
                         <label for="shipping_option" class="block text-sm font-bold text-gray-700 mb-1">วิธีรับอุปกรณ์ (Race Kit)</label>
                         <select id="shipping_option" name="shipping_option" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                             <option value="pickup" <?= $reg['shipping_option'] == 'pickup' ? 'selected' : '' ?>>รับเองหน้างาน</option>
                             <option value="delivery" <?= $reg['shipping_option'] == 'delivery' ? 'selected' : '' ?>>จัดส่งทางไปรษณีย์</option>
                         </select>
                     </div>

                     <div class="border-t border-gray-200 my-4"></div>

                     <!-- [NEW] Admin Slip Upload -->
                     <div class="bg-gray-50 p-3 rounded-lg border border-dashed border-gray-300">
                        <label class="block text-sm font-bold text-gray-700 mb-2">
                            <i class="fa-solid fa-upload mr-1 text-gray-500"></i> แนบ/แก้ไขสลิป (โดย Admin)
                        </label>
                        <input type="file" name="payment_slip_admin" accept="image/jpeg,image/png,application/pdf" 
                               class="block w-full text-xs text-gray-500
                                      file:mr-4 file:py-2 file:px-4
                                      file:rounded-full file:border-0
                                      file:text-xs file:font-semibold
                                      file:bg-indigo-100 file:text-indigo-700
                                      hover:file:bg-indigo-200
                                      cursor-pointer">
                        <p class="text-xs text-gray-400 mt-1 ml-1">อัปโหลดไฟล์ใหม่เพื่อแทนที่อันเดิม (ถ้ามี)</p>
                     </div>

                     <!-- Save Button -->
                     <button type="submit" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-200">
                         <i class="fa-solid fa-save mr-2 mt-0.5"></i> บันทึกการเปลี่ยนแปลง
                     </button>
                 </div>
            </div>
        </div>
    </div>
</form>

<?php include 'partials/footer.php'; ?>