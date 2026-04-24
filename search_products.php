<?php
require_once 'includes/db_connection.php';


$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$cat_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;


$where_clause = "WHERE 1=1";
if ($search != '') {
    $where_clause .= " AND p.name LIKE '%$search%'";
}
if ($cat_id > 0) {
    $sub_sql = "SELECT id FROM categories WHERE parent_id = $cat_id";
    $sub_res = mysqli_query($conn, $sub_sql);
    $ids = [$cat_id];
    while($sub_row = mysqli_fetch_assoc($sub_res)) { $ids[] = $sub_row['id']; }
    $id_list = implode(',', $ids);
    $where_clause .= " AND p.category_id IN ($id_list)";
}


$sql = "SELECT p.*, MIN(pv.price) as min_price 
        FROM products p 
        LEFT JOIN product_variants pv ON p.id = pv.product_id 
        $where_clause 
        GROUP BY p.id 
        ORDER BY p.id DESC";

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        ?>
        <div class="product-card">
            <img src="images/<?= htmlspecialchars($row['image_url']) ?>" alt="">
            <h4><?= htmlspecialchars($row['name']) ?></h4>
            <p class="price">
                <?= $row['min_price'] ? number_format($row['min_price'], 0, ',', '.') . 'đ' : 'Liên hệ' ?>
            </p>
            <a href="product_detail.php?id=<?= $row['id'] ?>" class="btn-detail">Xem chi tiết</a>
        </div>
        <?php
    }
} else {
    echo "<p style='grid-column: 1/-1; text-align: center; padding: 40px;'>Không tìm thấy sản phẩm nào.</p>";
}
?>