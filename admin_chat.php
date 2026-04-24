<?php
session_start();
require_once 'includes/db_connection.php';

// Kiểm tra quyền Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý Chat - HUMG Mobile</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin_chat.css">
</head>
<body>

<div class="container admin-chat-container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
        <h1 style="margin:0;">Quản lý Hỗ trợ Trực tuyến</h1>
        <div>
            <a href="admin_orders.php" style="text-decoration:none; color:#007bff; font-weight:bold; margin-right: 15px;">Quản lý Đơn hàng</a>
            <a href="index.php" style="text-decoration:none; color:#007bff; font-weight:bold;">← Về trang chủ</a>
        </div>
    </div>

    <div class="chat-layout">
        <!-- Sidebar danh sách phòng chat -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-header">
                <h3>Danh sách khách hàng</h3>
            </div>
            <div class="chat-room-list" id="room-list">
                <div style="padding: 20px; text-align: center; color: #777;">Đang tải dữ liệu...</div>
            </div>
        </div>

        <!-- Khung chat chính -->
        <div class="chat-main" id="chat-main" style="display: none;">
            <div class="chat-main-header">
                <h3 id="current-chat-name">Tên khách hàng</h3>
            </div>
            <div class="chat-messages" id="admin-chat-messages">
                <!-- Tin nhắn sẽ load vào đây -->
            </div>
            <div class="chat-input-area">
                <input type="hidden" id="current-room-id" value="0">
                <input type="text" id="admin-chat-input" placeholder="Nhập tin nhắn trả lời..." autocomplete="off">
                <button id="admin-btn-send">Gửi</button>
            </div>
        </div>
        
        <div class="chat-main" id="chat-empty" style="display: flex; align-items: center; justify-content: center; background: #f9f9f9;">
            <p style="color: #999; font-size: 1.2em;">Chọn một cuộc trò chuyện để bắt đầu</p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentRoomId = 0;
    let lastMessageId = 0;
    let pollingInterval = null;
    let typingTimeout = null;

    // 1. Dịch thời gian thông minh (Smart Time)
    function formatTimeAgo(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const diffSeconds = Math.floor((new Date() - date) / 1000);
        
        if (diffSeconds < 60) return 'Vừa xong';
        if (diffSeconds < 3600) return Math.floor(diffSeconds / 60) + ' phút trước';
        if (diffSeconds < 86400) return Math.floor(diffSeconds / 3600) + ' giờ trước';
        return Math.floor(diffSeconds / 86400) + ' ngày trước';
    }

    // 2. Tải danh sách phòng chat (Dùng Fetch API)
    async function loadRooms() {
        try {
            const response = await fetch('chat_api.php?action=get_rooms');
            const res = await response.json();
            
            if (res.success) {
                const roomList = document.getElementById('room-list');
                roomList.innerHTML = '';
                
                if (res.rooms.length === 0) {
                    roomList.innerHTML = '<div style="padding: 20px; text-align: center; color: #777;">Chưa có cuộc trò chuyện nào</div>';
                    return;
                }
                
                res.rooms.forEach(room => {
                    const activeClass = (room.id == currentRoomId) ? 'active' : '';
                    const unreadHtml = room.unread_count > 0 ? `<span class="unread-badge">${room.unread_count}</span>` : '';
                    let lastMsg = room.last_message || 'Chưa có tin nhắn';
                    
                    if (lastMsg.length > 25) lastMsg = lastMsg.substring(0, 25) + '...';
                    
                    // Ưu tiên hiển thị Typing nếu khách đang gõ
                    if (room.is_typing) {
                        lastMsg = '<i style="color:#007bff; font-weight:bold;">Đang gõ...</i>';
                    } else if (room.last_message_at) {
                        lastMsg = `[${formatTimeAgo(room.last_message_at)}] ${lastMsg}`;
                    }

                    const roomHtml = `
                        <div class="chat-room-item ${activeClass}" data-id="${room.id}" data-name="${room.username}">
                            <div class="room-info">
                                <div class="room-name">${room.username} ${unreadHtml}</div>
                                <div class="room-last-msg">${lastMsg}</div>
                            </div>
                        </div>
                    `;
                    roomList.insertAdjacentHTML('beforeend', roomHtml);
                });
            }
        } catch (error) {
            console.error('Lỗi tải danh sách room:', error);
        }
    }

    // 3. Tải tin nhắn và Hiệu ứng Typing
    async function loadMessages(roomId) {
        if (roomId === 0) return;
        try {
            const response = await fetch(`chat_api.php?action=get_messages&room_id=${roomId}&since_id=${lastMessageId}`);
            const res = await response.json();
            
            if (res.success) {
                const chatBox = document.getElementById('admin-chat-messages');
                
                // --- Xử lý hiệu ứng Typing ---
                const typingEl = document.getElementById('typing-indicator');
                if (res.is_typing) {
                    if (!typingEl) {
                        chatBox.insertAdjacentHTML('beforeend', `
                            <div id="typing-indicator" class="chat-msg msg-their">
                                <div class="msg-bubble" style="background:transparent; padding:0; box-shadow:none;">
                                    <div style="padding:10px; background:#f1f0f0; border-radius:15px; display:inline-block; font-size:0.85em; font-style:italic;">Đang nhập...</div>
                                </div>
                            </div>
                        `);
                        // Auto scroll
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                } else {
                    if (typingEl) typingEl.remove();
                }

                // --- Xử lý render tin nhắn mới ---
                if (res.messages.length > 0) {
                    const isScrolledToBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 50;

                    res.messages.forEach(msg => {
                        // So sánh linh hoạt ID từ Backend
                        const isMine = (msg.sender_id == <?= $_SESSION['user_id'] ?>);
                        const msgClass = isMine ? 'msg-mine' : 'msg-their';
                        const timeStr = new Date(msg.sent_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute:'2-digit'});
                        
                        const msgHtml = `
                            <div class="chat-msg ${msgClass}">
                                <div class="msg-bubble">${msg.message}</div>
                                <div class="msg-time" title="${formatTimeAgo(msg.sent_at)}">${timeStr}</div>
                            </div>
                        `;
                        
                        // Chèn tin nhắn lên TRƯỚC cái indicator typing (nếu nó đang tồn tại)
                        const currentTypingEl = document.getElementById('typing-indicator');
                        if (currentTypingEl) {
                            currentTypingEl.insertAdjacentHTML('beforebegin', msgHtml);
                        } else {
                            chatBox.insertAdjacentHTML('beforeend', msgHtml);
                        }
                        
                        lastMessageId = Math.max(lastMessageId, msg.id);
                    });
                    
                    // Auto-scroll thông minh
                    if (isScrolledToBottom || res.messages.some(m => m.sender_id == <?= $_SESSION['user_id'] ?>)) {
                        chatBox.scrollTop = chatBox.scrollHeight;
                    }
                }
            }
        } catch (error) {
            console.error('Lỗi tải tin nhắn:', error);
        }
    }

    // 4. Gửi tín hiệu Typing thao tác
    async function sendTypingSignal() {
        if (currentRoomId === 0) return;
        const formData = new FormData();
        formData.append('action', 'typing');
        formData.append('room_id', currentRoomId);
        await fetch('chat_api.php', { method: 'POST', body: formData });
    }

    // 5. Gửi tin nhắn thực sự
    async function sendMessage() {
        const input = document.getElementById('admin-chat-input');
        const msg = input.value.trim();
        if (msg === '' || currentRoomId === 0) return;

        const formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('room_id', currentRoomId);
        formData.append('message', msg);
        
        try {
            input.value = '';
            // Gỡ bỏ ngay typing indicator để UI mượt hơn
            const typingEl = document.getElementById('typing-indicator');
            if(typingEl) typingEl.remove();

            const response = await fetch('chat_api.php', { method: 'POST', body: formData });
            const res = await response.json();
            
            if (res.success) {
                loadMessages(currentRoomId);
                loadRooms();
            } else {
                alert('Lỗi: ' + res.error);
            }
        } catch (error) {
            console.error('Lỗi gửi tin nhắn:', error);
        }
    }

    // --- CÁC SỰ KIỆN GIAO DIỆN ---

    // Click chọn người để chat
    document.getElementById('room-list').addEventListener('click', (e) => {
        const item = e.target.closest('.chat-room-item');
        if (!item) return;

        const roomId = item.getAttribute('data-id');
        const name = item.getAttribute('data-name');
        
        if (roomId == currentRoomId) return;

        // Xóa class active cũ
        document.querySelectorAll('.chat-room-item').forEach(el => el.classList.remove('active'));
        item.classList.add('active');

        currentRoomId = roomId;
        lastMessageId = 0; // Reset phiên

        // Cập nhật giao diện trạng thái online
        document.getElementById('current-chat-name').innerHTML = name + ' <span style="font-size:12px; color:#2ecc71; margin-left:10px;">🟢 Live</span>';
        document.getElementById('current-room-id').value = roomId;
        document.getElementById('admin-chat-messages').innerHTML = '';
        
        document.getElementById('chat-empty').style.display = 'none';
        document.getElementById('chat-main').style.display = 'block';

        loadMessages(roomId);
        loadRooms();

        // Optimized Polling: 3s
        if (pollingInterval) clearInterval(pollingInterval);
        pollingInterval = setInterval(() => {
            loadMessages(currentRoomId);
        }, 3000);
    });

    // Enter & Send
    document.getElementById('admin-btn-send').addEventListener('click', sendMessage);
    document.getElementById('admin-chat-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        } else {
            // Typing logic: Gửi tín hiệu sau khi gõ 300ms
            if (typingTimeout) clearTimeout(typingTimeout);
            typingTimeout = setTimeout(sendTypingSignal, 300);
        }
    });

    // --- KHỞI ĐỘNG HỆ THỐNG ---
    loadRooms();
    setInterval(loadRooms, 5000); // 5s update danh sách phòng 1 lần
});
</script>

</body>
</html>
