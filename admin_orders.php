<?php
session_start();
require_once 'includes/db_connection.php';

// 1. Kiểm tra quyền Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// 2. Xử lý logic cập nhật trạng thái đơn hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['order_id'], $_POST['new_status'])) {
    $order_id_update = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];

    // Sử dụng Prepared Statement để chống SQL Injection
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $new_status, $order_id_update);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: admin_orders.php");
    exit();
}

// 3. Truy vấn danh sách đơn hàng (Tối ưu select cột cần thiết và xử lý NULL)
// Sử dụng COALESCE để luôn có dữ liệu hợp lệ hiển thị
$sql_orders = "
    SELECT 
        o.id, 
        o.status, 
        o.order_date, 
        o.total_amount, 
        u.username, 
        COALESCE(s.customer_name, u.username, 'Khách lẻ') AS display_name, 
        COALESCE(s.customer_phone, 'N/A') AS phone, 
        COALESCE(s.customer_email, 'N/A') AS email, 
        COALESCE(s.customer_address, 'Chưa cập nhật') AS address
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    LEFT JOIN order_shipping_details s ON o.id = s.order_id 
    ORDER BY o.order_date DESC
";

$res_orders = mysqli_query($conn, $sql_orders);
$orders = [];
$order_ids = [];

if ($res_orders) {
    while ($row = mysqli_fetch_assoc($res_orders)) {
        // Gán thêm mảng items rỗng mặc định để tiện gom nhóm phía sau
        $row['items'] = [];
        $orders[$row['id']] = $row;
        $order_ids[] = $row['id'];
    }
}

// 4. Giải quyết bài toán N+1 Query (Thay vì lặp query trong HTML, ta lấy tất cả item 1 lần)
if (!empty($order_ids)) {
    // Tạo chuỗi ?, ?, ? tương ứng với số lượng order
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    // Lấy tên sản phẩm và các trường cần thiết, dùng COALESCE xử lý giá
    $sql_items = "
        SELECT 
            oi.order_id, 
            p.name, 
            oi.quantity, 
            COALESCE(oi.price, oi.unit_price, 0) AS price
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id IN ($placeholders)
    ";

    $stmt_items = $conn->prepare($sql_items);
    if ($stmt_items) {
        // Build the types string 'i', 'i', 'i'...
        $types = str_repeat('i', count($order_ids));
        $stmt_items->bind_param($types, ...$order_ids);
        $stmt_items->execute();
        $res_items = $stmt_items->get_result();

        // Gán từng item vào đúng order của nó
        while ($item = $res_items->fetch_assoc()) {
            $orders[$item['order_id']]['items'][] = $item;
        }
        $stmt_items->close();
    }
}

// Utility function để lấy label trạng thái tiếng Việt
function getStatusBadge($status) {
    return match ($status) {
        'Pending'   => ['class' => 'status-Pending',   'text' => 'Chờ duyệt'],
        'Confirmed' => ['class' => 'status-Confirmed', 'text' => 'Đã duyệt'],
        'Cancelled' => ['class' => 'status-Cancelled', 'text' => 'Đã hủy'],
        default     => ['class' => 'status-Default',   'text' => 'Không rõ']
    };
}
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
        <div>
            <a href="admin_chat.php" style="text-decoration:none; color:#dc3545; font-weight:bold; margin-right: 15px;">💬 Quản lý Chat</a>
            <a href="index.php" style="text-decoration:none; color:#007bff; font-weight:bold;">← Về trang chủ</a>
        </div>
    </div>

    <!-- Tách biệt View: Chỉ lặp HTML để render dữ liệu đã được xử lý ở trên -->
    <?php if (empty($orders)): ?>
        <p style="text-align:center; color:#666;">Hiện chưa có đơn hàng nào.</p>
    <?php else: ?>
        <?php foreach ($orders as $order_id => $order): 
            $badge = getStatusBadge($order['status']); 
            $order_date_vn = date('d/m/Y H:i', strtotime($order['order_date']));
        ?>
            <div class="order-card">
                <div class="header-flex">
                    <div class="cust-info">
                        <h3>Người nhận: <?= htmlspecialchars($order['display_name']) ?></h3>
                        <div class="cust-contact">
                            <strong>SĐT:</strong> <?= htmlspecialchars($order['phone']) ?> | 
                            <strong>Email:</strong> <?= htmlspecialchars($order['email']) ?><br>
                            <strong>Địa chỉ:</strong> <?= htmlspecialchars($order['address']) ?><br>
                            <small style="color:#999;">Mã đơn: #<?= htmlspecialchars($order_id) ?> | Ngày đặt: <?= $order_date_vn ?></small>
                        </div>
                    </div>
                    
                    <div style="text-align: right;">
                        <span class="badge <?= $badge['class'] ?>"><?= htmlspecialchars($badge['text']) ?></span>
                        
                        <?php if ($order['status'] === 'Pending'): ?>
                            <div class="btn-group">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
                                    <input type="hidden" name="new_status" value="Confirmed">
                                    <button type="submit" name="update_status" class="btn btn-ok">Duyệt đơn</button>
                                </form>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
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
                        <?php if (empty($order['items'])): ?>
                            <tr><td colspan="4" style="text-align:center;color:#999;">Không có chi tiết sản phẩm</td></tr>
                        <?php else: ?>
                            <?php foreach ($order['items'] as $item): 
                                $price = (float)$item['price'];
                                $quantity = (int)$item['quantity'];
                                $subtotal = $price * $quantity;
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                                    <td>x<?= $quantity ?></td>
                                    <td><?= number_format($price, 0, ',', '.') ?>đ</td>
                                    <td><?= number_format($subtotal, 0, ',', '.') ?>đ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="summary">
                    TỔNG CỘNG: <span style="color:#e74c3c; font-weight:bold; font-size:1.2rem;">
                        <?= number_format((float)$order['total_amount'], 0, ',', '.') ?> VNĐ
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>