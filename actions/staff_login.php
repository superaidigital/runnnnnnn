<?php
// actions/staff_login.php
// สคริปต์สำหรับตรวจสอบการล็อกอินของเจ้าหน้าที่ (Fixed: Removed 'status' check)

require_once '../config.php';
require_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: ../admin/login.php?error=empty_fields');
        exit;
    }

    // [FIX] ลบ status ออกจากคำสั่ง SELECT
    $stmt = $mysqli->prepare("SELECT id, username, password_hash, full_name, role FROM staff WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // [FIX] ปิดการเช็คสถานะไว้ก่อน (Uncomment เมื่อเพิ่มคอลัมน์ status ใน DB แล้ว)
        /*
        if ($row['status'] !== 'active') {
            header('Location: ../admin/login.php?error=account_disabled');
            exit;
        }
        */

        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['staff_id'] = $row['id'];
            
            // ดึงข้อมูลกิจกรรมที่ดูแล (รองรับทั้งแบบเก่าและใหม่)
            $event_ids = [];
            if ($row['role'] !== 'admin') {
                // ลองดึงจากตาราง staff_assignments
                $stmt_assign = $mysqli->prepare("SELECT event_id FROM staff_assignments WHERE staff_id = ?");
                $stmt_assign->bind_param("i", $row['id']);
                $stmt_assign->execute();
                $res_assign = $stmt_assign->get_result();
                while ($assign = $res_assign->fetch_assoc()) {
                    $event_ids[] = intval($assign['event_id']);
                }
                $stmt_assign->close();
                
                // Fallback: ถ้าไม่มีในตารางใหม่ ให้ลองดู field เก่า (ถ้ามี)
                if (empty($event_ids) && isset($row['assigned_event_id']) && $row['assigned_event_id'] > 0) {
                    $event_ids[] = intval($row['assigned_event_id']);
                }
            }

            $_SESSION['staff_info'] = [
                'full_name' => $row['full_name'],
                'role' => $row['role'],
                'assigned_event_ids' => $event_ids 
            ];

            header('Location: ../admin/index.php');
            exit;
        } else {
            header('Location: ../admin/login.php?error=invalid_password');
            exit;
        }
    } else {
        header('Location: ../admin/login.php?error=user_not_found');
        exit;
    }
} else {
    header('Location: ../admin/login.php');
    exit;
}
?>