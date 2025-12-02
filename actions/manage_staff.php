<?php
// actions/manage_staff.php
// สคริปต์จัดการข้อมูลเจ้าหน้าที่ (รองรับหลายกิจกรรม: Many-to-Many)

require_once '../config.php';
require_once '../functions.php';

// Check Admin Permission
if (!isset($_SESSION['staff_id']) || $_SESSION['staff_info']['role'] !== 'admin') {
    header('Location: ../admin/index.php');
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($action === 'create') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        
        // รับค่าเป็น Array ของ event_ids
        $assigned_event_ids = isset($_POST['event_ids']) ? $_POST['event_ids'] : []; 

        // Basic Validation
        if (empty($username) || empty($password) || empty($full_name)) {
            $_SESSION['update_error'] = "กรุณากรอกข้อมูลให้ครบถ้วน";
            header('Location: ../admin/staff_management.php');
            exit;
        }

        // Check duplicate username
        $stmt = $mysqli->prepare("SELECT id FROM staff WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['update_error'] = "Username นี้มีผู้ใช้งานแล้ว";
            header('Location: ../admin/staff_management.php');
            exit;
        }
        $stmt->close();

        // Insert Staff
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $mysqli->begin_transaction();
        
        try {
            // 1. Insert into staff table
            $stmt = $mysqli->prepare("INSERT INTO staff (username, password_hash, full_name, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
            $stmt->bind_param("ssss", $username, $password_hash, $full_name, $role);
            $stmt->execute();
            $new_staff_id = $stmt->insert_id;
            $stmt->close();

            // 2. Insert into staff_assignments (Loop assigned events)
            if ($role === 'staff' && !empty($assigned_event_ids)) {
                $stmt_assign = $mysqli->prepare("INSERT INTO staff_assignments (staff_id, event_id) VALUES (?, ?)");
                foreach ($assigned_event_ids as $eid) {
                    $eid = intval($eid);
                    if ($eid > 0) {
                        $stmt_assign->bind_param("ii", $new_staff_id, $eid);
                        $stmt_assign->execute();
                    }
                }
                $stmt_assign->close();
            }

            $mysqli->commit();
            $_SESSION['update_success'] = "เพิ่มเจ้าหน้าที่เรียบร้อยแล้ว";

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['update_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }

    } elseif ($action === 'update') {
        $staff_id = intval($_POST['staff_id']);
        $full_name = trim($_POST['full_name']);
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        // รับค่าเป็น Array
        $assigned_event_ids = isset($_POST['event_ids']) ? $_POST['event_ids'] : [];

        $mysqli->begin_transaction();

        try {
            // 1. Update Staff Info
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $mysqli->prepare("UPDATE staff SET full_name = ?, role = ?, password_hash = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $role, $password_hash, $staff_id);
            } else {
                $stmt = $mysqli->prepare("UPDATE staff SET full_name = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssi", $full_name, $role, $staff_id);
            }
            $stmt->execute();
            $stmt->close();

            // 2. Update Assignments (Delete old -> Insert new)
            // Clear old assignments
            $mysqli->query("DELETE FROM staff_assignments WHERE staff_id = $staff_id");

            // Insert new ones if role is staff
            if ($role === 'staff' && !empty($assigned_event_ids)) {
                $stmt_assign = $mysqli->prepare("INSERT INTO staff_assignments (staff_id, event_id) VALUES (?, ?)");
                foreach ($assigned_event_ids as $eid) {
                    $eid = intval($eid);
                    if ($eid > 0) {
                        $stmt_assign->bind_param("ii", $staff_id, $eid);
                        $stmt_assign->execute();
                    }
                }
                $stmt_assign->close();
            }

            $mysqli->commit();
            $_SESSION['update_success'] = "แก้ไขข้อมูลเรียบร้อยแล้ว";

        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['update_error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }

    } elseif ($action === 'delete') {
        $staff_id = intval($_POST['staff_id']);
        
        if ($staff_id == $_SESSION['staff_id']) {
            $_SESSION['update_error'] = "ไม่สามารถลบบัญชีตัวเองได้";
        } else {
            // Delete from assignments first (if FK constraint exists, otherwise safe to delete)
            $mysqli->query("DELETE FROM staff_assignments WHERE staff_id = $staff_id");
            // Delete staff
            $mysqli->query("DELETE FROM staff WHERE id = $staff_id");
            $_SESSION['update_success'] = "ลบบัญชีเรียบร้อยแล้ว";
        }
    }

    header('Location: ../admin/staff_management.php');
    exit;
}
?>