<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$room_id = filter_var($_GET['room_id'] ?? 0, FILTER_VALIDATE_INT);
if(!$room_id) {
    header("Location: home.php");
    exit;
}

// Get room info
$room = $pdo->prepare("SELECT * FROM rooms WHERE id = ?");
$room->execute([$room_id]);
$room_data = $room->fetch();

if(!$room_data) {
    header("Location: home.php");
    exit;
}

// Get user info
$user_stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($room_data['name']) ?> - SUGO Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #0f172a;
            --light: #f8fafc;
            --radius: 12px;
        }
        
        body {
            background: #0f172a;
            color: var(--light);
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
        }
        
        .chat-sidebar {
            width: 300px;
            background: #1e293b;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .room-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .room-icon {
            color: var(--primary);
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: linear-gradient(180deg, #0f172a 0%, #1e1b2e 100%);
        }
        
        .message {
            display: flex;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--primary);
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .message-content {
            max-width: 70%;
        }
        
        .message-header {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            gap: 10px;
        }
        
        .message-username {
            font-weight: 600;
            color: white;
        }
        
        .message-time {
            color: #64748b;
            font-size: 0.85rem;
        }
        
        .message-bubble {
            background: #1e293b;
            padding: 12px 16px;
            border-radius: 18px;
            border-top-left-radius: 4px;
            position: relative;
            word-wrap: break-word;
        }
        
        .message.sent {
            flex-direction: row-reverse;
        }
        
        .message.sent .message-avatar {
            margin-right: 0;
            margin-left: 1rem;
        }
        
        .message.sent .message-content {
            align-items: flex-end;
        }
        
        .message.sent .message-bubble {
            background: var(--primary);
            color: white;
            border-top-right-radius: 4px;
            border-top-left-radius: 18px;
        }
        
        .message-input-container {
            background: rgba(30, 41, 59, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 1.5rem;
        }
        
        .message-input-group {
            display: flex;
            gap: 10px;
        }
        
        .message-input {
            flex: 1;
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 16px;
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        
        .message-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            outline: none;
        }
        
        .send-btn {
            background: var(--primary);
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .send-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .online-users {
            padding: 1.5rem;
            flex: 1;
            overflow-y: auto;
        }
        
        .online-user {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
        }
        
        .online-user:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        
        .online-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            margin-right: 12px;
            border: 2px solid var(--primary);
        }
        
        .online-badge {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 5px;
        }
        
        .typing-dot {
            width: 6px;
            height: 6px;
            background: var(--primary);
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }
        
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .back-btn:hover {
            color: white;
        }
        
        .emoji-picker {
            position: relative;
        }
        
        .emoji-btn {
            background: none;
            border: none;
            color: #94a3b8;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 10px;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .emoji-btn:hover {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .chat-sidebar {
                display: none;
            }
            
            .message-content {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Sidebar -->
        <div class="chat-sidebar">
            <div class="chat-header">
                <div>
                    <h5 class="mb-0">Online Users</h5>
                    <small class="text-muted" id="online-count">Loading...</small>
                </div>
                <a href="home.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
            
            <div class="online-users" id="onlineUsers">
                <!-- Online users will be loaded here -->
            </div>
        </div>
        
        <!-- Main Chat Area -->
        <div class="chat-main">
            <!-- Header -->
            <div class="chat-header">
                <div class="room-title">
                    <i class="fas fa-comments room-icon"></i>
                    <?= htmlspecialchars($room_data['name']) ?>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span id="typingIndicator" class="text-muted" style="display: none;">
                        <i class="fas fa-pencil-alt"></i> someone is typing...
                    </span>
                    <button class="btn btn-sm btn-outline-light" onclick="toggleSidebar()">
                        <i class="fas fa-users"></i>
                    </button>
                </div>
            </div>
            
            <!-- Messages -->
            <div class="messages-container" id="messagesContainer">
                <!-- Messages will be loaded here -->
            </div>
            
            <!-- Input Area -->
            <div class="message-input-container">
                <form id="messageForm" class="message-input-group">
                    <input type="hidden" id="room_id" value="<?= $room_id ?>">
                    <button type="button" class="emoji-btn" id="emojiBtn">
                        <i class="far fa-smile"></i>
                    </button>
                    <input type="text" id="messageInput" class="message-input" placeholder="Type your message..." autocomplete="off">
                    <div class="emoji-picker">
                        <button type="submit" class="send-btn" id="sendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const messagesContainer = document.getElementById('messagesContainer');
        const messageInput = document.getElementById('messageInput');
        const messageForm = document.getElementById('messageForm');
        const onlineUsers = document.getElementById('onlineUsers');
        const typingIndicator = document.getElementById('typingIndicator');
        const onlineCount = document.getElementById('onlineCount');
        
        let typingTimeout;
        let lastTypingTime = 0;
        
        // Auto-scroll to bottom
        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
        
        // Load messages
        function loadMessages() {
            fetch(`room_fetch.php?room_id=<?= $room_id ?>`)
                .then(response => response.text())
                .then(data => {
                    messagesContainer.innerHTML = data;
                    scrollToBottom();
                });
        }
        
        // Load online users
        function loadOnlineUsers() {
            fetch(`room_online_users.php?room_id=<?= $room_id ?>`)
                .then(response => response.text())
                .then(data => {
                    onlineUsers.innerHTML = data;
                });
            
            fetch(`room_online_count.php?room_id=<?= $room_id ?>`)
                .then(response => response.text())
                .then(data => {
                    onlineCount.textContent = data;
                });
        }
        
        // Send message
        messageForm.onsubmit = function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            
            if(message) {
                fetch('room_send.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `room_id=<?= $room_id ?>&message=${encodeURIComponent(message)}`
                }).then(() => {
                    messageInput.value = '';
                    loadMessages();
                });
            }
        };
        
        // Typing indicator
        messageInput.addEventListener('input', function() {
            lastTypingTime = Date.now();
            
            if(!typingTimeout) {
                typingTimeout = setTimeout(() => {
                    const typingTime = Date.now();
                    const timeDiff = typingTime - lastTypingTime;
                    
                    if(timeDiff >= 1000) {
                        // Send typing stopped signal
                        // You can implement this with AJAX
                    }
                    typingTimeout = null;
                }, 1000);
            }
        });
        
        // Send typing status
        messageInput.addEventListener('keypress', function() {
            // Send typing started signal via AJAX
            // fetch('typing_start.php?room_id=<?= $room_id ?>');
        });
        
        // Auto-refresh
        setInterval(loadMessages, 2000);
        setInterval(loadOnlineUsers, 5000);
        
        // Initial load
        loadMessages();
        loadOnlineUsers();
        
        // Focus on input
        messageInput.focus();
        
        // Toggle sidebar on mobile
        function toggleSidebar() {
            document.querySelector('.chat-sidebar').classList.toggle('active');
        }
        
        // Emoji picker (basic implementation)
        document.getElementById('emojiBtn').addEventListener('click', function() {
            const emojis = ['😀', '😂', '🥰', '😎', '🤔', '👍', '❤️', '🔥', '✨'];
            const picker = document.createElement('div');
            picker.style.cssText = `
                position: absolute;
                bottom: 60px;
                left: 0;
                background: #1e293b;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 12px;
                padding: 10px;
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 5px;
                z-index: 1000;
            `;
            
            emojis.forEach(emoji => {
                const btn = document.createElement('button');
                btn.textContent = emoji;
                btn.style.cssText = `
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    cursor: pointer;
                    padding: 5px;
                    border-radius: 5px;
                    transition: background 0.3s;
                `;
                btn.onmouseover = () => btn.style.background = 'rgba(255,255,255,0.1)';
                btn.onmouseout = () => btn.style.background = 'none';
                btn.onclick = () => {
                    messageInput.value += emoji;
                    messageInput.focus();
                    document.body.removeChild(picker);
                };
                picker.appendChild(btn);
            });
            
            document.body.appendChild(picker);
            
            // Remove picker when clicking outside
            setTimeout(() => {
                document.addEventListener('click', function removePicker(e) {
                    if(!picker.contains(e.target) && e.target !== document.getElementById('emojiBtn')) {
                        if(document.body.contains(picker)) {
                            document.body.removeChild(picker);
                        }
                        document.removeEventListener('click', removePicker);
                    }
                });
            }, 0);
        });
        
        // Update status periodically
        setInterval(() => {
            fetch('update_status.php').catch(() => {});
        }, 30000);
    </script>
</body>
</html>