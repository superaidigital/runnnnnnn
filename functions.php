<?php
// functions.php
// ไฟล์สำหรับรวบรวมฟังก์ชันที่ใช้งานบ่อยทั่วทั้งระบบ (Core Functions)
// [UPDATED] เพิ่ม get_global_settings กลับเข้ามา

/**
 * ฟังก์ชันสำหรับ Sanitize ข้อมูลจากผู้ใช้เพื่อป้องกัน XSS
 */
function e($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * ฟังก์ชันสำหรับดึงข้อมูลการตั้งค่า Global จากฐานข้อมูล
 * (แก้ไข: เพิ่มฟังก์ชันนี้กลับเข้ามาเพื่อแก้ Fatal Error)
 */
function get_global_settings($db) {
    $settings = [];
    if ($db) {
        $result = $db->query("SELECT setting_key, setting_value FROM settings");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    return $settings;
}

/**
 * ฟังก์ชันสำหรับสร้าง CSRF Token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ฟังก์ชันสำหรับตรวจสอบ CSRF Token
 */
function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        error_log("CSRF Token Validation Failed from IP: " . $_SERVER['REMOTE_ADDR']);
        die('Security Check Failed: Invalid CSRF Token. Please reload the page and try again.');
    }
}

/**
 * ฟังก์ชันสำหรับอัปโหลดไฟล์แบบปลอดภัย (Secure File Upload)
 */
function secure_file_upload($file, $destination_folder, $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("เกิดข้อผิดพลาดในการอัปโหลด (Code: " . $file['error'] . ")");
    }
    if ($file['size'] > $max_size) {
        throw new Exception("ไฟล์มีขนาดใหญ่เกินไป (สูงสุด " . ($max_size / 1048576) . " MB)");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    $valid_mimes = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'pdf'  => 'application/pdf'
    ];

    $is_valid_mime = false;
    $extension_mapped = '';
    
    foreach ($allowed_types as $ext) {
        if (isset($valid_mimes[$ext]) && $valid_mimes[$ext] === $mime_type) {
            $is_valid_mime = true;
            $extension_mapped = $ext;
            break;
        }
    }

    if (!$is_valid_mime) {
        throw new Exception("ประเภทไฟล์ไม่ถูกต้อง (Detected: $mime_type)");
    }

    if ($extension_mapped === 'jpeg') $extension_mapped = 'jpg';
    $new_filename = bin2hex(random_bytes(16)) . '.' . $extension_mapped;

    $target_path = rtrim($destination_folder, '/') . '/' . $new_filename;
    
    if (!is_dir($destination_folder)) {
        if (!mkdir($destination_folder, 0755, true)) {
            throw new Exception("ไม่สามารถสร้างโฟลเดอร์ปลายทางได้");
        }
    }

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        throw new Exception("เกิดข้อผิดพลาดในการบันทึกไฟล์");
    }

    return str_replace('../', '', $target_path);
}

/**
 * ฟังก์ชันสำหรับแสดงวันที่และเวลาภาษาไทย
 */
function formatThaiDateTime($dateStr) {
    if (empty($dateStr) || $dateStr == '0000-00-00 00:00:00' || $dateStr == '0000-00-00') return '-';
    $thai_months = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];
    $date = new DateTime($dateStr);
    $y = $date->format('Y') + 543;
    $m = $thai_months[(int)$date->format('n')];
    $d = $date->format('j');
    $t = $date->format('H:i');
    return ($t == '00:00') ? "$d $m $y" : "$d $m $y เวลา $t น.";
}

/**
 * ฟังก์ชันเดิม: แปลงวันที่เป็นรูปแบบไทย (วัน เดือน ปี) เต็ม
 */
function formatThaiDate($dateStr) {
    if (empty($dateStr) || $dateStr === '0000-00-00') return null;
    try {
        $thai_months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        $date = new DateTime($dateStr);
        $day = $date->format('j');
        $month = $thai_months[$date->format('n') - 1];
        $year = (int)$date->format('Y') + 543; 
        return "$day $month $year";
    } catch (Exception $e) { return null; }
}

function mask_email($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
    list($user, $domain) = explode('@', $email);
    $len = strlen($user);
    $visible = floor($len / 2);
    return substr($user, 0, $visible) . str_repeat('*', $len - $visible) . '@' . $domain;
}

function mask_phone($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) >= 10) return substr($phone, 0, 3) . '-XXX-' . substr($phone, -4);
    return $phone;
}

function validateThaiID($id) {
    if (!preg_match('/^\d{13}$/', $id)) return false;
    $sum = 0;
    for ($i = 0; $i < 12; $i++) $sum += (int)$id[$i] * (13 - $i);
    return (int)$id[12] === (11 - ($sum % 11)) % 10;
}
?>