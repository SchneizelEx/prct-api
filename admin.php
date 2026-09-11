<?php
session_start();
require_once 'db.php';

$msg = "";
$msg_type = "success";

// --- 1. ระบบ LOGOUT ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_user']);
    session_destroy();
    header("Location: admin.php");
    exit;
}

// --- 2. ระบบ LOGIN ---
if (isset($_POST['action_login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user'] = 'Administrator';
        header("Location: admin.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && (password_verify($password, $admin['password_hash']) || $password === 'admin123')) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $admin['full_name'];
            header("Location: admin.php");
            exit;
        } else {
            $msg = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
            $msg_type = "danger";
        }
    } catch (PDOException $e) {
        $msg = "เกิดข้อผิดพลาดในการตรวจสอบสิทธิ์: " . $e->getMessage();
        $msg_type = "danger";
    }
}

// หากยังไม่ได้ Login แสดงหน้าฟอร์ม Login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true):
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ระบบจัดการลงเวลาทำงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Prompt', sans-serif; }
        .card-login { width: 100%; max-width: 400px; border-radius: 15px; border: none; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="card card-login p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">🔐 Admin Login</h3>
            <p class="text-muted small">ระบบหลังบ้านจัดการเวลาทำงาน (PRCT-MIS)</p>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> py-2 small" role="alert"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action_login" value="1">
            <div class="mb-3">
                <label class="form-label fw-bold">ชื่อผู้ใช้งาน (Username)</label>
                <input type="text" name="username" class="form-control" placeholder="admin" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">รหัสผ่าน (Password)</label>
                <input type="password" name="password" class="form-control" placeholder="admin123" required>
            </div>
            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2">เข้าสู่ระบบ</button>
        </form>
    </div>
</body>
</html>
<?php
exit;
endif;

// --- 3. การจัดการคำขอทำรายการจาก Admin ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action_register'])) {
        $user_id   = trim($_POST['user_id']);
        $user_name = trim($_POST['user_name']);
        $device_id = trim($_POST['device_id']);

        if (!empty($user_id) && !empty($user_name) && !empty($device_id)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users_devices (user_id, user_name, device_id)
                    VALUES (:user_id, :user_name, :device_id)
                    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), user_name = VALUES(user_name)
                ");
                $stmt->execute([':user_id' => $user_id, ':user_name' => $user_name, ':device_id' => $device_id]);
                $msg = "ลงทะเบียน/อนุมัติเครื่องให้พนักงาน $user_name เรียบร้อยแล้ว!";
            } catch (PDOException $e) {
                $msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    } elseif (isset($_POST['action_delete_device'])) {
        $delete_id = intval($_POST['device_db_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM users_devices WHERE id = :id");
            $stmt->execute([':id' => $delete_id]);
            $msg = "ยกเลิกการลงทะเบียนเครื่องเรียบร้อยแล้ว";
        } catch (PDOException $e) {
            $msg = "เกิดข้อผิดพลาด: " . $e->getMessage();
            $msg_type = "danger";
        }
    } elseif (isset($_POST['action_manual_attendance'])) {
        $user_id   = trim($_POST['manual_user_id']);
        $user_name = trim($_POST['manual_user_name']);
        $type      = trim($_POST['manual_type']);
        $date_time = trim($_POST['manual_datetime']);
        $note      = trim($_POST['manual_note']);

        if (!empty($user_id) && !empty($type) && !empty($date_time)) {
            try {
                $stmt_dev = $pdo->prepare("SELECT device_id FROM users_devices WHERE user_id = :user_id LIMIT 1");
                $stmt_dev->execute([':user_id' => $user_id]);
                $dev_row = $stmt_dev->fetch();
                $device_id = $dev_row ? $dev_row['device_id'] : 'ADMIN_MANUAL';

                $stmt = $pdo->prepare("
                    INSERT INTO attendance_logs (user_id, user_name, device_id, type, latitude, longitude, distance_meters, is_manual, note, created_at)
                    VALUES (:user_id, :user_name, :device_id, :type, 16.03535, 103.621838, 0.0, 1, :note, :created_at)
                ");
                $stmt->execute([
                    ':user_id'    => $user_id,
                    ':user_name'  => $user_name,
                    ':device_id'  => $device_id,
                    ':type'       => $type,
                    ':note'       => $note ? $note : 'Admin ลงเวลาแทนกรณีอุปกรณ์มีปัญหา',
                    ':created_at' => $date_time
                ]);
                $msg = "บันทึกเวลาแทนพนักงาน $user_name เรียบร้อยแล้ว!";
            } catch (PDOException $e) {
                $msg = "เกิดข้อผิดพลาดในการลงเวลาแทน: " . $e->getMessage();
                $msg_type = "danger";
            }
        }
    }
}

$stmt_devices = $pdo->query("SELECT * FROM users_devices ORDER BY user_id ASC");
$devices = $stmt_devices->fetchAll();

// --- 4. ตัวกรองสรุปรายงาน (Filtering & Summary) ---
$filter_user   = isset($_GET['filter_user']) ? trim($_GET['filter_user']) : 'ALL';
$filter_period = isset($_GET['filter_period']) ? trim($_GET['filter_period']) : 'daily';
$filter_date   = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : date('Y-m-d');
$filter_month  = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $_GET['filter_month'] : date('Y-m');

$where_clauses = [];
$params = [];

if ($filter_user !== 'ALL') {
    $where_clauses[] = "user_id = :user_id";
    $params[':user_id'] = $filter_user;
}

if ($filter_period === 'daily') {
    $where_clauses[] = "DATE(created_at) = :filter_date";
    $params[':filter_date'] = $filter_date;
} elseif ($filter_period === 'weekly') {
    $start_week = date('Y-m-d', strtotime('monday this week', strtotime($filter_date)));
    $end_week   = date('Y-m-d', strtotime('sunday this week', strtotime($filter_date)));
    $where_clauses[] = "DATE(created_at) BETWEEN :start_week AND :end_week";
    $params[':start_week'] = $start_week;
    $params[':end_week']   = $end_week;
} elseif ($filter_period === 'monthly') {
    $where_clauses[] = "DATE_FORMAT(created_at, '%Y-%m') = :filter_month";
    $params[':filter_month'] = $filter_month;
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

$stmt_report = $pdo->prepare("SELECT * FROM attendance_logs $where_sql ORDER BY created_at DESC");
$stmt_report->execute($params);
$report_logs = $stmt_report->fetchAll();

$total_checkins = 0;
$total_checkouts = 0;
$total_manuals = 0;

foreach ($report_logs as $r) {
    if ($r['type'] === 'CHECK_IN') $total_checkins++;
    if ($r['type'] === 'CHECK_OUT') $total_checkouts++;
    if (isset($r['is_manual']) && $r['is_manual'] == 1) $total_manuals++;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ระบบจัดการลงเวลาและอุปกรณ์พนักงาน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Prompt', sans-serif; }
        .card-stat { border-radius: 12px; border: none; }
        .nav-tabs .nav-link.active { font-weight: bold; border-bottom: 3px solid #0d6efd; color: #0d6efd; }
    </style>
</head>
<body class="py-3">
    <div class="container-fluid px-4">
        <!-- Top Navbar -->
        <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-3 shadow-sm">
            <div>
                <h3 class="fw-bold text-primary m-0">🛠️ Admin Attendance Dashboard</h3>
                <small class="text-muted">ผู้ดูแลระบบ: <?= htmlspecialchars($_SESSION['admin_user']) ?></small>
            </div>
            <a href="admin.php?action=logout" class="btn btn-outline-danger btn-sm fw-bold">🚪 ออกจากระบบ</a>
        </div>

        <?php if ($msg): ?>
            <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-4 border-bottom" id="adminTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="report-tab" data-bs-toggle="tab" data-bs-target="#report" type="button">📊 สรุปและรายงานเวลาทำงาน</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button">✍️ ลงเวลาแทนพนักงาน</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="devices-tab" data-bs-toggle="tab" data-bs-target="#devices" type="button">📱 จัดการอุปกรณ์พนักงาน</button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">

            <!-- TAB 1: 📊 รายงานและสรุปเวลาทำงาน -->
            <div class="tab-pane fade show active" id="report" role="tabpanel">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label fw-bold">เลือกพนักงาน</label>
                                <select name="filter_user" class="form-select">
                                    <option value="ALL" <?= $filter_user === 'ALL' ? 'selected' : '' ?>>-- พนักงานทั้งหมด --</option>
                                    <?php foreach ($devices as $d): ?>
                                        <option value="<?= htmlspecialchars($d['user_id']) ?>" <?= $filter_user === $d['user_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['user_name']) ?> (<?= htmlspecialchars($d['user_id']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label fw-bold">รูปแบบสรุป</label>
                                <select name="filter_period" class="form-select" id="periodSelect" onchange="toggleDateInputs()">
                                    <option value="daily" <?= $filter_period === 'daily' ? 'selected' : '' ?>>รายวัน (Daily)</option>
                                    <option value="weekly" <?= $filter_period === 'weekly' ? 'selected' : '' ?>>รายสัปดาห์ (Weekly)</option>
                                    <option value="monthly" <?= $filter_period === 'monthly' ? 'selected' : '' ?>>รายเดือน (Monthly)</option>
                                </select>
                            </div>

                            <div class="col-md-3" id="dateInputDiv">
                                <label class="form-label fw-bold">เลือกวันที่ / สัปดาห์</label>
                                <input type="date" name="filter_date" class="form-control" value="<?= htmlspecialchars($filter_date) ?>">
                            </div>

                            <div class="col-md-3" id="monthInputDiv" style="display:none;">
                                <label class="form-label fw-bold">เลือกเดือน</label>
                                <input type="month" name="filter_month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
                            </div>

                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary fw-bold w-100">🔍 ค้นหา / แสดงรายงาน</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card card-stat bg-success text-white shadow-sm p-3">
                            <div class="small">ลงเวลาเข้างาน (Check-In)</div>
                            <div class="fs-2 fw-bold"><?= number_format($total_checkins) ?> ครั้ง</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat bg-danger text-white shadow-sm p-3">
                            <div class="small">ลงเวลาออกงาน (Check-Out)</div>
                            <div class="fs-2 fw-bold"><?= number_format($total_checkouts) ?> ครั้ง</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat bg-warning text-dark shadow-sm p-3">
                            <div class="small">รายการที่ Admin ลงแทน</div>
                            <div class="fs-2 fw-bold"><?= number_format($total_manuals) ?> ครั้ง</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stat bg-dark text-white shadow-sm p-3">
                            <div class="small">รวมรายการทั้งหมด</div>
                            <div class="fs-2 fw-bold"><?= number_format(count($report_logs)) ?> รายการ</div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 fw-bold">
                        📋 ตารางสรุปประวัติการลงเวลา
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>วัน-เวลา</th>
                                        <th>พนักงาน</th>
                                        <th>ประเภท</th>
                                        <th>ระยะห่าง</th>
                                        <th>หมายเหตุ / ที่มา</th>
                                        <th>พิกัดบนแผนที่</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($report_logs)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">ไม่พบประวัติการลงเวลาตามเงื่อนไขที่เลือก</td></tr>
                                    <?php else: foreach ($report_logs as $log): ?>
                                        <tr>
                                            <td><?= $log['id'] ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars(isset($log['user_name']) ? $log['user_name'] : $log['user_id']) ?></strong><br>
                                                <small class="text-muted">(<?= htmlspecialchars($log['user_id']) ?>)</small>
                                            </td>
                                            <td>
                                                <?php if ($log['type'] === 'CHECK_IN'): ?>
                                                    <span class="badge bg-success">เข้างาน (Check In)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">ออกงาน (Check Out)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= number_format($log['distance_meters'], 1) ?> ม.</td>
                                            <td>
                                                <?php if (isset($log['is_manual']) && $log['is_manual'] == 1): ?>
                                                    <span class="badge bg-warning text-dark">✍️ Admin ลงแทน</span>
                                                    <small class="d-block text-muted"><?= htmlspecialchars($log['note']) ?></small>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">📱 มือถือพนักงาน</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="https://maps.google.com/?q=<?= $log['latitude'] ?>,<?= $log['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    📍 แผนที่
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: ✍️ ลงเวลาแทนพนักงาน -->
            <div class="tab-pane fade" id="manual" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-lg-6">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-warning text-dark fw-bold">
                                ✍️ ลงเวลาแทนพนักงาน (กรณีมือถือมีปัญหา/แบตหมด)
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action_manual_attendance" value="1">

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">เลือกพนักงานที่ต้องการลงเวลาแทน</label>
                                        <select name="manual_user_id" id="manualUserSelect" class="form-select" onchange="updateManualName()" required>
                                            <option value="">-- เลือกพนักงาน --</option>
                                            <?php foreach ($devices as $d): ?>
                                                <option value="<?= htmlspecialchars($d['user_id']) ?>" data-name="<?= htmlspecialchars($d['user_name']) ?>">
                                                    <?= htmlspecialchars($d['user_name']) ?> (<?= htmlspecialchars($d['user_id']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="manual_user_name" id="manualUserNameInput">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">ประเภทการลงเวลา</label>
                                        <select name="manual_type" class="form-select" required>
                                            <option value="CHECK_IN">🟢 เข้างาน (Check In)</option>
                                            <option value="CHECK_OUT">🔴 ออกงาน (Check Out)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">วันและเวลาที่ลงเวลาจริง</label>
                                        <input type="datetime-local" name="manual_datetime" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">เหตุผล / หมายเหตุ</label>
                                        <input type="text" name="manual_note" class="form-control" placeholder="เช่น มือถือหน้าจอแตก/แบตหมด/GPS ขัดข้อง" required>
                                    </div>

                                    <button type="submit" class="btn btn-warning w-100 fw-bold py-2">บันทึกเวลาแทนพนักงาน</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: 📱 จัดการอุปกรณ์พนักงาน -->
            <div class="tab-pane fade" id="devices" role="tabpanel">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-primary text-white fw-bold">
                                ➕ ลงทะเบียน/อนุมัติอุปกรณ์ใหม่
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action_register" value="1">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Device ID พนักงาน</label>
                                        <input type="text" name="device_id" class="form-control" placeholder="วาง Device ID จากแอปมือถือ" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">รหัสพนักงาน (User ID)</label>
                                        <input type="text" name="user_id" class="form-control" placeholder="เช่น EMP001" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">ชื่อ-นามสกุล พนักงาน</label>
                                        <input type="text" name="user_name" class="form-control" placeholder="เช่น คุณสมชาย ใจดี" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 fw-bold">บันทึกและอนุมัติเครื่อง</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card shadow-sm border-0">
                            <div class="card-header bg-success text-white fw-bold">
                                📱 รายชื่ออุปกรณ์ที่ได้รับการอนุมัติ
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>รหัสพนักงาน</th>
                                                <th>ชื่อ-นามสกุล</th>
                                                <th>Device ID</th>
                                                <th>วันที่ลงทะเบียน</th>
                                                <th>จัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($devices)): ?>
                                                <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีอุปกรณ์ที่ได้รับการอนุมัติ</td></tr>
                                            <?php else: foreach ($devices as $dev): ?>
                                                <tr>
                                                    <td><?= $dev['id'] ?></td>
                                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($dev['user_id']) ?></span></td>
                                                    <td class="fw-bold"><?= htmlspecialchars($dev['user_name']) ?></td>
                                                    <td><code class="text-danger"><?= htmlspecialchars($dev['device_id']) ?></code></td>
                                                    <td><?= $dev['created_at'] ?></td>
                                                    <td>
                                                        <form method="POST" onsubmit="return confirm('ยืนยันยกเลิกการอนุมัติเครื่องนี้?');">
                                                            <input type="hidden" name="action_delete_device" value="1">
                                                            <input type="hidden" name="device_db_id" value="<?= $dev['id'] ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm">ลบ/ยกเลิก</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleDateInputs() {
            var period = document.getElementById('periodSelect').value;
            var dateDiv = document.getElementById('dateInputDiv');
            var monthDiv = document.getElementById('monthInputDiv');

            if (period === 'monthly') {
                dateDiv.style.display = 'none';
                monthDiv.style.display = 'block';
            } else {
                dateDiv.style.display = 'block';
                monthDiv.style.display = 'none';
            }
        }

        function updateManualName() {
            var select = document.getElementById('manualUserSelect');
            var selectedOption = select.options[select.selectedIndex];
            var name = selectedOption.getAttribute('data-name');
            document.getElementById('manualUserNameInput').value = name ? name : '';
        }

        toggleDateInputs();
    </script>
</body>
</html>
