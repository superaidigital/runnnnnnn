<?php
// actions/process_registration.php
// สคริปต์ประมวลผลการสมัคร (Complete: Passport Support + Smart Status + Secure)

require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json; charset=utf-8');

function json_response($success, $message, $redirect_url = null) {
    echo json_encode(['success' => $success, 'message' => $message, 'redirect_url' => $redirect_url], JSON_UNESCAPED_UNICODE);
    exit;
}

// 1. ตรวจสอบ Request Method
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(false, 'Invalid Request Method');
}

// 2. [SECURITY] ตรวจสอบ CSRF Token
validate_csrf_token();

try {
    // 3. รับข้อมูลและ Sanitize
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $distance_id = isset($_POST['distance_id']) ? intval($_POST['distance_id']) : 0;
    $distance_name = isset($_POST['distance_name']) ? e($_POST['distance_name']) : '';
    $shirt_size = isset($_POST['shirt_size']) ? e($_POST['shirt_size']) : '';
    $title = isset($_POST['title']) ? e($_POST['title']) : '';
    $first_name = isset($_POST['first_name']) ? e($_POST['first_name']) : '';
    $last_name = isset($_POST['last_name']) ? e($_POST['last_name']) : '';
    $gender = isset($_POST['gender']) ? e($_POST['gender']) : '';
    
    // วันเกิดส่งมาเป็น Y-m-d จาก frontend (แม้หน้าจอจะแสดงเป็นไทย)
    $birth_date = isset($_POST['birth_date']) ? e($_POST['birth_date']) : '';
    
    $thai_id = isset($_POST['thai_id']) ? trim(e($_POST['thai_id'])) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $phone = isset($_POST['phone']) ? e($_POST['phone']) : '';
    $line_id = isset($_POST['line_id']) ? e($_POST['line_id']) : null;
    
    $disease = isset($_POST['disease']) ? e($_POST['disease']) : 'ไม่มีโรคประจำตัว';
    $disease_detail = ($disease === 'มีโรคประจำตัว' && isset($_POST['disease_detail'])) ? e($_POST['disease_detail']) : null;
    
    $emergency_contact_name = isset($_POST['emergency_contact_name']) ? e($_POST['emergency_contact_name']) : null;
    $emergency_contact_phone = isset($_POST['emergency_contact_phone']) ? e($_POST['emergency_contact_phone']) : null;

    // Shipping & Total Amount
    $shipping_option = isset($_POST['shipping_option']) ? e($_POST['shipping_option']) : 'รับเอง';
    $shipping_address = ($shipping_option === 'จัดส่ง' && isset($_POST['shipping_address'])) ? e($_POST['shipping_address']) : null;
    $total_amount_client = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0.0;

    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

    // 4. Validation เบื้องต้น
    if (empty($event_id) || empty($distance_id) || empty($first_name) || empty($last_name) || empty($thai_id)) {
        json_response(false, 'ข้อมูลที่จำเป็นไม่ครบถ้วน');
    }

    // [VALIDATION] ตรวจสอบเลขบัตรประชาชน หรือ Passport (Hybrid Check)
    $is_valid_id = false;
    // กรณีที่ 1: เป็นตัวเลขล้วน 13 หลัก ให้ตรวจสอบด้วยสูตรบัตรประชาชนไทย
    if (preg_match('/^\d{13}$/', $thai_id)) {
        if (function_exists('validateThaiID')) {
             if (validateThaiID($thai_id)) {
                 $is_valid_id = true;
             }
        } else {
             // Fallback ถ้าไม่มี function เช็คสูตร (เพื่อป้องกัน Error)
             $is_valid_id = true; 
        }
    } 
    // กรณีที่ 2: เป็นตัวเลขผสมตัวอักษร (6-20 หลัก) ให้ถือว่าเป็น Passport
    elseif (preg_match('/^[A-Za-z0-9]{6,20}$/', $thai_id)) {
        $is_valid_id = true;
    }

    if (!$is_valid_id) {
        json_response(false, 'รูปแบบหมายเลขบัตรประชาชน หรือ Passport ไม่ถูกต้อง');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'รูปแบบอีเมลไม่ถูกต้อง');
    }

    // 5. [SECURITY] ตรวจสอบราคาฝั่ง Server (Server-side Price Validation)
    $stmt_prices = $mysqli->prepare("
        SELECT 
            e.enable_shipping, e.shipping_cost, e.payment_deadline, e.start_date,
            d.price AS distance_price
        FROM events e
        JOIN distances d ON d.event_id = e.id
        WHERE e.id = ? AND d.id = ?
    ");
    $stmt_prices->bind_param("ii", $event_id, $distance_id);
    $stmt_prices->execute();
    $result_prices = $stmt_prices->get_result();
    if ($result_prices->num_rows === 0) {
        throw new Exception("ไม่พบข้อมูลกิจกรรมหรือระยะทาง");
    }
    $price_data = $result_prices->fetch_assoc();
    $stmt_prices->close();

    // คำนวณยอดเงินที่ถูกต้อง
    $race_price = floatval($price_data['distance_price']);
    $shipping_cost = ($price_data['enable_shipping'] == 1 && $shipping_option === 'จัดส่ง') ? floatval($price_data['shipping_cost']) : 0;
    $expected_total_amount = $race_price + $shipping_cost;

    // เปรียบเทียบยอดเงิน (อนุญาตให้ต่างกันได้เล็กน้อยทศนิยม)
    if (abs($expected_total_amount - $total_amount_client) > 0.01) {
        error_log("Price Mismatch ID:$thai_id - Expected: $expected_total_amount, Sent: $total_amount_client");
        json_response(false, "ยอดชำระเงินไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง");
    }

    $payLaterEnabled = $price_data['payment_deadline'] != null;

    // 6. ตรวจสอบการสมัครซ้ำ (Duplicate Check)
    $stmt_dup = $mysqli->prepare("SELECT id FROM registrations WHERE event_id = ? AND thai_id = ?");
    $stmt_dup->bind_param("is", $event_id, $thai_id);
    $stmt_dup->execute();
    if ($stmt_dup->get_result()->num_rows > 0) {
        json_response(false, 'หมายเลขบัตรประชาชน/Passport นี้ได้สมัครกิจกรรมนี้ไปแล้ว');
    }
    $stmt_dup->close();

    // 7. คำนวณอายุและค้นหารุ่นการแข่งขัน (Race Category Calculation)
    $race_category_id = null;
    if (!empty($birth_date) && !empty($price_data['start_date'])) {
        $birthDateObj = new DateTime($birth_date);
        $eventDateObj = new DateTime($price_data['start_date']);
        $age = $eventDateObj->diff($birthDateObj)->y;

        $cat_stmt = $mysqli->prepare("
            SELECT id FROM race_categories 
            WHERE event_id = ? AND distance = ? AND gender = ? AND minAge <= ? AND maxAge >= ? 
            LIMIT 1
        ");
        $cat_stmt->bind_param("isssi", $event_id, $distance_name, $gender, $age, $age);
        $cat_stmt->execute();
        $cat_res = $cat_stmt->get_result();
        if ($row = $cat_res->fetch_assoc()) {
            $race_category_id = intval($row['id']);
        }
        $cat_stmt->close();
    }

    // 8. จัดการอัปโหลดสลิปและกำหนดสถานะ (Upload & Status Logic)
    $payment_slip_url = null;
    $status = 'รอชำระเงิน'; // สถานะเริ่มต้น (กรณี Pay Later หรือยังไม่แนบ)

    // ตรวจสอบว่ามีการแนบไฟล์มาหรือไม่
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] != UPLOAD_ERR_NO_FILE) {
        try {
            // อัปโหลดไฟล์
            $payment_slip_url = secure_file_upload($_FILES['payment_slip'], '../uploads/slips/', ['jpg', 'jpeg', 'png', 'pdf']);
            
            // [CRITICAL] หากมีการแนบไฟล์สลิปสำเร็จ ให้เปลี่ยนสถานะเป็น 'รอตรวจสอบ' ทันที
            $status = 'รอตรวจสอบ'; 
            
        } catch (Exception $e) {
            json_response(false, 'เกิดปัญหาในการอัปโหลดสลิป: ' . $e->getMessage());
        }
    } elseif (!$payLaterEnabled) {
        // หากไม่มีไฟล์แนบ และกิจกรรม "ไม่อนุญาต" ให้จ่ายทีหลัง (Pay Later) -> ต้องแจ้ง error
        json_response(false, 'กรุณาอัปโหลดหลักฐานการโอนเงิน');
    }
    // หากไม่มีไฟล์แนบ แต่กิจกรรม "อนุญาต" ให้จ่ายทีหลัง -> จะใช้สถานะ default คือ 'รอชำระเงิน'

    // 9. บันทึกลงฐานข้อมูล
    $db_shipping_option = ($shipping_option === 'จัดส่ง') ? 'delivery' : 'pickup';
    $registration_code = 'RUN' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));

    $mysqli->begin_transaction();

    $sql = "INSERT INTO registrations (
                registration_code, user_id, event_id, distance_id, race_category_id,
                shirt_size, title, first_name, last_name, gender,
                birth_date, thai_id, email, phone, line_id,
                disease, disease_detail, emergency_contact_name, emergency_contact_phone,
                payment_slip_url, status, shipping_option, shipping_address, total_amount, registered_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("siiiissssssssssssssssssd", 
        $registration_code, $user_id, $event_id, $distance_id, $race_category_id,
        $shirt_size, $title, $first_name, $last_name, $gender,
        $birth_date, $thai_id, $email, $phone, $line_id,
        $disease, $disease_detail, $emergency_contact_name, $emergency_contact_phone,
        $payment_slip_url, $status, $db_shipping_option, $shipping_address, $expected_total_amount
    );

    if (!$stmt->execute()) {
        throw new Exception("Database Insert Failed: " . $stmt->error);
    }
    $stmt->close();

    // อัปเดตที่อยู่ผู้ใช้ หากเลือกจัดส่งและมี user_id (เพื่อให้ครั้งหน้าไม่ต้องกรอกใหม่)
    if ($user_id && $db_shipping_option === 'delivery' && !empty($shipping_address)) {
        $upd_addr = $mysqli->prepare("UPDATE users SET address = ? WHERE id = ?");
        $upd_addr->bind_param("si", $shipping_address, $user_id);
        $upd_addr->execute();
        $upd_addr->close();
    }

    $mysqli->commit();

    $_SESSION['success_message'] = "การสมัครเสร็จสมบูรณ์! รหัสของคุณคือ: " . $registration_code;
    json_response(true, 'Registration Successful', 'index.php?page=dashboard');

} catch (Exception $e) {
    $mysqli->rollback();
    error_log($e->getMessage());
    json_response(false, 'เกิดข้อผิดพลาด: ' . $e->getMessage());
}
?>