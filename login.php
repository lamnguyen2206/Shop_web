<?php
session_start();
require_once 'includes/db_connection.php'; 

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username_or_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email)) {
        $errors['field'] = 'Vui lòng nhập Tên đăng nhập hoặc Email.';
    }
    if (empty($password)) {
        $errors['password'] = 'Vui lòng nhập Mật khẩu.';
    }

    if (empty($errors)) {

        $field = filter_var($username_or_email, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';


        $sql = "SELECT id, username, password, role FROM users WHERE {$field} = ? LIMIT 1";
        
        if ($stmt = $conn->prepare($sql)) {

            $stmt->bind_param("s", $username_or_email);
            

            $stmt->execute();
            

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                header('Location: index.php');
                exit;
            } else {
                $errors['login'] = 'Tên đăng nhập/Email hoặc Mật khẩu không đúng.';
            }
            $stmt->close();
        } else {
            $errors['system'] = 'Lỗi hệ thống CSDL: Vui lòng thử lại sau.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập Tài khoản | HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <header>
        <h1>HUMG Mobile</h1>
        <nav>
            <a href="index.php">Trang chủ</a>
            <a href="register.php">Đăng ký</a>
            <a href="cart.php">Giỏ hàng</a>
        </nav>
    </header>

    <main class="container">
        <div class="login-box">
            <h2>Đăng nhập</h2>

            <?php if (isset($errors['system'])): ?>
                <div class="error-message" style="border: 1px solid red; padding: 10px;"><?php echo htmlspecialchars($errors['system']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($errors['login'])): ?>
                <div class="error-message" style="padding: 10px; text-align: center;"><?php echo htmlspecialchars($errors['login']); ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="form-group">
                    <label for="username_or_email">Tên đăng nhập hoặc Email:</label>
                    <input type="text" id="username_or_email" name="username_or_email" value="<?php echo htmlspecialchars($username_or_email); ?>" required>
                    <?php if (isset($errors['field'])) echo '<div class="error-message">' . htmlspecialchars($errors['field']) . '</div>'; ?>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input type="password" id="password" name="password" required>
                    <?php if (isset($errors['password'])) echo '<div class="error-message">' . htmlspecialchars($errors['password']) . '</div>'; ?>
                </div>

                <button type="submit" class="btn-login">ĐĂNG NHẬP</button>
            </form>

            <p style="text-align: center; margin-top: 15px;">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2025 Website Bán Điện Thoại Uy tín- Chất lượng| HUMG Mobile</p>
            <div class="contact-info">
                <h4>Thông tin Liên hệ</h4>
                <p>Địa chỉ: 18 Đường phố Viên, Quận Bắc Từ Liêm, Hà Nội</p>
                <p>Hotline: 0987.654.321</p>
                <p>Email: support@humgmobile.vn</p>
            </div>
        </div>
    </footer>
</body>
</html>