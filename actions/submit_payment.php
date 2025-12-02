<?php
// actions/submit_payment.php
// สคริปต์แจ้งโอนเงิน (Secure Version: CSRF + Secure Upload)

require_once '../config.php';
require_once '../functions.php';

// [SECURITY] ตรวจสอบ CSRF Token
validate_csrf_token();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_id = isset($_POST['registration_id']) ? intval($_POST['registration_id']) : 0;
    
    if ($reg_id <= 0) {
        $_SESSION['error_message'] = "รหัสการสมัครไม่ถูกต้อง";
        header('Location: ../index.php?page=dashboard'); 
        exit;
    }

    try {
        // ตรวจสอบไฟล์
        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] !== UPLOAD_ERR_NO_FILE) {
            
            // 1. [SECURITY] อัปโหลดสลิปอย่างปลอดภัย (ตรวจสอบ MIME Type)
            $slip_path = secure_file_upload(
                $_FILES['payment_slip'], 
                '../uploads/slips/',
                ['jpg', 'jpeg', 'png', 'pdf']
            );

            // 2. ตรวจสอบสถานะปัจจุบัน (ป้องกันการแจ้งซ้ำถ้าอนุมัติแล้ว)
            $stmt_check = $mysqli->prepare("SELECT status FROM registrations WHERE id = ?");
            $stmt_check->bind_param("i", $reg_id);
            $stmt_check->execute();
            $current_status = $stmt_check->get_result()->fetch_assoc()['status'] ?? '';
            $stmt_check->close();

            if ($current_status === 'ชำระเงินแล้ว') {
                throw new Exception("รายการนี้ได้รับการอนุมัติแล้ว ไม่สามารถแจ้งโอนซ้ำได้");
            }

            // 3. อัปเดตฐานข้อมูล
            $stmt = $mysqli->prepare("UPDATE registrations SET status = 'รอตรวจสอบ', payment_slip_url = ? WHERE id = ?");
            $stmt->bind_param("si", $slip_path, $reg_id);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "แจ้งโอนเงินเรียบร้อยแล้ว กรุณารอเจ้าหน้าที่ตรวจสอบ";
            } else {
                throw new Exception("Database Error: " . $stmt->error);
            }
            $stmt->close();

        } else {
            throw new Exception("กรุณาแนบไฟล์หลักฐานการโอนเงิน");
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
    
    header('Location: ../index.php?page=dashboard');
    exit;
} else {
    header('Location: ../index.php');
    exit;
}
?>