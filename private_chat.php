<?php 
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$other_id) {
    header("Location: discover.php");
    exit;
}

// Get other user info
$stmt = $pdo->prepare("SELECT username, avatar, TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago FROM users WHERE id = ?");
$stmt->execute([$other_id]);
$other_user = $stmt->fetch();

if(!$other_user) {
    header("Location: discover.php");
    exit;
}

$is_online = $other_user['minutes_ago'] < 5;

// Mark messages as read
$pdo->prepare("UPDATE private_messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ?")
    ->execute([$_SESSION['user_id'], $other_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= htmlspecialchars($other_user['username']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
            color: #fff; 
            height: 100vh;
            overflow: hidden;
        }
        .chat-container { 
            max-width: 800px; 
            height: 95vh;
            margin: 10px auto;
            display: flex;
            flex-direction: column;
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
        }
        .chat-header { 
            background: rgba(30, 41, 59, 0.9);
            padding: 15px 20px; 
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-messages { 
            flex: 1;
            overflow-y: auto; 
            padding: 20px; 
            background: rgba(15, 23, 42, 0.5);
        }
        .message { 
            margin-bottom: 15px; 
            padding: 12px 16px; 
            border-radius: 15px;
            max-width: 75%;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .message.sent { 
            background: linear-gradient(45deg, #6366f1, #4f46e5);
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        .message.received { 
            background: rgba(255, 255, 255, 0.1);
            border-bottom-left-radius: 5px;
        }
        .message-avatar { 
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            border: 2px solid #4cc9f0; 
            margin-right: 10px;
        }
        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-left: 10px;
            font-size: 0.9rem;
            color: #94a3b8;
        }
        .typing-dot {
            width: 6px;
            height: 6px;
            background: #6366f1;
            border-radius: 50%;
            animation: typing 1.4s infinite;
        }
        .typing-dot:nth-child(2) { animation-delay: 0.2s; }
        .typing-dot:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-5px); }
        }
        
        .online-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .message-input-group {
            background: rgba(30, 41, 59, 0.9);
            padding: 15px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        .message-time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 5px;
            text-align: right;
        }
        .user-info {
            display: flex;
            align-items: center;
        }
        .back-btn {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.3s;
        }
        .back-btn:hover {
            color: white;
        }
        #loading {
            text-align: center;
            padding: 20px;
            color: #94a3b8;
        }
        /* Add these styles to existing CSS */

.coin-warning {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    color: #fbbf24;
    padding: 10px;
    border-radius: 8px;
    margin: 10px 20px;
    text-align: center;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}

.message.sent .coin-cost {
    font-size: 0.7rem;
    margin-left: 8px;
    opacity: 0.7;
}

/* Coin modal animations */
@keyframes modalFadeIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.coin-modal > div {
    animation: modalFadeIn 0.3s ease;
}

/* Task quick buttons */
.task-quick {
    transition: all 0.3s;
    cursor: pointer;
}

.task-quick:hover {
    transform: translateY(-2px);
    background: rgba(99, 102, 241, 0.2) !important;
    border-color: #6366f1;
}
/* Chat Header Styles - Fixed for Mobile */
.chat-header {
    background: rgba(30, 41, 59, 0.9);
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    min-height: 70px;
}

.user-info {
    display: flex;
    align-items: center;
    flex: 1;
    min-width: 0; /* Allows text truncation */
}

.user-info h5 {
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}

/* Coin display positioning - FIXED for mobile */
.coin-display-header {
    position: static !important;
    margin-left: 10px;
    white-space: nowrap;
}

.coin-info-tooltip {
    position: static !important;
    margin-left: 5px;
}

/* Typing indicator positioning */
#typing-indicator {
    position: absolute;
    bottom: -20px;
    left: 70px;
    background: rgba(30, 41, 59, 0.9);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    z-index: 100;
}

/* Mobile Responsive Fixes */
@media (max-width: 768px) {
    .chat-header {
        padding: 10px 12px;
        flex-wrap: wrap;
    }
    
    .user-info {
        max-width: calc(100% - 50px);
    }
    
    .user-info h5 {
        max-width: 120px;
        font-size: 1rem;
    }
    
    .user-info small {
        font-size: 0.7rem;
        display: block;
    }
    
    .message-avatar {
        width: 32px;
        height: 32px;
        margin-right: 8px;
    }
    
    .back-btn {
        margin-right: 8px !important;
    }
    
    /* Stack coin display on very small screens */
    .coin-display-header {
        position: absolute !important;
        top: 10px;
        right: 50px;
        font-size: 0.8rem !important;
        padding: 3px 8px !important;
    }
    
    .coin-info-tooltip {
        position: absolute !important;
        top: 10px;
        right: 10px;
    }
    
    #typing-indicator {
        left: 50px;
        bottom: -25px;
        font-size: 0.7rem;
        padding: 3px 10px;
    }
}

