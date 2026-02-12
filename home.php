<?php 
require 'config.php';
if(!isset($_SESSION['user_id'])) header("Location: login.php");

// Get user info
$user_stmt = $pdo->prepare("SELECT username, avatar, coins FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$current_user = $user_stmt->fetch();

// Get rooms - FIXED: Use prepare() or query() correctly
// Get rooms - Use query() for simple SELECT without parameters
try {
    $stmt = $pdo->query("SELECT * FROM rooms");
    $rooms = $stmt->fetchAll();
} catch(PDOException $e) {
    $rooms = [];
    error_log("Room fetch error: " . $e->getMessage());
}

// Get online friends
$online_stmt = $pdo->prepare("
    SELECT id, username, avatar, 
           TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago 
    FROM users 
    WHERE last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND id != ?
    ORDER BY last_active DESC 
    LIMIT 10
");
$online_stmt->execute([$_SESSION['user_id']]);
$online_friends = $online_stmt->fetchAll();
?>
<!-- Rest of the HTML remains the same -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SUGO Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --radius: 12px;
        }
        
        body {
            background: #0f172a;
            color: var(--light);
            font-family: 'Poppins', sans-serif;
        }
        
        .sidebar {
            background: #1e293b;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid var(--primary);
            margin-right: 1rem;
        }
        
        .user-info h6 {
            margin: 0;
            font-weight: 600;
        }
        
        .user-info small {
            color: var(--gray);
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: #94a3b8;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            background: rgba(99, 102, 241, 0.1);
            color: white;
        }
        
        .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-link i {
            width: 24px;
            margin-right: 12px;
            text-align: center;
        }
        
        .card {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.25rem;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .online-badge {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .room-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), transparent);
            border: 1px solid rgba(99, 102, 241, 0.2);
            padding: 1.5rem;
            border-radius: var(--radius);
            text-align: center;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .room-card:hover {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), transparent);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
        }
        
        .room-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .stats-card {
            text-align: center;
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }
        
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            align-items: center;
            justify-content: center;
        }
        
        @media (max-width: 992px) {
            .mobile-menu-btn {
                display: flex;
            }
        }
        
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .online-user {
            display: flex;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .online-user:hover {
            background: rgba(99, 102, 241, 0.1);
        }
        
        .online-user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 12px;
        }
        
        .coins-badge {
            background: linear-gradient(45deg, #f59e0b, #f97316);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        h5{
color: #f3f4f6;  }

    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">SUGO</div>
        
        <!-- User Profile -->
        <div class="user-profile">
            <img src="uploads/<?= htmlspecialchars($current_user['avatar']) ?>" class="user-avatar" alt="Avatar">
            <div class="user-info">
                <h6><?= htmlspecialchars($current_user['username']) ?></h6>
                <small><span class="online-badge"></span> Online</small>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="home.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="discover.php" class="nav-link">
                    <i class="fas fa-users"></i> Discover
                </a>
            </li>
            <li class="nav-item">
                <a href="profile.php" class="nav-link">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
           <li class="nav-item">
    <a href="messages.php" class="nav-link">
        <i class="fas fa-comments"></i> Messages
        <?php 
        // Count unread messages
        $unread_stmt = $pdo->prepare("
            SELECT COUNT(*) as unread 
            FROM private_messages 
            WHERE receiver_id = ? AND is_read = FALSE
        ");
        $unread_stmt->execute([$_SESSION['user_id']]);
        $unread = $unread_stmt->fetch()['unread'];
        
        if($unread > 0): ?>
            <span class="badge bg-danger ms-2"><?= $unread ?></span>
        <?php endif; ?>
    </a>
</li>
            <li class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fas fa-gift"></i> Gifts Shop
                </a>
            </li>
            <li class="nav-item">
                <a href="logout.php" class="nav-link text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
        
        <!-- Coins Balance -->
       <!-- Coins Balance -->
<div class="mt-4 text-center">
    <div class="coins-badge coin-display">
        <i class="fas fa-coins me-2"></i>
        <?= $current_user['coins'] ?> Coins
    </div>
</div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2>Welcome back, <?= htmlspecialchars($current_user['username']) ?>! 👋</h2>
            <p class="mb-0">Ready to connect with amazing people around the world?</p>
        </div>
        
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="card stats-card">
                    <div class="stat-number"><?= count($rooms) ?></div>
                    <div class="stat-label">Active Rooms</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stats-card">
                    <div class="stat-number"><?= count($online_friends) ?></div>
                    <div class="stat-label">Online Now</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stats-card">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Live Support</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stats-card">
                    <div class="stat-number">100%</div>
                    <div class="stat-label">Secure</div>
                </div>
            </div>
        </div>
        
        <!-- Chat Rooms -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-door-open me-2"></i>Join Chat Rooms</h5>
                        <a href="discover.php" class="btn btn-primary btn-sm">Discover More</a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach($rooms as $room): ?>
                            <div class="col-md-4 mb-3">
                                <a href="room.php?room_id=<?= $room['id'] ?>" class="text-decoration-none">
                                    <div class="room-card">
                                        <div class="room-icon">
                                            <i class="fas fa-comments"></i>
                                        </div>
                                        <h5><?= htmlspecialchars($room['name']) ?></h5>
                                       <p class="text-muted mb-0" style="color: #e5e7eb !important;">Join the conversation</p>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Online Friends -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Online Now</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach($online_friends as $friend): 
                            $is_online = $friend['minutes_ago'] < 5;
                        ?>
                        <div class="online-user">
                            <img src="uploads/<?= htmlspecialchars($friend['avatar']) ?>" class="online-user-avatar" alt="Avatar">
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?= htmlspecialchars($friend['username']) ?></h6>
                                <small class="<?= $is_online ? 'text-success' : 'text-muted' ?>">
                                    <span class="online-badge" style="background: <?= $is_online ? '#10b981' : '#64748b' ?>"></span>
                                    <?= $is_online ? 'Online' : 'Offline' ?>
                                </small>
                            </div>
                            <a href="private_chat.php?user=<?= $friend['id'] ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-comment"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if(count($online_friends) === 0): ?>
                        <div class="text-center text-muted py-3">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <p>No users online at the moment</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <a href="discover.php" class="btn btn-outline-primary w-100 py-3">
                                    <i class="fas fa-users fa-2x mb-2"></i><br>
                                    Discover People
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="profile.php" class="btn btn-outline-success w-100 py-3">
                                    <i class="fas fa-user-edit fa-2x mb-2"></i><br>
                                    Edit Profile
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-warning w-100 py-3">
                                    <i class="fas fa-gift fa-2x mb-2"></i><br>
                                    Send Gifts
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="#" class="btn btn-outline-info w-100 py-3">
                                    <i class="fas fa-cog fa-2x mb-2"></i><br>
                                    Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

   <script>
    // Mobile menu toggle
    document.getElementById('mobileMenuBtn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });
    
    // Close menu when clicking outside on mobile
    document.getElementById('mainContent').addEventListener('click', function() {
        if(window.innerWidth <= 992) {
            document.getElementById('sidebar').classList.remove('active');
        }
    });
    
    // Auto-update online status
    setInterval(() => {
        fetch('update_status.php').catch(() => {});
    }, 30000);
    
    // ========== LIVE MESSAGE NOTIFICATION SYSTEM ==========
    class HomeNotificationSystem {
        constructor() {
            this.lastCheckTime = Math.floor(Date.now() / 1000);
            this.notificationSound = new Audio('notification.mp3');
            this.notificationSound.preload = 'auto';
            this.init();
        }
        
        init() {
            console.log('Notification system initialized');
            this.setupNotificationStyle();
            this.startLiveUpdates();
            this.updateUnreadCount();
        }
        
        setupNotificationStyle() {
            // Create notification container
            const notificationContainer = document.createElement('div');
            notificationContainer.id = 'notification-container';
            notificationContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
                width: 90%;
                display: flex;
                flex-direction: column;
                gap: 10px;
            `;
            document.body.appendChild(notificationContainer);
            
            // Add CSS for notifications
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                
                .live-notification {
                    background: linear-gradient(135deg, #6366f1, #4f46e5);
                    color: white;
                    border-radius: 12px;
                    padding: 15px;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                    border-left: 5px solid #10b981;
                    animation: slideInRight 0.3s ease;
                    backdrop-filter: blur(10px);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                }
                
                .live-notification.slide-out {
                    animation: slideOutRight 0.3s ease;
                }
                
                .notification-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    margin-bottom: 8px;
                }
                
                .notification-title {
                    font-weight: 600;
                    font-size: 1rem;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                
                .notification-close {
                    background: none;
                    border: none;
                    color: white;
                    opacity: 0.7;
                    cursor: pointer;
                    padding: 2px;
                    border-radius: 4px;
                    transition: all 0.3s ease;
                }
                
                .notification-close:hover {
                    opacity: 1;
                    background: rgba(255, 255, 255, 0.1);
                }
                
                .notification-body {
                    font-size: 0.9rem;
                    line-height: 1.4;
                    opacity: 0.9;
                }
                
                .notification-badge {
                    background: #ef4444;
                    color: white;
                    font-size: 0.8rem;
                    font-weight: bold;
                    padding: 2px 8px;
                    border-radius: 12px;
                    animation: pulse 2s infinite;
                }
                
                .notification-link {
                    color: #a5b4fc;
                    text-decoration: none;
                    font-weight: 500;
                    transition: color 0.3s ease;
                }
                
                .notification-link:hover {
                    color: white;
                    text-decoration: underline;
                }
                
                .sender-list {
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 5px;
                    margin-top: 8px;
                }
                
                .sender-badge {
                    background: rgba(255, 255, 255, 0.15);
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 0.8rem;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                }
                
                .nav-link.active {
                    animation: pulse 1s ease;
                }
                
                /* Update messages nav link to show unread count */
                .messages-nav-link {
                    position: relative;
                }
                
                .unread-indicator {
                    position: absolute;
                    top: 5px;
                    right: 5px;
                    width: 8px;
                    height: 8px;
                    background: #ef4444;
                    border-radius: 50%;
                    animation: pulse 1s infinite;
                }
                
                /* Online friends update animation */
                .online-user.new-message {
                    animation: pulse 0.5s ease;
                    border: 1px solid #6366f1;
                }
                
                /* Message icon animation */
                @keyframes messageIconPulse {
                    0% { transform: rotate(0deg); }
                    25% { transform: rotate(-10deg); }
                    50% { transform: rotate(10deg); }
                    75% { transform: rotate(-5deg); }
                    100% { transform: rotate(0deg); }
                }
                
                .message-icon-pulse {
                    animation: messageIconPulse 0.5s ease;
                }
            `;
            document.head.appendChild(style);
        }
        
        startLiveUpdates() {
            // Check for new messages every 3 seconds
            setInterval(() => {
                this.checkForNewMessages();
            }, 3000);
            
            // Update unread count every 10 seconds
            setInterval(() => {
                this.updateUnreadCount();
            }, 10000);
        }
        
        checkForNewMessages() {
            fetch(`check_new_messages_home.php?last_check=${this.lastCheckTime}`)
                .then(r => r.json())
                .then(data => {
                    if(data.has_new && data.count > 0) {
                        this.showNewMessageNotification(data);
                        this.playNotificationSound();
                        this.updateMessagesIcon(data.count);
                        this.updateOnlineFriends(data.senders);
                    }
                    
                    // Update last check time
                    this.lastCheckTime = data.timestamp;
                })
                .catch(err => console.log('Message check error:', err));
        }
        
        showNewMessageNotification(data) {
            const container = document.getElementById('notification-container');
            const notificationId = 'notification-' + Date.now();
            
            // Limit to 3 notifications max
            const notifications = container.querySelectorAll('.live-notification');
            if(notifications.length >= 3) {
                notifications[0].remove();
            }
            
            // Get sender names
            const senderNames = data.senders.map(s => s.username).slice(0, 3);
            const senderCount = data.senders.length;
            const moreCount = senderCount > 3 ? senderCount - 3 : 0;
            
            const notification = document.createElement('div');
            notification.id = notificationId;
            notification.className = 'live-notification';
            notification.style.animationDelay = '0.1s';
            
            notification.innerHTML = `
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-comment-dots"></i>
                        New Message${data.count > 1 ? 's' : ''}
                        <span class="notification-badge">${data.count}</span>
                    </div>
                    <button class="notification-close" onclick="document.getElementById('${notificationId}').classList.add('slide-out'); setTimeout(() => {if(document.getElementById('${notificationId}')) document.getElementById('${notificationId}').remove();}, 300);">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="notification-body">
                    ${data.count > 1 ? 
                        `You have ${data.count} new messages from:` : 
                        `You have a new message from:`}
                    
                    <div class="sender-list">
                        ${senderNames.map(name => `
                            <span class="sender-badge">
                                <i class="fas fa-user"></i> ${this.escapeHtml(name)}
                            </span>
                        `).join('')}
                        ${moreCount > 0 ? 
                            `<span class="sender-badge">+${moreCount} more</span>` : 
                            ''}
                    </div>
                    
                    <div class="mt-3">
                        <a href="messages.php" class="notification-link">
                            <i class="fas fa-external-link-alt me-1"></i> View Messages
                        </a>
                        <span class="mx-2 text-muted">|</span>
                        <a href="#" class="notification-link" onclick="markAllAsRead()">
                            <i class="fas fa-check-double me-1"></i> Mark as Read
                        </a>
                    </div>
                </div>
            `;
            
            container.appendChild(notification);
            
            // Auto-remove after 8 seconds
            setTimeout(() => {
                if(document.getElementById(notificationId)) {
                    document.getElementById(notificationId).classList.add('slide-out');
                    setTimeout(() => {
                        if(document.getElementById(notificationId)) {
                            document.getElementById(notificationId).remove();
                        }
                    }, 300);
                }
            }, 8000);
            
            // Click to open messages
            notification.addEventListener('click', (e) => {
                if(!e.target.closest('.notification-close')) {
                    window.location.href = 'messages.php';
                }
            });
        }
        
        playNotificationSound() {
            try {
                this.notificationSound.currentTime = 0;
                this.notificationSound.play();
            } catch (e) {
                console.log('Sound play failed:', e);
            }
        }
        
        updateMessagesIcon(count) {
            // Update nav link badge
            const messagesLink = document.querySelector('a[href="messages.php"]');
            if(messagesLink) {
                let badge = messagesLink.querySelector('.badge');
                if(!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge bg-danger ms-2';
                    messagesLink.appendChild(badge);
                }
                badge.textContent = count;
                badge.style.animation = 'pulse 1s ease';
                
                // Add pulse animation to icon
                const icon = messagesLink.querySelector('i');
                if(icon) {
                    icon.classList.add('message-icon-pulse');
                    setTimeout(() => {
                        icon.classList.remove('message-icon-pulse');
                    }, 500);
                }
            }
        }
        
        updateOnlineFriends(senders) {
            // Highlight online friends who sent messages
            senders.forEach(sender => {
                const friendElement = document.querySelector(`.online-user a[href*="user=${sender.id}"]`);
                if(friendElement) {
                    const userCard = friendElement.closest('.online-user');
                    if(userCard) {
                        userCard.classList.add('new-message');
                        setTimeout(() => {
                            userCard.classList.remove('new-message');
                        }, 3000);
                    }
                }
            });
        }
        
        updateUnreadCount() {
            fetch('get_unread_count.php')
                .then(r => r.json())
                .then(data => {
                    if(data.count > 0) {
                        this.updateMessagesIcon(data.count);
                    }
                })
                .catch(err => console.log('Unread count error:', err));
        }
        
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }
    
    // Initialize notification system
    document.addEventListener('DOMContentLoaded', function() {
        window.notificationSystem = new HomeNotificationSystem();
        
        // Add auto-refresh to online users section every 30 seconds
        setInterval(() => {
            refreshOnlineUsers();
        }, 30000);
    });
    
    // Function to refresh online users
    function refreshOnlineUsers() {
        fetch('get_online_users.php')
            .then(r => r.text())
            .then(html => {
                const onlineUsersContainer = document.querySelector('.card-body .online-user:first-child')?.closest('.card-body');
                if(onlineUsersContainer && html) {
                    onlineUsersContainer.innerHTML = html;
                }
            })
            .catch(err => console.log('Online users refresh error:', err));
    }
    
    // Function to mark all as read
    function markAllAsRead() {
        fetch('mark_all_read.php')
            .then(() => {
                // Update UI
                const messagesLink = document.querySelector('a[href="messages.php"]');
                if(messagesLink) {
                    const badge = messagesLink.querySelector('.badge');
                    if(badge) {
                        badge.remove();
                    }
                }
                
                // Show success notification
                showSuccessNotification('All messages marked as read!');
            })
            .catch(err => console.log('Mark all read error:', err));
    }
    
    // Function to show success notification
    function showSuccessNotification(message) {
        const container = document.getElementById('notification-container');
        const notificationId = 'success-' + Date.now();
        
        const notification = document.createElement('div');
        notification.id = notificationId;
        notification.className = 'live-notification';
        notification.style.background = 'linear-gradient(135deg, #10b981, #059669)';
        
        notification.innerHTML = `
            <div class="notification-header">
                <div class="notification-title">
                    <i class="fas fa-check-circle"></i>
                    Success!
                </div>
                <button class="notification-close" onclick="this.parentElement.parentElement.classList.add('slide-out'); setTimeout(() => {if(this.parentElement.parentElement.parentElement) this.parentElement.parentElement.remove();}, 300);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="notification-body">
                ${message}
            </div>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            if(document.getElementById(notificationId)) {
                document.getElementById(notificationId).classList.add('slide-out');
                setTimeout(() => {
                    if(document.getElementById(notificationId)) {
                        document.getElementById(notificationId).remove();
                    }
                }, 300);
            }
        }, 3000);
    }
    
    // Update user coins display every 30 seconds
    setInterval(() => {
        updateCoinsDisplay();
    }, 30000);
    
    function updateCoinsDisplay() {
        fetch('get_user_coins.php')
            .then(r => r.json())
            .then(data => {
                const coinDisplays = document.querySelectorAll('.coin-display, .coins-badge');
                coinDisplays.forEach(display => {
                    const currentText = display.textContent.trim();
                    const newText = currentText.replace(/\d+/, data.coins);
                    display.textContent = newText;
                });
            })
            .catch(err => console.log('Coin update error:', err));
    }
</script>
</body>
</html>