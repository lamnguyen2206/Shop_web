-- ============================================
-- CHAT SCHEMA cho HUMG Mobile
-- Chạy file này trong phpMyAdmin
-- ============================================

-- Bảng phòng chat (mỗi khách hàng có 1 phòng với admin)
CREATE TABLE IF NOT EXISTS `chat_rooms` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_message_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng tin nhắn
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`     INT UNSIGNED NOT NULL,
    `sender_id`   INT UNSIGNED NOT NULL,
    `message`     TEXT NOT NULL,
    `sent_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    KEY `idx_room_sent` (`room_id`, `sent_at`),
    CONSTRAINT `fk_msg_room` FOREIGN KEY (`room_id`) REFERENCES `chat_rooms`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
