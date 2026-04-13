<?php
session_start();
require_once 'includes/db_connection.php';

// 1. Kiểm tra quyền Admin (Sử dụng ID người dùng từ session)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 9) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id_update = intval($_POST['order_id']);
    $new_status = $_POST['new_status'];

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id_update);
    $stmt->execute();
    $stmt->close();
    header("Location: admin_orders.php");
    exit();
}

$sql_orders = "SELECT o.*, u.username, s.customer_name, s.customer_phone, s.customer_email, s.customer_address 
               FROM orders o 
               JOIN users u ON o.user_id = u.id 
               LEFT JOIN order_shipping_details s ON o.id = s.order_id 
               ORDER BY o.order_date DESC";

$res_orders = mysqli_query($conn, $sql_orders);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản trị đơn hàng toàn diện - HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin_orders.css">
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h1 style="margin:0;">Quản trị đơn hàng toàn diện</h1>
        <a href="index.php" style="text-decoration:none; color:#007bff; font-weight:bold;">← Về trang chủ</a>
    </div>

    <?php while($order = mysqli_fetch_assoc($res_orders)): $order_id = $order['id']; ?>
        <div class="order-card">
            <div class="header-flex">
                <div class="cust-info">
                    <h3>Người nhận: <?= htmlspecialchars($order['customer_name'] ?? 'Khách lẻ') ?></h3>
                    <div class="cust-contact">
                        <strong>SĐT:</strong> <?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?> | 
                        <strong>Email:</strong> <?= htmlspecialchars($order['customer_email'] ?? 'N/A') ?><br>
                        <strong>Địa chỉ:</strong> <?= htmlspecialchars($order['customer_address'] ?? 'N/A') ?><br>
                        <small style="color:#999;">Mã đơn: #<?= $order_id ?> | Ngày đặt: <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></small>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span class="badge status-<?= $order['status'] ?>"><?= $order['status'] ?></span>
                    
                    <?php if($order['status'] == 'Pending'): ?>
                        <div class="btn-group">
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="new_status" value="Confirmed">
                                <button type="submit" name="update_status" class="btn btn-ok">Duyệt đơn</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="new_status" value="Cancelled">
                                <button type="submit" name="update_status" class="btn btn-no">Hủy đơn</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th width="50%">Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sql_items = "SELECT oi.*, p.name FROM order_items oi 
                                  JOIN products p ON oi.product_id = p.id 
                                  WHERE oi.order_id = $order_id";
                    $res_items = mysqli_query($conn, $sql_items);
                    while($item = mysqli_fetch_assoc($res_items)):
                        $price = $item['price'] ?? $item['unit_price'] ?? 0;
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                        <td>x<?= $item['quantity'] ?></td>
                        <td><?= number_format($price, 0, ',', '.') ?>đ</td>
                        <td><?= number_format($price * $item['quantity'], 0, ',', '.') ?>đ</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <div class="summary">
                <?php 
                    $total = $order['total_price'] ?? $order['total_amount'] ?? 0;
                ?>
                TỔNG CỘNG: <?= number_format($total, 0, ',', '.') ?> VNĐ
            </div>
        </div>
    <?php endwhile; ?>
</div>

</body>
</html>