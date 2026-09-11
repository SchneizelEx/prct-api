<?php
$host     = "localhost";
$db_name  = "work_attendance";
$username = "root";       // ปรับเปลี่ยนตามการตั้งค่า MySQL ของเซิร์ฟเวอร์
$password = "";           // ปรับเปลี่ยนตามการตั้งค่า MySQL ของเซิร์ฟเวอร์
$charset  = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    http_response_code(500);
    die("Database Connection Error: " . $e->getMessage());
}
?>
