<!-- CHAT WIDGET -->
<?php if(isset($_SESSION['user_id']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')): ?>
<link rel="stylesheet" href="css/chat_widget.css">
<div class="chat-widget">
    <div class="chat-toggle" id="chat-toggle">
        💬 <span class="chat-badge" id="chat-badge" style="display:none;">0</span>
    </div>
    
    <div class="chat-panel" id="chat-panel" style="display:none;">
        <div class="chat-header">
            <h4>Hỗ trợ trực tuyến</h4>
            <span class="close-chat" id="close-chat">&times;</span>
        </div>
        <div class="chat-body" id="chat-history">
            <!-- Tin nhắn sẽ được load vào đây -->
        </div>
        <div class="chat-footer">
            <input type="text" id="chat-input" placeholder="Nhập tin nhắn..." autocomplete="off">
            <button id="send-btn">Gửi</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    let roomId = 0;
    let lastMsgId = 0;
    let chatInterval = null;
    let isPanelOpen = false;
    let typingTimeout = null;

    const chatToggle = document.getElementById('chat-toggle');
    const chatPanel = document.getElementById('chat-panel');
    const chatBadge = document.getElementById('chat-badge');
    const closeChat = document.getElementById('close-chat');
    const chatHistory = document.getElementById('chat-history');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');

    // 1. Dịch thời gian thông minh
    function formatTimeAgo(dateString) {
        if (!dateString) return '';
        const diff = Math.floor((new Date() - new Date(dateString)) / 1000);
        if (diff < 60) return 'Vừa xong';
        if (diff < 3600) return Math.floor(diff / 60) + ' phút trước';
        if (diff < 86400) return Math.floor(diff / 3600) + ' giờ trước';
        return Math.floor(diff / 86400) + ' ngày trước';
    }

    // 2. Khởi tạo Room
    async function initRoom() {
        try {
            const res = await fetch('chat_api.php?action=get_or_create_room').then(r => r.json());
            if (res.success) {
                roomId = res.room_id;
                checkUnreadAndUpdate();
                setInterval(checkUnreadAndUpdate, 5000);
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function checkUnreadAndUpdate() {
        if (roomId === 0 || isPanelOpen) return;
        try {
            const res = await fetch('chat_api.php?action=unread_count').then(r => r.json());
            if (res.success && res.count > 0) {
                chatBadge.textContent = res.count;
                chatBadge.style.display = 'inline-block';
            } else {
                chatBadge.style.display = 'none';
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadChatMessages() {
        if (roomId === 0) return;
        try {
            const res = await fetch(`chat_api.php?action=get_messages&room_id=${roomId}&since_id=${lastMsgId}`).then(r => r.json());
            if (res.success) {
                // Typing Indicator
                const curTyping = document.getElementById('client-typing');
                if (res.is_typing) {
                    if (!curTyping) {
                        chatHistory.insertAdjacentHTML('beforeend', `<div id="client-typing" class="chat-admin"><span style="background:transparent; padding:0;"><i style="color:#666; font-size:12px;">Admin đang nhập...</i></span></div>`);
                        chatHistory.scrollTop = chatHistory.scrollHeight;
                    }
                } else {
                    if (curTyping) curTyping.remove();
                }

                if (res.messages.length > 0) {
                    const isScrolledBottom = chatHistory.scrollHeight - chatHistory.clientHeight <= chatHistory.scrollTop + 50;

                    res.messages.forEach(msg => {
                        const isMine = (msg.sender_id == <?= $_SESSION['user_id'] ?>);
                        const cls = isMine ? 'chat-mine' : 'chat-admin';
                        const timeStr = formatTimeAgo(msg.sent_at);
                        
                        const html = `<div class="${cls}" title="${timeStr}"><span>${msg.message}</span></div>`;
                        const typeEl = document.getElementById('client-typing');
                        if (typeEl) typeEl.insertAdjacentHTML('beforebegin', html);
                        else chatHistory.insertAdjacentHTML('beforeend', html);
                        
                        lastMsgId = Math.max(lastMsgId, msg.id);
                    });
                    
                    if (isScrolledBottom || res.messages.some(m => m.sender_id == <?= $_SESSION['user_id'] ?>)) {
                        chatHistory.scrollTop = chatHistory.scrollHeight;
                    }
                }
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function sendTyping() {
        if (roomId === 0) return;
        const fd = new FormData();
        fd.append('action', 'typing');
        fd.append('room_id', roomId);
        await fetch('chat_api.php', { method: 'POST', body: fd });
    }

    async function sendMessage() {
        const text = chatInput.value.trim();
        if (text === '' || roomId === 0) return;
        
        chatInput.value = '';
        const fd = new FormData();
        fd.append('action', 'send_message');
        fd.append('room_id', roomId);
        fd.append('message', text);

        try {
            const curTyping = document.getElementById('client-typing');
            if (curTyping) curTyping.remove();

            const res = await fetch('chat_api.php', { method: 'POST', body: fd }).then(r => r.json());
            if (res.success) {
                loadChatMessages();
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Toggle logic
    chatToggle.addEventListener('click', () => {
        isPanelOpen = chatPanel.style.display === 'none' || chatPanel.style.display === '';
        chatPanel.style.display = isPanelOpen ? 'flex' : 'none';
        
        if (isPanelOpen) {
            chatBadge.style.display = 'none';
            loadChatMessages();
            if (chatInterval) clearInterval(chatInterval);
            chatInterval = setInterval(loadChatMessages, 3000);
        } else {
            clearInterval(chatInterval);
        }
    });

    closeChat.addEventListener('click', () => {
        chatPanel.style.display = 'none';
        isPanelOpen = false;
        clearInterval(chatInterval);
    });

    sendBtn.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendMessage();
        } else {
            if (typingTimeout) clearTimeout(typingTimeout);
            typingTimeout = setTimeout(sendTyping, 300);
        }
    });

    initRoom();
});
</script>
<?php endif; ?>
