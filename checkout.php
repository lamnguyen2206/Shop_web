<?php
session_start();
require_once 'includes/db_connection.php'; // Sử dụng $conn (MySQLi)


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'checkout.php';
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_items = $_SESSION['cart'];
$grand_total = 0;
$errors = [];


$logged_in_user = null;


$stmt_u = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt_u->bind_param("i", $user_id);
$stmt_u->execute();
$logged_in_user = $stmt_u->get_result()->fetch_assoc();

foreach ($cart_items as $variant_id => $item) {
    if (!isset($item['name']) || !isset($item['price']) || !isset($item['quantity'])) {
        $errors['system'] = "Lỗi dữ liệu giỏ hàng: Sản phẩm ID {$variant_id} không hợp lệ.";
        break;
    }
    $grand_total += $item['price'] * $item['quantity'];
}


$data = [
    'name' => $_POST['name'] ?? ($logged_in_user['username'] ?? ''),
    'email' => $_POST['email'] ?? ($logged_in_user['email'] ?? ''),
    'phone' => $_POST['phone'] ?? '',
    'address' => $_POST['address'] ?? '',
];


if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $order_date = date('Y-m-d H:i:s');
    $status = 'Processing';


    if (empty($name)) $errors['name'] = 'Vui lòng nhập họ tên.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email không hợp lệ.';
    if (empty($phone)) $errors['phone'] = 'Vui lòng nhập số điện thoại.';
    if (empty($address)) $errors['address'] = 'Vui lòng nhập địa chỉ.';
    if ($grand_total <= 0) $errors['system'] = 'Tổng tiền không hợp lệ.';

    if (empty($errors)) {

        $conn->begin_transaction();

        try {

            $sql_order = "INSERT INTO orders (user_id, order_date, total_amount, status) VALUES (?, ?, ?, ?)";
            $stmt_o = $conn->prepare($sql_order);
            $stmt_o->bind_param("isds", $user_id, $order_date, $grand_total, $status);
            $stmt_o->execute();
            $order_id = $conn->insert_id;

            $sql_shipping = "INSERT INTO order_shipping_details (order_id, customer_name, customer_email, customer_phone, customer_address) VALUES (?, ?, ?, ?, ?)";
            $stmt_s = $conn->prepare($sql_shipping);
            $stmt_s->bind_param("issss", $order_id, $name, $email, $phone, $address);
            $stmt_s->execute();

            $sql_item = "INSERT INTO order_items (order_id, product_id, variant_id, quantity, price) VALUES (?, ?, ?, ?, ?)";
            $sql_stock = "UPDATE product_variants SET stock = stock - ? WHERE id = ? AND stock >= ?";
            
            $stmt_item = $conn->prepare($sql_item);
            $stmt_stock = $conn->prepare($sql_stock);

            foreach ($cart_items as $v_id => $item) {
                if (!isset($item['product_id'])) {
                    throw new Exception("Sản phẩm {$item['name']} thiếu ID gốc.");
                }

                $stmt_item->bind_param("iiiid", $order_id, $item['product_id'], $v_id, $item['quantity'], $item['price']);
                $stmt_item->execute();

                $stmt_stock->bind_param("iii", $item['quantity'], $v_id, $item['quantity']);
                $stmt_stock->execute();

                if ($stmt_stock->affected_rows === 0) {
                    throw new Exception("Sản phẩm " . $item['name'] . " đã hết hàng hoặc không đủ số lượng.");
                }
            }
            $conn->commit();
            unset($_SESSION['cart']);
            header('Location: order_success.php?id=' . $order_id);
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors['system'] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán | HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/checkout.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>HUMG Mobile</h1>
            <nav>
                <a href="index.php">Trang chủ</a>
                <a href="cart.php">Giỏ hàng</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <h2>Thanh toán</h2>
        <div class="checkout-layout">
            <div class="customer-form">
                <?php if (isset($errors['system'])): ?>
                    <div style="color:white; background:red; padding:10px; margin-bottom:10px;"><?= $errors['system'] ?></div>
                <?php endif; ?>

                <form action="checkout.php" method="POST">
                    <div class="form-group">
                        <label>Họ tên nhận hàng:</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($data['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Số điện thoại:</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($data['phone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Địa chỉ giao hàng:</label>
                        <textarea name="address" rows="3" required><?= htmlspecialchars($data['address']) ?></textarea>
                    </div>
                    <button type="submit" class="btn-place-order">XÁC NHẬN ĐẶT HÀNG</button>
                </form>
            </div>

            <div class="order-summary">
                <h3>Đơn hàng của bạn</h3>
                <?php foreach ($cart_items as $item): ?>
                    <div style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">
                        <?= htmlspecialchars($item['name']) ?> (<?= htmlspecialchars($item['color']) ?>)<br>
                        <?= $item['quantity'] ?> x <?= number_format($item['price'], 0, ',', '.') ?>đ
                    </div>
                <?php endforeach; ?>
                <div class="summary-total">
                    Tổng: <?= number_format($grand_total, 0, ',', '.') ?> VNĐ
                </div>
            </div>
        </div>
    </main>
    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>