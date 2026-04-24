<?php

session_start();
require_once 'includes/db_connection.php';

$product = null;
$variants = [];
$error_message = '';


if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    

    $sql_product = "SELECT id, name, image_url, description FROM products WHERE id = ?";
    if ($stmt_product = $conn->prepare($sql_product)) {
        $stmt_product->bind_param("i", $product_id);
        $stmt_product->execute();
        $result_product = $stmt_product->get_result();
        $product = $result_product->fetch_assoc();
        $stmt_product->close();

        if (!$product) {
            $error_message = "Không tìm thấy sản phẩm này.";
        } else {

            $sql_variants = "SELECT id, color_name, hex_code, price, stock 
                             FROM product_variants 
                             WHERE product_id = ? 
                             ORDER BY price ASC";
            
            if ($stmt_variants = $conn->prepare($sql_variants)) {
                $stmt_variants->bind_param("i", $product_id);
                $stmt_variants->execute();
                $result_variants = $stmt_variants->get_result();
                
                while ($row = $result_variants->fetch_assoc()) {
                    $variants[] = $row;
                }
                $stmt_variants->close();

                if (empty($variants)) {
                    $error_message = "Sản phẩm này hiện chưa có biến thể màu nào để bán.";
                }
            }
        }
    } else {
        $error_message = "Lỗi hệ thống khi chuẩn bị truy vấn.";
    }
} else {
    $error_message = "Tham số sản phẩm không hợp lệ.";
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Chi tiết Sản phẩm'; ?> | HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/product_detail.css">
</head>
<body>
    <header style="background: #007bff; color: white; padding: 20px 0;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <h1>HUMG Mobile</h1>
            <nav>
                <a href="index.php" style="color: white; margin: 0 10px;">Trang chủ</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span style="margin: 0 10px;">Chào, <?= htmlspecialchars($_SESSION['username']); ?>!</span>
                    <a href="order_history.php" style="color: white; margin: 0 10px;">Đơn hàng</a>
                    <a href="logout.php" style="color: white; margin: 0 10px;">Đăng xuất</a>
                <?php else: ?>
                    <a href="login.php" style="color: white; margin: 0 10px;">Đăng nhập</a>
                <?php endif; ?>
                <a href="cart.php" style="color: white; margin: 0 10px;">Giỏ hàng</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($product && !empty($variants)): ?>
            <h2 style="margin-top: 20px;"><?php echo htmlspecialchars($product['name']); ?></h2>
            <div class="product-detail">
                <div class="product-image">
                    <img src="images/<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                
                <div class="product-info">
                    <p class="price-large" id="display-price"><?php echo number_format($variants[0]['price'], 0, ',', '.') . ' VNĐ'; ?></p>
                    
                    <h3>Chọn Màu sắc:</h3>
                    <div class="color-selector">
                        <?php foreach ($variants as $index => $variant): ?>
                            <div 
                                class="color-option <?php echo $index === 0 ? 'selected' : ''; ?>" 
                                data-variant-id="<?php echo $variant['id']; ?>"
                                data-price="<?php echo $variant['price']; ?>"
                                data-stock="<?php echo $variant['stock']; ?>"
                                onclick="selectVariant(this)">
                                <span class="color-dot" style="background-color: <?php echo htmlspecialchars($variant['hex_code'] ?? 'gray'); ?>"></span>
                                <?php echo htmlspecialchars($variant['color_name']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <h3>Thông tin chung</h3>
                    <p><strong>Tình trạng:</strong> <span id="display-stock-status"><?php echo $variants[0]['stock'] > 0 ? 'Còn hàng (' . $variants[0]['stock'] . ')' : 'Hết hàng'; ?></span></p>
                    <p><strong>Mã sản phẩm:</strong> SP-<?php echo $product['id']; ?></p>
                    
                    <h3 style="margin-top: 30px;">Mô tả chi tiết</h3>
                    <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <form action="cart.php" method="POST" style="margin-top: 30px;">
                        <input type="hidden" name="variant_id" id="selected-variant-id" value="<?php echo $variants[0]['id']; ?>">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="quantity-control">
                            <label for="quantity">Số lượng:</label>
                            <input type="number" id="quantity" name="quantity" value="1" min="1" max="<?php echo $variants[0]['stock']; ?>" required>
                        </div>
                        
                        <div id="add-to-cart-area">
                            <?php if ($variants[0]['stock'] > 0): ?>
                                <button type="submit" class="btn-add-to-cart">THÊM VÀO GIỎ HÀNG</button>
                            <?php else: ?>
                                <button type="button" class="btn-add-to-cart" disabled style="background-color: #6c757d;">Hết hàng</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert-error"><?php echo $error_message; ?></div>
            <p><a href="index.php">Quay lại Trang chủ</a></p>
        <?php endif; ?>
    </main>

    <footer style="background: #212529; color: white; padding: 40px 0; margin-top: 50px; text-align: center;">
        <p>&copy; 2025 HUMG Mobile - 18 Phố Viên, Bắc Từ Liêm, Hà Nội</p>
    </footer>

    <script>
    function selectVariant(element) {
        document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');

        const variantId = element.getAttribute('data-variant-id');
        const price = parseFloat(element.getAttribute('data-price'));
        const stock = parseInt(element.getAttribute('data-stock'));

        document.getElementById('display-price').textContent = new Intl.NumberFormat('vi-VN').format(price) + ' VNĐ';
        document.getElementById('display-stock-status').innerHTML = stock > 0 ? `Còn hàng (${stock})` : 'Hết hàng';
        document.getElementById('selected-variant-id').value = variantId;
        
        const qtyInput = document.getElementById('quantity');
        qtyInput.setAttribute('max', stock);
        if (parseInt(qtyInput.value) > stock) qtyInput.value = stock > 0 ? 1 : 0;

        const buyArea = document.getElementById('add-to-cart-area');
        if (stock > 0) {
            buyArea.innerHTML = `<button type="submit" class="btn-add-to-cart">🛒 THÊM VÀO GIỎ HÀNG</button>`;
        } else {
            buyArea.innerHTML = `<button type="button" class="btn-add-to-cart" disabled style="background-color: #6c757d;">Hết hàng</button>`;
        }
    }
    </script>
    <?php include 'includes/chat_widget.php'; ?>
</body>
</html>