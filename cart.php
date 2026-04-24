<?php
session_start();
require_once 'includes/db_connection.php'; 


if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $variant_id = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 
                  (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

    if ($variant_id <= 0) {
        $message = "Biến thể sản phẩm không hợp lệ.";
    } elseif ($action === 'add' || $action === 'update') {
        $quantity = max(1, $quantity);
        $sql = "SELECT v.id AS variant_id, v.color_name, v.price, v.stock, 
                       p.id AS product_id, p.name AS product_name, p.image_url 
                FROM product_variants v 
                JOIN products p ON v.product_id = p.id 
                WHERE v.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $variant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $variant = $result->fetch_assoc();

        if ($variant) {
            $current_qty_in_cart = isset($_SESSION['cart'][$variant_id]['quantity']) ? $_SESSION['cart'][$variant_id]['quantity'] : 0;
            $product_full_name = htmlspecialchars($variant['product_name'] . ' (' . $variant['color_name'] . ')');

            if ($action === 'add') {
                $new_total_qty = $current_qty_in_cart + $quantity;
            } else { // 'update'
                $new_total_qty = $quantity;
            }

            if ($new_total_qty > $variant['stock']) {
                $message = "Xin lỗi, số lượng tồn kho của **{$product_full_name}** chỉ còn {$variant['stock']} chiếc.";
            } else {
                if ($new_total_qty > 0) {
                    $_SESSION['cart'][$variant_id] = [
                        'product_id' => $variant['product_id'],
                        'name' => $variant['product_name'],
                        'color' => $variant['color_name'],
                        'price' => $variant['price'],
                        'quantity' => $new_total_qty,
                        'image_url' => $variant['image_url'],
                        'stock' => $variant['stock']
                    ];
                    $message = ($action === 'add') ? "Đã thêm **{$product_full_name}** vào giỏ hàng thành công." : "Đã cập nhật số lượng cho **{$product_full_name}**.";
                } else {
                    unset($_SESSION['cart'][$variant_id]);
                    $message = "Đã xóa sản phẩm khỏi giỏ hàng.";
                }
            }
        } else {
            $message = "Biến thể sản phẩm không tồn tại.";
        }
    } elseif ($action === 'remove') {
        if (isset($_SESSION['cart'][$variant_id])) {
            $product_name = $_SESSION['cart'][$variant_id]['name'] . ' (' . $_SESSION['cart'][$variant_id]['color'] . ')';
            unset($_SESSION['cart'][$variant_id]);
            $message = "Đã xóa **{$product_name}** khỏi giỏ hàng.";
        }
    }

    header('Location: cart.php?message=' . urlencode($message));
    exit;
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
}

$cart_items = $_SESSION['cart'];
$grand_total = 0;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng của bạn | Mobile Shop</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/cart.css">
</head>
<body>
    <header>
        <div class="header-content">
            <h1>HUMG Mobile</h1>
            <nav>
                <a href="index.php">Trang chủ</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="order_history.php">Lịch sử Đơn hàng</a>
                    <a href="logout.php">Đăng xuất</a>
                    <span style="color: #fff; margin: 0 10px;">Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <?php else: ?>
                    <a href="login.php">Đăng nhập</a>
                    <a href="register.php">Đăng ký</a>
                <?php endif; ?>
                <a href="cart.php">Giỏ hàng</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Giỏ hàng của bạn</h2>

        <?php if ($message): ?>
            <div class="alert-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
            <p>Giỏ hàng của bạn hiện đang trống. <a href="index.php">Xem sản phẩm ngay!</a></p>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Giá (VNĐ)</th>
                        <th>Số lượng</th>
                        <th>Thành tiền (VNĐ)</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // KIỂM TRA TỒN KHO THỰC TẾ BẰNG MYSQLI
                    $variant_ids = array_keys($cart_items);
                    $variant_stocks = [];
                    if (!empty($variant_ids)) {
                        $placeholders = implode(',', array_fill(0, count($variant_ids), '?'));
                        $types = str_repeat('i', count($variant_ids));
                        $stmt_stock = $conn->prepare("SELECT id, stock FROM product_variants WHERE id IN ($placeholders)");
                        $stmt_stock->bind_param($types, ...$variant_ids);
                        $stmt_stock->execute();
                        $res_stock = $stmt_stock->get_result();
                        while($row_s = $res_stock->fetch_assoc()) {
                            $variant_stocks[$row_s['id']] = $row_s['stock'];
                        }
                    }
                    ?>
                    <?php foreach ($cart_items as $variant_id => $item): 
                        $sub_total = $item['price'] * $item['quantity'];
                        $grand_total += $sub_total;
                        $max_stock = $variant_stocks[$variant_id] ?? 0;
                    ?>
                    <tr>
                        <td>
                            <div class="cart-item-name">
                                <img src="images/<?php echo htmlspecialchars($item['image_url'] ?? 'default.jpg'); ?>" alt="">
                                <div>
                                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                                    <span class="variant-color">Màu: <?php echo htmlspecialchars($item['color']); ?></span>
                                </div>
                            </div>
                        </td>
                        <td><?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                        <td>
                            <form action="cart.php" method="POST" class="form-update" style="display: flex; align-items: center;">
                                <input type="hidden" name="id" value="<?php echo $variant_id; ?>"> 
                                <input type="hidden" name="action" value="update">
                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $max_stock; ?>" required>
                                <button type="submit">Cập nhật</button>
                            </form>
                            <small style="display: block; color: #888;">Còn: <?php echo $max_stock; ?></small>
                        </td>
                        <td><?php echo number_format($sub_total, 0, ',', '.'); ?></td>
                        <td>
                             <form action="cart.php" method="POST" style="display: inline;">
                                 <input type="hidden" name="id" value="<?php echo $variant_id; ?>">
                                 <button type="submit" name="action" value="remove" style="background: none; border: none; color: red; cursor: pointer;">Xóa</button>
                             </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Tổng cộng:</td>
                        <td colspan="2"><?php echo number_format($grand_total, 0, ',', '.'); ?> VNĐ</td>
                    </tr>
                </tfoot>
            </table>

            <div style="text-align: right; margin-top: 20px;">
                <a href="checkout.php" class="btn-checkout">Tiến hành Thanh toán</a>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Website Bán Điện Thoại Uy tín- Chất lượng| HUMG Mobile</p>
        </div>
    </footer>
    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>