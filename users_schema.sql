-- ==========================================
-- SCHEMA BẢNG USERS (Hệ Thống Phân Quyền)
-- Code tích hợp đầy đủ các cột dùng trong PHP
-- ==========================================

-- 1. Tạo bảng (nếu chưa có)
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Khóa chính',
  `username` varchar(100) NOT NULL UNIQUE COMMENT 'Tên đăng nhập',
  `email` varchar(255) NOT NULL UNIQUE COMMENT 'Email',
  `password` varchar(255) NOT NULL COMMENT 'Mật khẩu',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Thời gian đăng ký',
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Cập nhật cấu trúc (dành cho bảng đã tồn tại sẵn nhưng thiếu các cột)
-- Chạy riêng đoạn này nếu bị lỗi bảng cũ
/*
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user' AFTER `password`,
ADD COLUMN IF NOT EXISTS `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `role`,
ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
*/

-- 3. Tạo/Cập nhật một tài khoản Admin mặc định (Pass: 123456) 
-- (Chỉ chạy khi web chưa có Admin)
INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password`, `role`) 
VALUES (
    9, 
    'admin', 
    'admin@humgmobile.vn', 
    '$2y$10$O0a0v0c3yR.0l0k3x0z0M.fA1l9B1r3P8t8m9A1q7f7T2B5x9Y1N.', -- Đây là mã hash của mật khẩu: 123456
    'admin'
);

-- Cập nhật bắt buộc user id 9 thành admin (Nếu id 9 đã tồn tại trước đó dưới dạng 'user')
UPDATE `users` SET `role` = 'admin' WHERE `id` = 9;