/* Extra small devices */
@media (max-width: 480px) {
    .chat-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
        min-height: 90px;
    }
    
    .user-info {
        max-width: 100%;
        width: 100%;
    }
    
    .user-info h5 {
        max-width: 180px;
    }
    
    .coin-display-header {
        position: static !important;
        margin-left: 0;
        margin-top: 5px;
        align-self: flex-end;
    }
    
    .coin-info-tooltip {
        position: static !important;
        margin-left: 5px;
    }
    
    #typing-indicator {
        left: 45px;
        bottom: -20px;
    }
}

/* Fix for coin display header positioning */
#coin-display-header {
    position: static;
    margin-left: 10px;
    display: inline-flex;
    align-items: center;
}

/* Tooltip positioning fix */
.coin-tooltip {
    position: fixed !important;
    bottom: auto !important;
    top: 60px !important;
    right: 20px !important;
    left: auto !important;
    width: 250px !important;
    z-index: 9999 !important;
}

@media (max-width: 768px) {
    .coin-tooltip {
        right: 10px !important;
        left: auto !important;
        width: 200px !important;
        top: 80px !important;
    }

}
/* ===== WHATSAPP STYLE MESSAGE INPUT ===== */
.message-input-wrapper {
    background: #1e293b;
    padding: 12px 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    width: 100%;
}

.message-input-container {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #0f172a;
    border-radius: 28px;
    padding: 4px 8px 4px 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    transition: all 0.2s ease;
}

.message-input-container:focus-within {
    border-color: #6366f1;
    background: #0b1120;
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}

.message-input-field {
    flex: 1;
    background: transparent;
    border: none;
    color: white;
    font-size: 0.95rem;
    padding: 10px 0;
    outline: none;
    min-width: 0; /* Prevents flex overflow */
}

.message-input-field::placeholder {
    color: #94a3b8;
    font-weight: 400;
}

.message-input-field:focus::placeholder {
    color: #64748b;
}

/* Emoji Button - WhatsApp Style */
.emoji-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 1.3rem;
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
    cursor: pointer;
    width: 36px;
    height: 36px;
}

.emoji-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}

/* Send Button - WhatsApp Style */
.send-btn {
    background: #6366f1;
    border: none;
    color: white;
    font-size: 1rem;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border: none;
    box-shadow: 0 2px 5px rgba(99, 102, 241, 0.3);
}

.send-btn:hover {
    background: #4f46e5;
    transform: scale(1.05);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.4);
}

.send-btn i {
    font-size: 1rem;
}

/* When input is empty, show send button as disabled style */
.send-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Low coins warning style for input */
.message-input-field.coins-warning {
    color: #f59e0b;
}

.message-input-field.coins-warning::placeholder {
    color: #f59e0b;
    font-weight: 500;
}

/* Emoji Picker - WhatsApp Style */
.emoji-picker-container {
    position: absolute;
    bottom: 80px;
    left: 20px;
    background: #1e293b;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    max-width: 280px;
}

