<?php
session_start();
require_once 'includes/db_connection.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}


if (!isset($_GET['id'])) {
    header("Location: order_history.php");
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];


$sql_order = "SELECT o.*, s.customer_name, s.customer_email, s.customer_phone, s.customer_address 
              FROM orders o
              LEFT JOIN order_shipping_details s ON o.id = s.order_id
              WHERE o.id = ? AND o.user_id = ?";

$stmt = $conn->prepare($sql_order);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Đơn hàng không tồn tại hoặc bạn không có quyền xem.");
}


$sql_items = "SELECT oi.*, p.name 
              FROM order_items oi 
              JOIN products p ON oi.product_id = p.id 
              WHERE oi.order_id = ?";
$stmt_items = $conn->prepare($sql_items);
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$res_items = $stmt_items->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết đơn hàng #<?= $order_id ?></title>
    <link rel="stylesheet" href="css/order_detail.css">
</head>
<body>
    <div class="container">
        <a href="order_history.php" style="text-decoration: none;">← Quay lại lịch sử</a>
        
        <div class="section">
            <h2>Chi tiết đơn hàng #<?= $order_id ?></h2>
            <p>Ngày đặt: <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></p>
            <span class="status-badge <?= $order['status'] ?>">Trạng thái: <?= $order['status'] ?></span>
        </div>

        <div class="section grid">
            <div>
                <strong>Thông tin người nhận</strong><br>
                Họ tên: <?= htmlspecialchars($order['customer_name']) ?><br>
                SĐT: <?= htmlspecialchars($order['customer_phone']) ?><br>
                Email: <?= htmlspecialchars($order['customer_email']) ?>
            </div>
            <div>
                <strong>Địa chỉ giao hàng</strong><br>
                <?= nl2br(htmlspecialchars($order['customer_address'])) ?>
            </div>
        </div>

        <div class="section">
            <strong>Danh sách sản phẩm</strong>
            <table>
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Giá</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $res_items->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>x<?= $item['quantity'] ?></td>
                        <td><?= number_format($item['price'], 0, ',', '.') ?>đ</td>
                        <td><?= number_format($item['price'] * $item['quantity'], 0, ',', '.') ?>đ</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="total">
            TỔNG TIỀN: <?= number_format($order['total_amount'], 0, ',', '.') ?> VNĐ
        </div>
    </div>
    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>