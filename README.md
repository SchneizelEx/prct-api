# PRCT-MIS Attendance PHP API & Admin Dashboard

ระบบ Backend PHP REST API และ Admin Management Dashboard สำหรับแอปพลิเคชันลงเวลาทำงานระบบพิกัดตำแหน่ง (Geo-fencing 150m)

## 📌 คุณสมบัติ (Features)

1. **REST API สำหรับ Android App (`api.php`)**:
   - ตรวจสอบสิทธิ์การอนุมัติอุปกรณ์ (`check_device_status`)
   - บันทึกการลงเวลาเข้า-ออกงานพร้อมพิกัด GPS (`save_record`)
   - ดึงประวัติการลงเวลาข้ามเครื่อง (`get_history`)

2. **ระบบ Admin Management Dashboard (`admin.php`)**:
   - ระบบเข้าสู่ระบบความปลอดภัย (Admin Login)
   - ระบบลงเวลาแทนพนักงานเมื่ออุปกรณ์มีปัญหา (Manual Attendance Entry)
   - รายงานสถิติสรุปเวลาทำงานเป็นรายบุคคล / ทั้งหมด (รายวัน, รายสัปดาห์, รายเดือน)
   - ระบบอนุมัติและจัดการ Device ID พนักงาน (Device Management)

## 🛠️ โครงสร้างฐานข้อมูล MySQL (`schema.sql`)

```sql
CREATE DATABASE IF NOT EXISTS `work_attendance` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `work_attendance`;

-- 1. ตาราง Admin Login
CREATE TABLE IF NOT EXISTS `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_users` (`username`, `password_hash`, `full_name`) 
VALUES ('admin', '$2y$10$wN1iN/j0mOQ7.1kY8O4mveX9T4fN/0eS3hR7b/1m8m.m1m8m.m1m', 'Administrator')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 2. ตาราง Device ID พนักงาน
CREATE TABLE IF NOT EXISTS `users_devices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(50) NOT NULL,
    `user_name` VARCHAR(100) NOT NULL,
    `device_id` VARCHAR(100) NOT NULL UNIQUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ตารางประวัติการลงเวลา
CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(50) NOT NULL,
    `user_name` VARCHAR(100) DEFAULT '',
    `device_id` VARCHAR(100) DEFAULT '',
    `type` ENUM('CHECK_IN', 'CHECK_OUT') NOT NULL,
    `latitude` DOUBLE NOT NULL,
    `longitude` DOUBLE NOT NULL,
    `distance_meters` FLOAT NOT NULL,
    `is_manual` TINYINT(1) DEFAULT 0,
    `note` VARCHAR(255) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 🔑 บัญชีเข้าสู่ระบบ Admin ค่าเริ่มต้น
- **URL**: `http://your-domain/admin.php`
- **Username**: `admin`
- **Password**: `admin123`
