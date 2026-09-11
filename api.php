<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// อ่านข้อมูล JSON Request Body
$input_data = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($input_data['action'])) {
    $action = $input_data['action'];
}

switch ($action) {
    case 'check_device_status':
        handleCheckDeviceStatus($pdo);
        break;

    case 'save_record':
        handleSaveRecord($pdo, $input_data);
        break;

    case 'get_history':
        handleGetHistory($pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action"], JSON_UNESCAPED_UNICODE);
        break;
}

// 1. ตรวจสอบสถานะการอนุมัติอุปกรณ์จาก Admin
function handleCheckDeviceStatus($pdo) {
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';

    if (empty($device_id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ไม่ได้ระบุ device_id"], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT user_id, user_name FROM users_devices WHERE device_id = :device_id LIMIT 1");
        $stmt->execute([':device_id' => $device_id]);
        $row = $stmt->fetch();

        if ($row) {
            echo json_encode([
                "status"  => "success",
                "message" => "อุปกรณ์ได้รับอนุมัติเรียบร้อย",
                "data"    => [
                    "is_registered" => true,
                    "user_id"       => $row['user_id'],
                    "user_name"     => $row['user_name']
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "status"  => "error",
                "message" => "เครื่องนี้ยังไม่ได้ลงทะเบียน กรุณาติดต่อ Admin",
                "data"    => [ "is_registered" => false ]
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

// 2. บันทึกเวลาเข้า-ออกงาน (ตรวจสอบสิทธิ์อุปกรณ์ก่อนบันทึก)
function handleSaveRecord($pdo, $data) {
    if (!$data) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "No JSON data provided"], JSON_UNESCAPED_UNICODE);
        return;
    }

    $device_id       = isset($data['device_id']) ? trim($data['device_id']) : '';
    $type            = isset($data['type']) ? $data['type'] : '';
    $latitude        = isset($data['latitude']) ? floatval($data['latitude']) : 0.0;
    $longitude       = isset($data['longitude']) ? floatval($data['longitude']) : 0.0;
    $distance_meters = isset($data['distance_meters']) ? floatval($data['distance_meters']) : 0.0;

    if (empty($device_id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ไม่ได้ระบุ device_id"], JSON_UNESCAPED_UNICODE);
        return;
    }

    try {
        $stmt_check = $pdo->prepare("SELECT user_id, user_name FROM users_devices WHERE device_id = :device_id LIMIT 1");
        $stmt_check->execute([':device_id' => $device_id]);
        $device_info = $stmt_check->fetch();

        if (!$device_info) {
            http_response_code(403);
            echo json_encode([
                "status"  => "error",
                "message" => "ปฏิเสธการบันทึก: เครื่องยังไม่ได้ลงทะเบียนกับ Admin"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $user_id   = $device_info['user_id'];
        $user_name = $device_info['user_name'];

        // ตรวจสอบคอลัมน์ในตาราง
        $stmt_col = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'device_id'");
        $has_device_col = $stmt_col->fetch();

        if ($has_device_col) {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_logs (user_id, user_name, device_id, type, latitude, longitude, distance_meters)
                VALUES (:user_id, :user_name, :device_id, :type, :latitude, :longitude, :distance_meters)
            ");
            $stmt->execute([
                ':user_id'         => $user_id,
                ':user_name'       => $user_name,
                ':device_id'       => $device_id,
                ':type'            => $type,
                ':latitude'        => $latitude,
                ':longitude'       => $longitude,
                ':distance_meters' => $distance_meters
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO attendance_logs (user_id, type, latitude, longitude, distance_meters)
                VALUES (:user_id, :type, :latitude, :longitude, :distance_meters)
            ");
            $stmt->execute([
                ':user_id'         => $user_id,
                ':type'            => $type,
                ':latitude'        => $latitude,
                ':longitude'       => $longitude,
                ':distance_meters' => $distance_meters
            ]);
        }

        $last_id = $pdo->lastInsertId();

        $stmt_fetch = $pdo->prepare("SELECT * FROM attendance_logs WHERE id = :id");
        $stmt_fetch->execute([':id' => $last_id]);
        $inserted_row = $stmt_fetch->fetch();

        echo json_encode([
            "status"  => "success",
            "message" => "บันทึกเวลาเรียบร้อยแล้ว",
            "data"    => $inserted_row
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}

// 3. ดึงประวัติการลงเวลา
function handleGetHistory($pdo) {
    $device_id = isset($_GET['device_id']) ? trim($_GET['device_id']) : '';
    $user_id   = isset($_GET['user_id']) ? trim($_GET['user_id']) : '';

    try {
        $stmt_col = $pdo->query("SHOW COLUMNS FROM attendance_logs LIKE 'device_id'");
        $has_device_col = $stmt_col->fetch();

        if ($has_device_col && !empty($device_id)) {
            $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE device_id = :device_id ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([':device_id' => $device_id]);
        } elseif (!empty($user_id)) {
            $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50");
            $stmt->execute([':user_id' => $user_id]);
        } else {
            $stmt = $pdo->query("SELECT * FROM attendance_logs ORDER BY created_at DESC LIMIT 50");
        }

        $rows = $stmt->fetchAll();

        echo json_encode([
            "status" => "success",
            "data"   => $rows
        ], JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
?>
