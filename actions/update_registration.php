<?php
// actions/update_registration.php
// สคริปต์สำหรับอัปเดตข้อมูลการสมัคร (สถานะ, BIB, Corral, Shipping, และแนบสลิปโดย Admin)

require_once '../config.php';
require_once '../functions.php';

// --- 1. Session Check for Staff ---
if (!isset($_SESSION['staff_id'])) {
    header('Location: ../admin/login.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$is_super_admin = ($staff_info['role'] === 'admin');


// --- 2. Check Request Method ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- 3. Get and Sanitize Data ---
    $reg_id = isset($_POST['reg_id']) ? intval($_POST['reg_id']) : 0;
    $event_id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $new_status = isset($_POST['status']) ? e($_POST['status']) : '';
    $manual_bib_number = isset($_POST['bib_number']) ? e(trim($_POST['bib_number'])) : null;
    $new_shipping_option = isset($_POST['shipping_option']) ? e($_POST['shipping_option']) : 'pickup';
    $manual_corral = isset($_POST['corral']) ? e(trim($_POST['corral'])) : null;
    
    // Redirect URL logic (default to details page if not provided)
    $redirect_url = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : '../admin/registrant_detail.php?reg_id=' . $reg_id;

    // --- 4. Basic Validation ---
    if ($reg_id === 0 || $event_id === 0 || empty($new_status)) {
        $_SESSION['update_error'] = "ข้อมูลที่ส่งมาไม่ถูกต้อง (Missing ID or Status)";
        header('Location: ../admin/registrants.php?event_id=' . $event_id);
        exit;
    }

    // --- 5. Permission Check ---
    if (!$is_super_admin) {
        $stmt_check = $mysqli->prepare("SELECT event_id FROM registrations WHERE id = ?");
        $stmt_check->bind_param("i", $reg_id);
        $stmt_check->execute();
        $reg_event = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();
        
        // ถ้า Event ID ของผู้สมัคร ไม่ตรงกับที่ Staff ได้รับมอบหมาย
        if (!$reg_event || $reg_event['event_id'] !== $staff_info['assigned_event_id']) {
            $_SESSION['update_error'] = "คุณไม่มีสิทธิ์จัดการข้อมูลของผู้สมัครรายนี้";
            header('Location: ../admin/index.php');
            exit;
        }
    }

    // --- 6. Begin Transaction ---
    $mysqli->begin_transaction();

    try {
        // ดึงข้อมูลปัจจุบันเพื่อเปรียบเทียบ (เช่น มี BIB หรือยัง)
        $stmt_current = $mysqli->prepare("SELECT status, bib_number, payment_slip_url FROM registrations WHERE id = ?");
        $stmt_current->bind_param("i", $reg_id);
        $stmt_current->execute();
        $current_reg = $stmt_current->get_result()->fetch_assoc();
        $stmt_current->close();

        $bib_to_update = $manual_bib_number;
        $corral_to_update = $manual_corral;
        
        // ตัวแปรสำหรับการสร้าง Query
        $slip_sql_part = ""; 
        $sql_params = [$new_status]; 
        $sql_types = "s"; 

        // --- 7. Handle Slip Upload (Admin) ---
        if (isset($_FILES['payment_slip_admin']) && $_FILES['payment_slip_admin']['error'] === UPLOAD_ERR_OK) {
            // ตรวจสอบโฟลเดอร์ปลายทาง
            $upload_dir = '../uploads/slips/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // ใช้ฟังก์ชัน secure_file_upload จาก functions.php
            // อัปโหลดไฟล์ใหม่
            $new_slip_path = secure_file_upload($_FILES['payment_slip_admin'], $upload_dir, ['jpg', 'jpeg', 'png', 'pdf']);
            
            // เตรียม SQL part สำหรับอัปเดต path รูปภาพ
            $slip_sql_part = ", payment_slip_url = ?";
            
            // (Optional: ลบไฟล์เก่าทิ้งเพื่อประหยัดพื้นที่)
            // if (!empty($current_reg['payment_slip_url']) && file_exists('../' . $current_reg['payment_slip_url'])) {
            //    unlink('../' . $current_reg['payment_slip_url']);
            // }

        } elseif (isset($_FILES['payment_slip_admin']) && $_FILES['payment_slip_admin']['error'] !== UPLOAD_ERR_NO_FILE) {
             // กรณีมีไฟล์ส่งมาแต่ Error (เช่น ไฟล์ใหญ่เกิน)
             throw new Exception("การอัปโหลดไฟล์ล้มเหลว (Code: " . $_FILES['payment_slip_admin']['error'] . ")");
        }

        // --- 8. Auto-Assign BIB Logic ---
        // ทำงานเมื่อสถานะเปลี่ยนเป็น "ชำระเงินแล้ว" และยังไม่มี BIB (ทั้งใน DB และที่กรอกมา)
        if ($new_status === 'ชำระเงินแล้ว' && empty($current_reg['bib_number']) && empty($manual_bib_number)) {
            
            // Lock row events เพื่อกันแย่งเลข BIB กัน (Concurrency Control)
            $stmt_event = $mysqli->prepare("SELECT bib_prefix, bib_padding, bib_next_number, corral_settings FROM events WHERE id = ? FOR UPDATE");
            $stmt_event->bind_param("i", $event_id);
            $stmt_event->execute();
            $event_settings = $stmt_event->get_result()->fetch_assoc();
            $stmt_event->close();

            if ($event_settings) {
                $next_bib_num = $event_settings['bib_next_number'];
                
                // สร้างเลข BIB (Prefix + เลขรัน + Padding)
                $bib_number_padded = str_pad($next_bib_num, $event_settings['bib_padding'], '0', STR_PAD_LEFT);
                $bib_to_update = ($event_settings['bib_prefix'] ?? '') . $bib_number_padded;

                // คำนวณ Corral อัตโนมัติ (ถ้ามีการตั้งค่าไว้ และไม่ได้กรอกมือมา)
                if (!empty($event_settings['corral_settings']) && empty($manual_corral)) {
                    $corrals = json_decode($event_settings['corral_settings'], true);
                    if (is_array($corrals)) {
                        foreach ($corrals as $corral) {
                            // ตรวจสอบว่าเลข BIB อยู่ในช่วงไหน
                            if ($next_bib_num >= $corral['from_bib'] && $next_bib_num <= $corral['to_bib']) {
                                $corral_to_update = $corral['name'];
                                break;
                            }
                        }
                    }
                }
                
                // อัปเดตเลข BIB ถัดไปในตาราง Events (Increment)
                $stmt_inc = $mysqli->prepare("UPDATE events SET bib_next_number = bib_next_number + 1 WHERE id = ?");
                $stmt_inc->bind_param("i", $event_id);
                if (!$stmt_inc->execute()) {
                    throw new Exception("ไม่สามารถรันเลข BIB ถัดไปได้");
                }
                $stmt_inc->close();
            }
        }

        // --- 9. Prepare Final Update Query ---
        // Construct Query Dynamically
        // SQL Base: UPDATE ... SET status=?, bib=?, corral=?, shipping=? [ , slip=? ] WHERE id=?
        
        $sql_params[] = $bib_to_update;    $sql_types .= "s";
        $sql_params[] = $corral_to_update; $sql_types .= "s";
        $sql_params[] = $new_shipping_option; $sql_types .= "s";

        // ถ้ามีการอัปโหลดไฟล์ ให้เพิ่ม path เข้าไปใน params
        if (!empty($slip_sql_part)) {
            $sql_params[] = $new_slip_path;
            $sql_types .= "s";
        }

        // สุดท้ายเพิ่ม ID ของ Registration สำหรับ WHERE clause
        $sql_params[] = $reg_id;
        $sql_types .= "i";

        $final_sql = "UPDATE registrations SET status = ?, bib_number = ?, corral = ?, shipping_option = ? $slip_sql_part WHERE id = ?";
        
        $stmt_upd = $mysqli->prepare($final_sql);
        if (!$stmt_upd) {
            throw new Exception("SQL Prepare Error: " . $mysqli->error);
        }
        
        // Bind Params แบบ Dynamic (ใช้ splat operator ... เพื่อกระจาย array เป็น arguments)
        $stmt_upd->bind_param($sql_types, ...$sql_params);

        if (!$stmt_upd->execute()) {
            throw new Exception("บันทึกข้อมูลไม่สำเร็จ: " . $stmt_upd->error);
        }
        $stmt_upd->close();
        
        // --- 10. Commit Transaction ---
        $mysqli->commit();
        
        $_SESSION['update_success'] = "บันทึกข้อมูลเรียบร้อยแล้ว (ID: $reg_id)";

    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("Update Registration Error: " . $e->getMessage());
        $_SESSION['update_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }

    // --- 11. Redirect ---
    header('Location: ' . $redirect_url);
    exit;

} else {
    // Not a POST request
    header('Location: ../admin/index.php');
    exit;
}
?>