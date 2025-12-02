<?php
// actions/export_registrants.php
// สคริปต์สำหรับ Export ข้อมูลผู้สมัครเป็นไฟล์ CSV (รองรับภาษาไทย + สิทธิ์หลายกิจกรรม)

require_once '../config.php';
require_once '../functions.php';

// 1. Check Login
if (!isset($_SESSION['staff_id'])) {
    header('Location: ../admin/login.php');
    exit;
}
$staff_info = $_SESSION['staff_info'];
$is_super_admin = ($staff_info['role'] === 'admin');

// เตรียมรายการ Event IDs ที่ Staff ดูแล
$allowed_events = [];
if (!$is_super_admin) {
    if (isset($staff_info['assigned_event_ids']) && is_array($staff_info['assigned_event_ids'])) {
        $allowed_events = array_map('intval', $staff_info['assigned_event_ids']);
    } elseif (isset($staff_info['assigned_event_id'])) {
        $allowed_events = [intval($staff_info['assigned_event_id'])];
    }
}

// 2. Validate Input
if (!isset($_GET['event_id']) || !is_numeric($_GET['event_id'])) {
    die("Error: Invalid Event ID.");
}
$event_id = intval($_GET['event_id']);

// 3. Permission Check
if (!$is_super_admin && !in_array($event_id, $allowed_events)) {
    die("Error: Permission Denied. คุณไม่มีสิทธิ์ดาวน์โหลดข้อมูลของกิจกรรมนี้");
}

// 4. Fetch Event Name (สำหรับตั้งชื่อไฟล์)
$evt_stmt = $mysqli->prepare("SELECT event_code FROM events WHERE id = ?");
$evt_stmt->bind_param("i", $event_id);
$evt_stmt->execute();
$evt_res = $evt_stmt->get_result();
if ($evt_res->num_rows === 0) die("Event not found.");
$event_code = $evt_res->fetch_assoc()['event_code'];
$evt_stmt->close();

// 5. Prepare Query
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "
    SELECT 
        r.registration_code,
        r.bib_number,
        r.status,
        r.title,
        r.first_name,
        r.last_name,
        r.gender,
        r.birth_date,
        r.thai_id,
        r.email,
        r.phone,
        r.line_id,
        r.emergency_contact_name,
        r.emergency_contact_phone,
        r.disease,
        r.disease_detail,
        d.name AS distance,
        rc.name AS category,
        r.shirt_size,
        r.shipping_option,
        r.shipping_address,
        r.total_amount,
        r.registered_at
    FROM registrations r
    LEFT JOIN distances d ON r.distance_id = d.id
    LEFT JOIN race_categories rc ON r.race_category_id = rc.id
    WHERE r.event_id = ?
";

$params = [$event_id];
$types = "i";

if (!empty($search_term)) {
    $sql .= " AND (r.first_name LIKE ? OR r.last_name LIKE ? OR r.email LIKE ? OR r.phone LIKE ? OR r.registration_code LIKE ? OR r.bib_number LIKE ?)";
    $like = "%{$search_term}%";
    for ($i=0; $i<6; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

$sql .= " ORDER BY r.registered_at ASC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// 6. Generate CSV
// ตั้งชื่อไฟล์
$filename = "registrants_" . $event_code . "_" . date('Y-m-d_His') . ".csv";

// ส่ง Header ให้ Browser
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// เปิด Output Stream
$output = fopen('php://output', 'w');

// [CRITICAL] เขียน BOM (Byte Order Mark) เพื่อให้ Excel อ่านภาษาไทยออก
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// เขียนหัวตาราง (ภาษาไทย)
$headers = [
    'รหัสสมัคร', 'BIB', 'สถานะ', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'เพศ', 'วันเกิด (พ.ศ.)', 
    'เลขบัตร/Passport', 'อีเมล', 'เบอร์โทร', 'Line ID', 'ผู้ติดต่อฉุกเฉิน', 'เบอร์ฉุกเฉิน',
    'โรคประจำตัว', 'รายละเอียดโรค', 'ระยะทาง', 'รุ่นอายุ', 'ไซส์เสื้อ', 
    'การรับของ', 'ที่อยู่จัดส่ง', 'ยอดชำระ', 'วันที่สมัคร'
];
fputcsv($output, $headers);

// เขียนข้อมูล
while ($row = $result->fetch_assoc()) {
    // แปลงข้อมูลให้อ่านง่าย
    
    // วันเกิด (เป็น พ.ศ.)
    $birth_date_th = '-';
    if (!empty($row['birth_date'])) {
        $bd = new DateTime($row['birth_date']);
        $birth_date_th = $bd->format('d/m/') . ($bd->format('Y') + 543);
    }

    // วันที่สมัคร (เป็น พ.ศ. + เวลา)
    $reg_at_th = '-';
    if (!empty($row['registered_at'])) {
        $rd = new DateTime($row['registered_at']);
        $reg_at_th = $rd->format('d/m/') . ($rd->format('Y') + 543) . ' ' . $rd->format('H:i');
    }

    // สถานะการรับของ
    $ship_opt = ($row['shipping_option'] == 'delivery') ? 'จัดส่งไปรษณีย์' : 'รับเองหน้างาน';
    
    // เคลียร์ Line break ในที่อยู่ เพื่อไม่ให้ CSV พัง
    $address = str_replace(["\r", "\n"], " ", $row['shipping_address']);

    // ข้อมูลที่จะลง CSV
    $csv_row = [
        $row['registration_code'],
        $row['bib_number'] ? $row['bib_number'] : '-',
        $row['status'],
        $row['title'],
        $row['first_name'],
        $row['last_name'],
        $row['gender'],
        $birth_date_th,
        "'" . $row['thai_id'], // ใส่ ' นำหน้าเพื่อกัน Excel แปลงเป็น scientific notation
        $row['email'],
        "'" . $row['phone'],
        $row['line_id'],
        $row['emergency_contact_name'],
        "'" . $row['emergency_contact_phone'],
        $row['disease'],
        $row['disease_detail'],
        $row['distance'],
        $row['category'] ? $row['category'] : 'General',
        $row['shirt_size'],
        $ship_opt,
        $address,
        $row['total_amount'],
        $reg_at_th
    ];

    fputcsv($output, $csv_row);
}

fclose($output);
$stmt->close();
exit;
?>