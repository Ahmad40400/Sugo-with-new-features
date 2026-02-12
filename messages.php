
<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get all conversations for the current user
// Get all conversations for the current user
$conversations = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.avatar,
        MAX(pm2.created_at) as last_message_time,
        (
            SELECT message 
            FROM private_messages 
            WHERE (sender_id = u.id AND receiver_id = ?) 
               OR (sender_id = ? AND receiver_id = u.id)
            ORDER BY created_at DESC 
            LIMIT 1
        ) as last_message,
        (
            SELECT COUNT(*) 
            FROM private_messages 
            WHERE receiver_id = ? 
            AND sender_id = u.id 
            AND is_read = FALSE
        ) as unread_count
    FROM users u
    INNER JOIN private_messages pm2 ON (
        (pm2.sender_id = u.id AND pm2.receiver_id = ?) 
        OR (pm2.sender_id = ? AND pm2.receiver_id = u.id)
    )
    WHERE u.id != ?
    GROUP BY u.id, u.username, u.avatar
    ORDER BY last_message_time DESC
");

$conversations->execute([
    $_SESSION['user_id'], 
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    $_SESSION['user_id']
]);

$conversations = $conversations->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - SUGO Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f0f23; color: #fff; }
        .conversation-card { 
            background: #1a1a2e; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 10px;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }
        .conversation-card:hover { 
            background: #252547; 
            border-color: #4cc9f0;
        }
        .conversation-card.unread { 
            border-left: 4px solid #6366f1; 
            background: rgba(99, 102, 241, 0.1);
        }
        .conversation-avatar { 
            width: 50px; 
            height: 50px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid #4cc9f0; 
        }
        .unread-badge {
            background: #6366f1;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .last-message {
            color: #aaa;
            font-size: 0.9rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 200px;
        }
        .message-time {
            color: #666;
            font-size: 0.8rem;
        }
        .message-status {
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .sent { color: #4cc9f0; }
        .read { color: #10b981; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <nav class="mb-4">
            <a href="home.php" class="btn btn-outline-light"><i class="fas fa-home"></i> Home</a>
            <a href="discover.php" class="btn btn-outline-info"><i class="fas fa-users"></i> Discover</a>
            <a href="profile.php" class="btn btn-outline-warning"><i class="fas fa-user"></i> Profile</a>
        </nav>
        <!-- At the top of messages.php, after nav -->
<?php
// Check coins
$coins_stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
$coins_stmt->execute([$_SESSION['user_id']]);
$user_coins = $coins_stmt->fetch()['coins'];

if($user_coins < 40): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 
    You have only <?= $user_coins ?> coins left (<?= floor($user_coins / 20) ?> messages). 
    <a href="tasks.php" class="alert-link">Earn more coins</a>
</div>
<?php endif; ?>
        
        <h2><i class="fas fa-comments"></i> Messages</h2>
        <p class="text-muted mb-4" style="color: #e5e7eb !important;">Your conversations</p>
        
        <div class="row">
            <div class="col-md-8">
                <?php if(empty($conversations)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3" style="color: #e5e7eb !important;"></i>
                        <h4>No messages yet</h4>
                        <p>Start a conversation with someone!</p>
                        <a href="discover.php" class="btn btn-primary">
                            <i class="fas fa-users"></i> Discover People
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach($conversations as $conv): 
                        $hasUnread = $conv['unread_count'] > 0;
                    ?>
                    <div class="conversation-card <?= $hasUnread ? 'unread' : '' ?>" 
                         onclick="window.location.href='private_chat.php?user=<?= $conv['id'] ?>'">
                        <div class="d-flex align-items-center">
                            <img src="uploads/<?= htmlspecialchars($conv['avatar'] ?? 'default.jpg') ?>" 
                                 class="conversation-avatar me-3" alt="Avatar">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between">
                                    <h5 class="mb-1"><?= htmlspecialchars($conv['username']) ?></h5>
                                    <?php if($conv['last_message_time']): ?>
                                        <span class="message-time">
                                            <?= date('H:i', strtotime($conv['last_message_time'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if($conv['last_message']): ?>
                                    <p class="last-message mb-0">
                                        <?= htmlspecialchars(substr($conv['last_message'], 0, 50)) ?>
                                        <?= strlen($conv['last_message']) > 50 ? '...' : '' ?>
                                    </p>
                                <?php else: ?>
                                    <p class="last-message mb-0 text-muted" >No messages yet</p>
                                <?php endif; ?>
                            </div>
                            <?php if($hasUnread): ?>
                                <div class="unread-badge">
                                    <?= $conv['unread_count'] ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-dark border-secondary mb-3" style="color: #e5e7eb !important;">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle"></i> Quick Tips</h5>
                        <ul class="text-muted" style="color: #e5e7eb !important;">
                            <li>Click on any conversation to continue chatting</li>
                            <li>Unread messages are marked with a blue badge</li>
                            <li>Blue tick means your message was read</li>
                            <li>Single gray tick means sent</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>