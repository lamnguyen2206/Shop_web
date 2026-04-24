<?php
session_start();
require_once 'includes/db_connection.php'; 


$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$cat_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;


$sql_all_cats = "SELECT * FROM categories ORDER BY parent_id ASC, name ASC";
$res_all_cats = mysqli_query($conn, $sql_all_cats);
$categories = [];
while ($row = mysqli_fetch_assoc($res_all_cats)) {
    $categories[] = $row;
}

function renderCategoryMenu($data, $parent_id = 0, $level = 0) {
    foreach ($data as $item) {
        $db_parent = ($item['parent_id'] == NULL) ? 0 : $item['parent_id'];
        if ($db_parent == $parent_id) {
            $padding = $level * 20; 
            $class = ($level == 0) ? 'parent-cat' : 'child-cat';
            echo "<li style='list-style:none; padding-left: {$padding}px; margin: 8px 0;'>";
            echo "<a href='index.php?category={$item['id']}' class='{$class}'>";
            echo ($level == 0 ? "<strong>" . mb_strtoupper($item['name']) . "</strong>" : "— " . $item['name']);
            echo "</a></li>";
            renderCategoryMenu($data, $item['id'], $level + 1);
        }
    }
}


ob_start();
$_GET['search'] = $search; 
$_GET['category'] = $cat_id;
include 'search_products.php'; 
$initial_products = ob_get_clean();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>HUMG Mobile | Hệ thống bán lẻ</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>

<header>
    <div class="container flex">
        <h1>HUMG Mobile</h1>
        <nav>
            <a href="index.php">Trang chủ</a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="admin_orders.php" class="admin-link">QUẢN TRỊ ĐƠN</a>
                <?php endif; ?>
                <a href="order_history.php">Lịch sử đơn</a>
                <a href="logout.php">Đăng xuất (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
            <?php else: ?>
                <a href="login.php">Đăng nhập</a>
            <?php endif; ?>
            <a href="cart.php">Giỏ hàng</a>
        </nav>
    </div>
</header>

<div class="container">
    <div class="main-layout">
        <aside class="sidebar">
            <h3>DANH MỤC</h3>
            <ul style="padding: 0; margin: 0;">
                <li style="list-style: none; margin-bottom: 10px;">
                    <a href="index.php" style="text-decoration:none; color:#333; font-weight:bold;">🏠 Tất cả sản phẩm</a>
                </li>
                <?php renderCategoryMenu($categories, 0); ?>
            </ul>
        </aside>

        <main class="content">
            <div class="search-box">
                <input type="text" id="ajax-search" placeholder="Nhập tên máy cần tìm..." value="<?= htmlspecialchars($search) ?>" autocomplete="off">
                <button type="button">Tìm kiếm</button>
            </div>
            
            <div id="search-loading">Đang cập nhật sản phẩm...</div>

            <div class="product-grid" id="product-container">
                <?= $initial_products ?>
            </div>
        </main>
    </div>
</div>

<footer>
    <div class="container"><p>&copy; 2025 HUMG Mobile</p></div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    let timer = null;

    const urlParams = new URLSearchParams(window.location.search);
    const catId = urlParams.get('category') || 0;

    $('#ajax-search').on('keyup', function() {
        clearTimeout(timer);
        let keyword = $(this).val();


        timer = setTimeout(function() {
            $('#search-loading').show();
            $('#product-container').css('opacity', '0.5');

            $.ajax({
                url: 'search_products.php', 
                type: 'GET',
                data: { 
                    search: keyword,
                    category: catId 
                },
                success: function(data) {

                    $('#product-container').html(data).css('opacity', '1');
                    $('#search-loading').hide();
                },
                error: function() {
                    $('#product-container').css('opacity', '1');
                    $('#search-loading').hide();
                }
            });
        }, 400);
    });
});
</script>

<!-- CHAT WIDGET -->
<?php include 'includes/chat_widget.php'; ?>

</body>
</html>