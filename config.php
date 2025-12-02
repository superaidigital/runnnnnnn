<?php
// config.php
// ไฟล์สำหรับตั้งค่าการเชื่อมต่อฐานข้อมูลและค่าพื้นฐานของระบบ
// [UPDATED] รองรับ Environment Variables เพื่อความปลอดภัย

// --- การตั้งค่าการเชื่อมต่อฐานข้อมูล ---
// พยายามอ่านค่าจาก Environment Variables ก่อน (สำหรับ Production)
// หากไม่มี ให้ใช้ค่า Default (สำหรับ Development)

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'pao_run_db2');

// --- เริ่มการทำงานของ Session ---
// ใช้สำหรับจัดการการ login ของผู้ใช้และเจ้าหน้าที่
if (session_status() == PHP_SESSION_NONE) {
    // ตั้งค่า Session Security ขั้นพื้นฐาน
    ini_set('session.cookie_httponly', 1); // ป้องกัน JavaScript เข้าถึง Cookie
    ini_set('session.use_only_cookies', 1); // บังคับใช้เฉพาะ Cookie ไม่ใช้ URL parameter
    session_start();
}

// --- สร้างการเชื่อมต่อฐานข้อมูล ---
try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // --- ตรวจสอบการเชื่อมต่อ ---
    if ($mysqli->connect_error) {
        // บันทึก Error ลง Log แทนการแสดงผลหน้าจอโดยตรงเพื่อความปลอดภัย
        error_log("Connection failed: " . $mysqli->connect_error);
        die("ขออภัย ระบบฐานข้อมูลมีปัญหาชั่วคราว");
    }

    // --- ตั้งค่า Character Set เป็น UTF-8 ---
    // เพื่อให้รองรับภาษาไทยได้อย่างถูกต้อง
    $mysqli->set_charset("utf8mb4");

} catch (Exception $e) {
    error_log("Database Exception: " . $e->getMessage());
    die("ขออภัย ระบบกำลังปิดปรับปรุงชั่วคราว");
}

// --- กำหนด Timezone พื้นฐาน ---
date_default_timezone_set('Asia/Bangkok');

// --- ป้องกันการแสดง Error ใน Production ---
// (ควรเปิดเฉพาะตอน Dev)
// ini_set('display_errors', 0);
// error_reporting(0);
?>