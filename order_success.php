<?php
session_start();
// Không cần kết nối CSDL trừ khi bạn muốn hiển thị thêm chi tiết sản phẩm ngay tại đây.
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng Thành công | HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/order_success.css">
</head>
<body>

<header>
    <h1>HUMG Mobile</h1>
    <nav>
        <a href="index.php">Trang chủ</a>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="order_history.php">Lịch sử Đơn hàng</a>
            <a href="logout.php">Đăng xuất</a>
            <span style="margin-left: 10px; opacity: 0.9;">| Chào, <?= htmlspecialchars($_SESSION['username']); ?>!</span>
        <?php else: ?>
            <a href="login.php">Đăng nhập</a>
            <a href="register.php">Đăng ký</a>
        <?php endif; ?>
        <a href="cart.php">Giỏ hàng</a>
    </nav>
</header>

<main class="container">
    <div class="success-box">
        <div class="success-icon">✔</div>
        <h2>Đặt hàng Thành công!</h2>
        <p>Cảm ơn bạn đã tin tưởng và mua hàng tại <strong>HUMG Mobile</strong>.</p>
        
        <?php if (isset($_GET['id'])): ?>
            <div class="order-id">
                Mã đơn hàng: #<?= htmlspecialchars($_GET['id']); ?>
            </div>
        <?php endif; ?>

        <p>Nhân viên chúng tôi sẽ gọi điện xác nhận đơn hàng sớm nhất có thể.</p>
        <p>Thông tin chi tiết đã được gửi vào lịch sử mua hàng của bạn.</p>
        
        <a href="index.php" class="btn-home">Tiếp tục mua sắm</a>
    </div>
</main>

<footer>
    <div class="container">
        <p>&copy; 2025 HUMG Mobile - Uy tín & Chất lượng</p>
        <div class="contact-info">
            <p>Địa chỉ: 18 Phố Viên, Quận Bắc Từ Liêm, Hà Nội</p>
            <p>Hotline: 0987.654.321 | Email: support@humgmobile.vn</p>
        </div>
    </div>
</footer>

</body>
</html>