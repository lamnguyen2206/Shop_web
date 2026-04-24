<?php
session_start();
require_once 'includes/db_connection.php';

header('Content-Type: application/json; charset=utf-8');

// Bỏ hằng số ADMIN_ID vì đã dùng role

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$action = $_REQUEST['action'] ?? '';

// ─────────────────────────────────────────────
// Hàm tiện ích
// ─────────────────────────────────────────────

function getOrCreateRoom($conn, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM chat_rooms WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) return $row['id'];

    $stmt2 = $conn->prepare("INSERT INTO chat_rooms (user_id) VALUES (?)");
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $room_id = $stmt2->insert_id;
    $stmt2->close();
    return $room_id;
}

// ─────────────────────────────────────────────
// Xử lý các action
// ─────────────────────────────────────────────

switch ($action) {

    // ---------- Lấy hoặc tạo room cho user hiện tại ----------
    case 'get_or_create_room':
        if ($is_admin) {
            echo json_encode(['success' => false, 'error' => 'Admin không cần tạo room']);
            exit;
        }
        $room_id = getOrCreateRoom($conn, $current_user_id);
        echo json_encode(['success' => true, 'room_id' => $room_id]);
        break;

    // ---------- Gửi tin nhắn ----------
    case 'send_message':
        $message  = trim($_POST['message'] ?? '');
        $room_id  = (int)($_POST['room_id'] ?? 0);

        if (empty($message) || $room_id === 0) {
            echo json_encode(['success' => false, 'error' => 'Thiếu dữ liệu']);
            exit;
        }

        // Kiểm tra quyền: admin có thể vào bất kỳ room; user chỉ vào room của mình
        if (!$is_admin) {
            $chk = $conn->prepare("SELECT id FROM chat_rooms WHERE id = ? AND user_id = ?");
            $chk->bind_param("ii", $room_id, $current_user_id);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                echo json_encode(['success' => false, 'error' => 'Không có quyền']);
                exit;
            }
            $chk->close();
        }

        $stmt = $conn->prepare("INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $room_id, $current_user_id, $message);
        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        // Cập nhật last_message_at
        $conn->query("UPDATE chat_rooms SET last_message_at = NOW() WHERE id = $room_id");

        echo json_encode(['success' => true, 'message_id' => $new_id]);
        break;

    // ---------- Gửi tín hiệu đang nhập ----------
    case 'typing':
        $room_id = (int)($_POST['room_id'] ?? 0);
        if ($room_id === 0) {
            echo json_encode(['success' => false]);
            exit;
        }
        
        $col = $is_admin ? 'typing_admin_at' : 'typing_user_at';
        $stmt = $conn->prepare("UPDATE chat_rooms SET $col = NOW() WHERE id = ?");
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
        break;

    // ---------- Lấy tin nhắn (polling) ----------
    case 'get_messages':
        $room_id  = (int)($_GET['room_id'] ?? 0);
        $since_id = (int)($_GET['since_id'] ?? 0);

        if ($room_id === 0) {
            echo json_encode(['success' => false, 'error' => 'Thiếu room_id']);
            exit;
        }

        // Kiểm tra quyền
        if (!$is_admin) {
            $chk = $conn->prepare("SELECT id FROM chat_rooms WHERE id = ? AND user_id = ?");
            $chk->bind_param("ii", $room_id, $current_user_id);
            $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                echo json_encode(['success' => false, 'error' => 'Không có quyền']);
                exit;
            }
            $chk->close();
        }

        $stmt = $conn->prepare("
            SELECT m.id, m.sender_id, m.message, m.sent_at, m.is_read,
                   u.username
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ? AND m.id > ?
            ORDER BY m.sent_at ASC
            LIMIT 50
        ");
        $stmt->bind_param("ii", $room_id, $since_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $row['message'] = htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8');
            $row['username'] = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
            $messages[] = $row;
        }
        $stmt->close();

        // Đánh dấu đọc nếu là admin hoặc là user trong room
        if (!empty($messages)) {
            $conn->query("UPDATE chat_messages SET is_read = 1
                          WHERE room_id = $room_id AND sender_id != $current_user_id AND is_read = 0");
        }

        // Lấy trạng thái typing của đối phương
        $is_typing = false;
        $typing_col = $is_admin ? 'typing_user_at' : 'typing_admin_at';
        $res_typing = $conn->query("SELECT IF(TIMESTAMPDIFF(SECOND, $typing_col, NOW()) <= 4, 1, 0) AS is_typing FROM chat_rooms WHERE id = $room_id");
        if ($res_typing && $row_t = $res_typing->fetch_assoc()) {
            $is_typing = (bool)$row_t['is_typing'];
        }

        echo json_encode(['success' => true, 'messages' => $messages, 'is_typing' => $is_typing]);
        break;

    // ---------- Lấy danh sách rooms (Admin only) ----------
    case 'get_rooms':
        if (!$is_admin) {
            echo json_encode(['success' => false, 'error' => 'Không có quyền']);
            exit;
        }

        $sql = "SELECT r.id, r.user_id, r.last_message_at,
                       u.username,
                       (SELECT COUNT(*) FROM chat_messages m
                        WHERE m.room_id = r.id AND m.sender_id != ? AND m.is_read = 0) AS unread_count,
                       (SELECT message FROM chat_messages m2
                        WHERE m2.room_id = r.id ORDER BY m2.sent_at DESC LIMIT 1) AS last_message,
                       IF(TIMESTAMPDIFF(SECOND, r.typing_user_at, NOW()) <= 4, 1, 0) AS is_typing
                FROM chat_rooms r
                JOIN users u ON r.user_id = u.id
                ORDER BY r.last_message_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $rooms = [];
        while ($row = $result->fetch_assoc()) {
            if (isset($row['last_message'])) {
                $row['last_message'] = htmlspecialchars($row['last_message'], ENT_QUOTES, 'UTF-8');
            }
            $row['username'] = htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8');
            $rooms[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'rooms' => $rooms]);
        break;

    // ---------- Đếm tin nhắn chưa đọc (User) ----------
    case 'unread_count':
        if ($is_admin) {
            // Admin: đếm tổng tất cả room
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt FROM chat_messages m
                JOIN chat_rooms r ON m.room_id = r.id
                WHERE m.sender_id != ? AND m.is_read = 0
            ");
        } else {
            // User: đếm room của mình
            $stmt = $conn->prepare("
                SELECT COUNT(*) AS cnt FROM chat_messages m
                JOIN chat_rooms r ON m.room_id = r.id
                WHERE r.user_id = ? AND m.sender_id != ? AND m.is_read = 0
            ");
        }
        if ($is_admin) {
            $stmt->bind_param("i", $current_user_id);
        } else {
            $stmt->bind_param("ii", $current_user_id, $current_user_id);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'count' => (int)$row['cnt']]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Action không hợp lệ']);
        break;
}
