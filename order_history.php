<?php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];


$sql = "SELECT o.id, o.order_date, o.total_amount, o.status, 
               GROUP_CONCAT(p.name SEPARATOR ', ') AS product_names 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ? 
        GROUP BY o.id 
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Lịch sử đơn hàng - HUMG Mobile</title>
    <link rel="stylesheet" href="css/order_history.css">
</head>
<body>
    <div class="container">
        <a href="index.php" style="text-decoration: none; color: #007bff; font-weight: bold;">← Quay lại cửa hàng</a>
        <h2>Lịch sử mua hàng</h2>
        
        <table>
            <thead>
                <tr>
                    <th class="text-center">Mã đơn</th>
                    <th>Sản phẩm</th>
                    <th class="text-center">Ngày đặt</th>
                    <th class="text-center">Tổng tiền</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center"><strong>#<?= $row['id'] ?></strong></td>
                        <td style="max-width: 300px; color: #666; font-style: italic;">
                            <?= htmlspecialchars($row['product_names']) ?>
                        </td>
                        <td class="text-center"><?= date('d/m/Y H:i', strtotime($row['order_date'])) ?></td>
                        <td class="text-center" style="color: #d70018; font-weight: bold;">
                            <?= number_format($row['total_amount'], 0, ',', '.') ?>đ
                        </td>
                        <td class="text-center">
                            <span class="status status-<?= $row['status'] ?>"><?= $row['status'] ?></span>
                        </td>
                        <td class="text-center">
                            <a href="order_detail.php?id=<?= $row['id'] ?>" class="btn-detail">Xem chi tiết</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 40px; color: #999;">Bạn chưa có đơn hàng nào.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>