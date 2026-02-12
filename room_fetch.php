<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$room_id = filter_var($_GET['room_id'] ?? 0, FILTER_VALIDATE_INT);
if(!$room_id) exit;

// Update user's last activity
$pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")
    ->execute([$_SESSION['user_id']]);

// Fetch messages with user info
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.avatar, u.last_active 
    FROM messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.room_id = ? 
    ORDER BY m.created_at DESC 
    LIMIT 50
");
$stmt->execute([$room_id]);
$messages = array_reverse($stmt->fetchAll());

foreach($messages as $msg) {
    $is_sent = $msg['user_id'] == $_SESSION['user_id'];
    $is_online = (time() - strtotime($msg['last_active'])) < 300;
    $time = date('H:i', strtotime($msg['created_at']));
    
    echo '<div class="message ' . ($is_sent ? 'sent' : '') . '">';
    if(!$is_sent) {
        echo '<img src="uploads/' . htmlspecialchars($msg['avatar']) . '" class="message-avatar" alt="Avatar">';
    }
    echo '<div class="message-content">';
    echo '<div class="message-header">';
    if(!$is_sent) {
        echo '<span class="message-username">' . htmlspecialchars($msg['username']) . '</span>';
        if($is_online) {
            echo '<span class="online-badge"></span>';
        }
    }
    echo '<span class="message-time">' . $time . '</span>';
    echo '</div>';
    echo '<div class="message-bubble">' . htmlspecialchars($msg['message']) . '</div>';
    echo '</div>';
    if($is_sent) {
        echo '<img src="uploads/' . htmlspecialchars($msg['avatar']) . '" class="message-avatar" alt="Avatar">';
    }
    echo '</div>';
}
?>