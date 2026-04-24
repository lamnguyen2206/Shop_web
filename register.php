<?php
session_start();
require_once 'includes/db_connection.php'; 

$errors = [];
$success_message = '';
$username = ''; 
$email = '';    

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';


    if (empty($username)) {
        $errors['username'] = 'Tên đăng nhập không được để trống.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{5,20}$/', $username)) {
        $errors['username'] = 'Tên đăng nhập từ 5-20 ký tự, chỉ chứa chữ cái, số và gạch dưới.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không hợp lệ.';
    }

    if (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Xác nhận mật khẩu không khớp.';
    }
    

    if (empty($errors)) {
        $check_sql = "SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1";
        if ($stmt_user = $conn->prepare($check_sql)) {
            $stmt_user->bind_param("ss", $username, $email);
            $stmt_user->execute();
            $result = $stmt_user->get_result();
            $existing_user = $result->fetch_assoc();

            if ($existing_user) {
                if ($existing_user['username'] === $username) {
                    $errors['username'] = 'Tên đăng nhập này đã tồn tại.';
                }
                if ($existing_user['email'] === $email) {
                    $errors['email'] = 'Email này đã được sử dụng.';
                }
            }
            $stmt_user->close();
        } else {
            $errors['system'] = 'Lỗi hệ thống CSDL khi kiểm tra dữ liệu.';
        }
    }


    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)"; 
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $username, $email, $hashed_password);
            
            if ($stmt->execute()) {
                $success_message = "Đăng ký thành công! Bạn có thể <a href='login.php'>đăng nhập</a> ngay.";

                $username = $email = ''; 
            } else {
                $errors['system'] = 'Lỗi trong quá trình lưu dữ liệu.';
            }
            $stmt->close();
        } else {
            $errors['system'] = 'Lỗi hệ thống khi chuẩn bị đăng ký.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký Tài khoản | HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <header>
        <h1>HUMG Mobile</h1>
        <nav>
            <a href="index.php">Trang chủ</a>
            <a href="login.php">Đăng nhập</a>
            <a href="cart.php">Giỏ hàng</a>
        </nav>
    </header>

    <main class="container">
        <div class="register-box">
            <h2 style="text-align: center; color: #007bff; margin-bottom: 25px;">Đăng ký Thành viên</h2>

            <?php if (!empty($success_message)): ?>
                <div class="alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($errors['system'])): ?>
                <div class="error-message" style="border: 1px solid #dc3545; padding: 10px; margin-bottom: 15px; background: #fff5f5;"><?php echo $errors['system']; ?></div>
            <?php endif; ?>

            <form action="register.php" method="POST">
                <div class="form-group">
                    <label for="username">Tên đăng nhập:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Ví dụ: nva_2025" required>
                    <?php if (isset($errors['username'])) echo '<div class="error-message">' . $errors['username'] . '</div>'; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="example@gmail.com" required>
                    <?php if (isset($errors['email'])) echo '<div class="error-message">' . $errors['email'] . '</div>'; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input type="password" id="password" name="password" placeholder="Tối thiểu 6 ký tự" required>
                    <?php if (isset($errors['password'])) echo '<div class="error-message">' . $errors['password'] . '</div>'; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu:</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
                    <?php if (isset($errors['confirm_password'])) echo '<div class="error-message">' . $errors['confirm_password'] . '</div>'; ?>
                </div>

                <button type="submit" class="btn-register">TẠO TÀI KHOẢN</button>
            </form>

            <p style="text-align: center; margin-top: 20px; color: #666;">Đã có tài khoản? <a href="login.php" style="color: #007bff; text-decoration: none;">Đăng nhập ngay</a></p>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 HUMG Mobile - Kết nối đam mê công nghệ</p>
    </footer>
</body>
</html>