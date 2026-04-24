<?php
$host = 'localhost';
$db   = 'humg_mobile';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Khởi tạo kết nối MySQLi
$conn = new mysqli($host, $user, $pass, $db);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("LỖI KẾT NỐI DATABASE: " . $conn->connect_error);
}

// Thiết lập tiếng Việt
$conn->set_charset($charset);
?>