.emoji-item {
    background: transparent;
    border: none;
    font-size: 1.5rem;
    padding: 8px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.emoji-item:hover {
    background: rgba(99, 102, 241, 0.2);
    transform: scale(1.1);
}

/* Mobile Responsive - WhatsApp Style */
@media (max-width: 768px) {
    .message-input-wrapper {
        padding: 10px 12px;
    }
    
    .message-input-container {
        padding: 2px 6px 2px 10px;
    }
    
    .message-input-field {
        font-size: 0.9rem;
        padding: 8px 0;
    }
    
    .emoji-btn {
        width: 32px;
        height: 32px;
        font-size: 1.2rem;
    }
    
    .send-btn {
        width: 38px;
        height: 38px;
    }
}

/* Very small devices */
@media (max-width: 480px) {
    .message-input-wrapper {
        padding: 8px 10px;
    }
    
    .emoji-btn {
        width: 30px;
        height: 30px;
        font-size: 1.1rem;
    }
    
    .send-btn {
        width: 36px;
        height: 36px;
    }
    
    .send-btn i {
        font-size: 0.9rem;
    }
}

.attach-btn {
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 1.2rem;
    padding: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
    cursor: pointer;
    width: 36px;
    height: 36px;
}

.attach-btn:hover {
    background: rgba(99, 102, 241, 0.1);
    color: #6366f1;
}


    </style>
</head>
<body>
    <div class="container-fluid h-100">
        <div class="chat-container">
            <!-- Header -->
           <!-- Header -->
<div class="chat-header">
    <div class="user-info">
        <a href="discover.php" class="back-btn me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <img src="uploads/<?= htmlspecialchars($other_user['avatar'] ?? 'default.jpg') ?>" 
             class="message-avatar" alt="Avatar">
        <div>
            <h5 class="mb-0"><?= htmlspecialchars($other_user['username']) ?></h5>
            <small class="text-muted" style="color: #e5e7eb !important;">
                <?php if($is_online): ?>
                    <span class="online-dot"></span>Online now
                <?php else: ?>
                    <span style="background: #64748b;" class="online-dot"></span>
                    Last seen <?= $other_user['minutes_ago'] < 60 ? $other_user['minutes_ago'] . ' min ago' : floor($other_user['minutes_ago']/60) . ' hours ago' ?>
                <?php endif; ?>
            </small>
        </div>
    </div>
    <div id="typing-indicator" class="typing-indicator" style="display:none;">
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
        <span class="typing-dot"></span>
        <span>Typing...</span>
    </div>
</div>
            
            <!-- Messages Area -->
            <div class="chat-messages" id="chat">
                <div id="loading">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Loading messages...
                </div>
            </div>
            
            <!-- Input Form -->
           <!-- Input Form - WhatsApp Style -->
<form id="message-form" class="message-input-wrapper">
    <input type="hidden" id="other_user_id" value="<?= $other_id ?>">
    
    <div class="message-input-container">
        <button type="button" class="emoji-btn" id="emojiBtn">
            <i class="far fa-smile"></i>
        </button>
        
        <input type="text" 
               id="message-input" 
               class="message-input-field" 
               placeholder="Type a message" 
               required 
               autocomplete="off" 
               autofocus>
        
        <button type="submit" class="send-btn" id="sendBtn">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</form>
        </div>
    </div>

    <!-- Hidden Audio for Notifications -->
    <audio id="messageSound" preload="auto" style="display:none;">
        <source src="notification.mp3" type="audio/mpeg">
    </audio>

   <script>
    // SIMPLE LIVE CHAT SYSTEM FOR INFINITYFREE
class SimpleChat {
    constructor(otherUserId) {
        this.otherUserId = otherUserId;
        this.lastMessageId = 0;
        this.typingTimeout = null;
        this.isTyping = false;
        this.userCoins = 0;
        this.coinWarningShown = false; // Track if warning has been shown
        this.init();
    }
    
    init() {
        console.log('Chat initialized for user:', this.otherUserId);
        this.setupEventListeners();
        this.loadMessages();
        this.loadUserCoins();
        this.startLiveUpdates();
    }
    
    setupEventListeners() {
        // Send message
        document.getElementById('message-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendMessage();
        });
        
        // Typing detection
        const messageInput = document.getElementById('message-input');
        messageInput.addEventListener('input', () => {
            this.sendTypingStatus(true);
            clearTimeout(this.typingTimeout);
            this.typingTimeout = setTimeout(() => {
                this.sendTypingStatus(false);
            }, 1000);
        });
        
        // Enter key to send (without shift)
        messageInput.addEventListener('keydown', (e) => {
            if(e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        
        // Auto-focus on chat click
        document.getElementById('chat').addEventListener('click', () => {
            messageInput.focus();
        });
        
        // Check coins on input but DON'T show modal automatically
        messageInput.addEventListener('focus', () => {
            this.checkCoinsStatus();
        });
    }
    
    loadUserCoins() {
        fetch('get_user_coins.php')
            .then(r => r.json())
            .then(data => {
                this.userCoins = data.coins;
                this.updateCoinDisplay(this.userCoins);
                this.coinWarningShown = false; // Reset warning flag when coins update
            })
            .catch(err => console.log('Coin load error:', err));
    }
    
    checkCoinsStatus() {
        const input = document.getElementById('message-input');
        
        // Only update placeholder based on coins, DON'T show modal
        if(this.userCoins < 20) {
            if(this.userCoins <= 0) {
                input.placeholder = "⚠️ You have 0 coins! Complete tasks to earn coins.";
            } else if(this.userCoins <= 10) {
                input.placeholder = `⚠️ Low coins: ${this.userCoins} left. Need 20 coins to message.`;
            } else {
                input.placeholder = `You have ${this.userCoins} coins. Need 20 coins to message.`;
            }
            input.style.borderColor = '#f59e0b';
            
            // Remove any existing warning message
            if(document.getElementById('coin-warning')) {
                document.getElementById('coin-warning').remove();
            }
        } else {
            input.placeholder = "Type your message...";
            input.style.borderColor = '';
            
            // Remove warning if exists
            const warning = document.getElementById('coin-warning');
            if(warning) {
                warning.remove();
            }
        }
    }
    
    sendMessage() {
        const input = document.getElementById('message-input');
        const message = input.value.trim();
        
        if(!message) return;
        
        // Check if user has enough coins - ONLY show modal when actually trying to send
        if(this.userCoins < 20) {
            this.showCoinModal(`You need 20 coins to send a message. You currently have ${this.userCoins} coins.`);
            return; // Don't send message
        }
        
        // Show message immediately
        this.showMessage(message, true);
        input.value = '';
        this.sendTypingStatus(false);
        
        // Send to server
        fetch('private_send.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user=${this.otherUserId}&msg=${encodeURIComponent(message)}`
        })
        .then(r => {
            if(r.status === 402) {
                return r.json().then(data => {
                    if(data.error === 'insufficient_coins') {
                        this.showCoinModal('Not enough coins! Complete tasks to earn more coins.');
                        throw new Error('insufficient_coins');
                    }
                    throw new Error('Payment required');
                });
            }
            return r.json();
        })
        .then(data => {
            if(data.success) {
                console.log('Message sent successfully');
                this.updateUserStatus();
                this.lastMessageId = Math.max(this.lastMessageId, data.id);
                
                // Update coins
                this.userCoins = data.remaining_coins;
                this.updateCoinDisplay(this.userCoins);
                this.coinWarningShown = false; // Reset warning flag
                
                // Check if user needs to be redirected to tasks - but DON'T show modal automatically
                if(data.needs_tasks && this.userCoins < 20) {
                    // Just update placeholder, don't show modal
                    this.checkCoinsStatus();
                }
                
                // Auto-complete first message task
                if(this.lastMessageId === data.id) {
                    this.completeTask('first_message');
                }
            }
        })
        .catch(err => {
            console.error('Send failed:', err);
            if(err.message !== 'insufficient_coins') {
                // Show error on the message
                const lastMsg = document.querySelector('.message.sent:last-child');
                if(lastMsg) {
                    const checkMark = lastMsg.querySelector('.fa-check');
                    if(checkMark) {
                        checkMark.className = 'fas fa-exclamation-triangle text-danger';
                    }
                }
            }
        });
    }
    
    completeTask(taskType) {
        fetch('tasks_complete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `task_type=${taskType}`
        })
        .then(r => r.json())
        .then(data => {
            if(data.success) {
                console.log('Task completed! +' + data.coins_added + ' coins');
                this.userCoins += data.coins_added;
                this.updateCoinDisplay(this.userCoins);
                this.coinWarningShown = false;
                
                // Show notification
                this.showNotification(`Task completed! +${data.coins_added} coins earned!`);
            }
        })
        .catch(err => console.log('Task completion error:', err));
    }
    
    showMessage(text, isSent = false, isRead = false) {
        const chatDiv = document.getElementById('chat');
        const loadingDiv = document.getElementById('loading');
        
        // Remove loading indicator
        if(loadingDiv && chatDiv.children.length > 1) {
            loadingDiv.remove();
        }
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${isSent ? 'sent' : 'received'}`;
        
        let checkMark = '';
        if(isSent) {
            checkMark = isRead ? 
                '<i class="fas fa-check-double text-info" title="Read"></i>' : 
                '<i class="fas fa-check text-muted" title="Sent"></i>';
        }
        
        messageDiv.innerHTML = `
            <p class="mb-1">${this.escapeHtml(text)}</p>
            <div class="message-time">
                ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                ${checkMark}
                ${isSent ? `<span class="coin-cost ms-2" title="Cost: 20 coins"><i class="fas fa-coins text-warning"></i> 20</span>` : ''}
            </div>
        `;
        
        chatDiv.appendChild(messageDiv);
        this.scrollToBottom();
    }
    
    loadMessages() {
        fetch(`private_fetch.php?user=${this.otherUserId}`)
            .then(r => r.text())
            .then(html => {
                const chatDiv = document.getElementById('chat');
                const loadingDiv = document.getElementById('loading');
                
                if(html.trim() && !html.includes('No messages')) {
                    chatDiv.innerHTML = html;
                    this.scrollToBottom();
                    
                    // Get last message ID
                    const lastMsg = document.querySelector('.message:last-child');
                    if(lastMsg && lastMsg.dataset.id) {
                        this.lastMessageId = parseInt(lastMsg.dataset.id);
                    }
                } else if(loadingDiv) {
                    loadingDiv.innerHTML = '<p class="text-muted">No messages yet. Start the conversation!</p>';
                }
                
                this.markMessagesAsRead();
            })
            .catch(err => {
                console.error('Load error:', err);
                document.getElementById('loading').innerHTML = 
                    '<p class="text-danger">Failed to load messages. Please refresh.</p>';
            });
    }
    
    startLiveUpdates() {
        // Check for new messages every 2 seconds
        setInterval(() => {
            this.checkForNewMessages();
        }, 2000);
        
        // Check for typing status every second
        setInterval(() => {
            this.checkTypingStatus();
        }, 1000);
        
        // Update user status every 30 seconds
        setInterval(() => {
            this.updateUserStatus();
        }, 30000);
        
        // Check coins status periodically - UPDATE PLACEHOLDER ONLY, NO MODAL
        setInterval(() => {
            this.loadUserCoins();
        }, 10000);
    }
    
    checkForNewMessages() {
        fetch(`check_new_simple.php?user=${this.otherUserId}&last_id=${this.lastMessageId}`)
            .then(r => r.json())
            .then(data => {
                if(data.has_new) {
                    this.loadMessages();
                    if(data.play_sound) {
                        this.playSound();
                    }
                }
                if(data.last_id) {
                    this.lastMessageId = data.last_id;
                }
            })
            .catch(err => console.log('Update check error:', err));
    }
    
    checkTypingStatus() {
        fetch(`check_typing.php?user=${this.otherUserId}`)
            .then(r => r.json())
            .then(data => {
                const typingIndicator = document.getElementById('typing-indicator');
                if(data.is_typing) {
                    typingIndicator.style.display = 'flex';
                } else {
                    typingIndicator.style.display = 'none';
                }
            })
            .catch(err => console.log('Typing check error:', err));
    }
    
    sendTypingStatus(isTyping) {
        if(this.isTyping === isTyping) return;
        
        this.isTyping = isTyping;
        fetch('update_typing.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `other_id=${this.otherUserId}&is_typing=${isTyping ? 1 : 0}`
        })
        .catch(err => console.log('Typing update error:', err));
    }
    
    markMessagesAsRead() {
        fetch(`mark_read.php?user=${this.otherUserId}`)
            .catch(err => console.log('Mark read error:', err));
    }
    
    updateUserStatus() {
        fetch('update_status.php')
            .catch(err => console.log('Status update error:', err));
    }
    
    updateCoinDisplay(coins) {
        // Update coin display in chat header
        let coinDisplay = document.getElementById('coin-display-header');
        if(!coinDisplay) {
            // Create coin display in header
            const header = document.querySelector('.chat-header');
            coinDisplay = document.createElement('div');
            coinDisplay.id = 'coin-display-header';
            coinDisplay.className = 'coin-display-header';
            coinDisplay.style.cssText = `
                position: absolute;
                right: 70px;
                background: rgba(245, 158, 11, 0.1);
                border: 1px solid rgba(245, 158, 11, 0.3);
                border-radius: 20px;
                padding: 4px 12px;
                font-size: 0.9rem;
            `;
            header.appendChild(coinDisplay);
        }
        
        coinDisplay.innerHTML = `
            <i class="fas fa-coins text-warning"></i>
            <span class="ms-1">${coins}</span>
            <small class="ms-2 text-muted">(${Math.floor(coins/20)} msgs)</small>
        `;
        
        // Update input placeholder based on coins - NO MODAL
        this.checkCoinsStatus();
    }
    
    showCoinModal(customMessage = null) {
        // Remove existing modal if any
        const existingModal = document.querySelector('.coin-modal');
        if(existingModal) {
            existingModal.remove();
        }
        
        const modal = document.createElement('div');
        modal.className = 'coin-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        `;
        
        const message = customMessage || `You need 20 coins to send a message. You have ${this.userCoins} coins.`;
        
        modal.innerHTML = `
            <div style="background: #1a1a2e; border-radius: 15px; padding: 30px; max-width: 500px; width: 90%; border: 2px solid #f59e0b;">
                <div class="text-center mb-4">
                    <i class="fas fa-coins fa-3x text-warning mb-3"></i>
                    <h3 class="text-warning">Need More Coins!</h3>
                    <p class="text-light">${message}</p>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <strong>Your Balance:</strong> ${this.userCoins} coins
                        <br>
                        <small>Each message costs 20 coins</small>
                    </div>
                </div>
                
                <div class="mb-4">
                    <h5 class="text-light"><i class="fas fa-bolt text-primary"></i> Quick Tasks (+25 coins each):</h5>
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <a href="profile.php" class="text-decoration-none">
                                <div class="task-quick bg-dark p-2 rounded text-center">
                                    <i class="fas fa-user-edit text-info"></i>
                                    <div class="small">Update Profile</div>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="profile.php" class="text-decoration-none">
                                <div class="task-quick bg-dark p-2 rounded text-center">
                                    <i class="fas fa-file-alt text-success"></i>
                                    <div class="small">Complete Bio</div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="tasks.php" class="btn btn-warning flex-grow-1">
                        <i class="fas fa-tasks"></i> View All Tasks
                    </a>
                    <button class="btn btn-outline-light" onclick="this.closest('.coin-modal').remove(); document.getElementById('message-input').focus();">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close modal when clicking outside
        modal.addEventListener('click', (e) => {
            if(e.target === modal) {
                modal.remove();
            }
        });
        
        // Escape key to close
        document.addEventListener('keydown', function closeModal(e) {
            if(e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', closeModal);
            }
        });
    }
    
    showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `chat-notification ${type}`;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#10b981' : '#f59e0b'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 10000;
            animation: slideIn 0.3s ease, slideOut 0.3s ease 2.7s;
        `;
        
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} me-2"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if(notification.parentNode) {
                notification.remove();
            }
        }, 3000);
    }
    
    playSound() {
        const sound = document.getElementById('messageSound');
        if(sound) {
            sound.currentTime = 0;
            sound.play().catch(e => console.log('Sound play failed:', e));
        }
    }
    
    scrollToBottom() {
        const chat = document.getElementById('chat');
        if(chat) {
            chat.scrollTop = chat.scrollHeight;
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize chat when page loads
document.addEventListener('DOMContentLoaded', function() {
    const otherUserId = <?= $other_id ?>;
    window.simpleChat = new SimpleChat(otherUserId);
    
    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .coin-cost {
            font-size: 0.8rem;
            opacity: 0.7;
        }
        
        .task-quick {
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .task-quick:hover {
            transform: translateY(-2px);
            background: rgba(99, 102, 241, 0.2) !important;
            border-color: #6366f1;
        }
        
        .coin-display-header {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
            100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
        }
        
        /* Warning text color for low coins */
        .text-warning-low {
            color: #f59e0b !important;
        }
    `;
    document.head.appendChild(style);
    
    // Add coin icon to chat header
    const header = document.querySelector('.chat-header');
    if(header) {
        const coinInfo = document.createElement('div');
        coinInfo.className = 'coin-info-tooltip';
        coinInfo.style.cssText = `
            position: relative;
            display: inline-block;
            margin-left: 10px;
        `;
        
        coinInfo.innerHTML = `
            <i class="fas fa-info-circle text-muted" style="cursor: help;"></i>
            <div class="coin-tooltip" style="
                display: none;
                position: absolute;
                bottom: 100%;
                right: 0;
                background: #1a1a2e;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 8px;
                padding: 10px;
                width: 200px;
                z-index: 100;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            ">
                <small class="text-muted">💡 Chat Tips:</small>
                <ul class="mt-2 mb-0 ps-3" style="font-size: 0.8rem;">
                    <li>Each message costs 20 coins</li>
                    <li>Complete tasks to earn coins</li>
                    <li>Daily login bonus: 10 coins</li>
                    <li>Modal only shows when you try to send without enough coins</li>
                </ul>
            </div>
        `;
        
        coinInfo.addEventListener('mouseenter', () => {
            coinInfo.querySelector('.coin-tooltip').style.display = 'block';
        });
        
        coinInfo.addEventListener('mouseleave', () => {
            coinInfo.querySelector('.coin-tooltip').style.display = 'none';
        });
        
        header.appendChild(coinInfo);
    }
    
    // REMOVED: The auto coin reminder that was showing every minute
    // No more automatic popups!
});
</script>
</body>
</html>