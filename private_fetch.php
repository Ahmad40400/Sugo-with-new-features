<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$other_id) exit;

// Update user's last activity
$pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")
    ->execute([$_SESSION['user_id']]);

// Fetch messages
$stmt = $pdo->prepare("
    SELECT pm.*, u.username, u.avatar 
    FROM private_messages pm
    JOIN users u ON pm.sender_id = u.id
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?)
    ORDER BY pm.created_at ASC
");
$stmt->execute([$_SESSION['user_id'], $other_id, $other_id, $_SESSION['user_id']]);
$messages = $stmt->fetchAll();

if(empty($messages)) {
    echo '<div class="text-center text-muted py-4">';
    echo '<i class="fas fa-comments fa-2x mb-3"></i>';
    echo '<p>No messages yet. Start the conversation!</p>';
    echo '</div>';
    exit;
}

foreach($messages as $m) {
    $is_sent = $m['sender_id'] == $_SESSION['user_id'];
    $time = date('H:i', strtotime($m['created_at']));
    
    echo '<div class="message ' . ($is_sent ? 'sent' : 'received') . '" data-id="' . $m['id'] . '">';
    
    if(!$is_sent) {
        echo '<div class="d-flex align-items-start">';
        echo '<img src="uploads/' . htmlspecialchars($m['avatar'] ?? 'default.jpg') . '" class="message-avatar me-2" alt="Avatar">';
        echo '<div>';
    }
    
    echo '<p class="mb-1">' . nl2br(htmlspecialchars($m['message'])) . '</p>';
    
    echo '<div class="message-time">';
    echo $time;
    if($is_sent) {
        echo $m['is_read'] ? 
            ' <i class="fas fa-check-double text-info" title="Read"></i>' : 
            ' <i class="fas fa-check text-muted" title="Sent"></i>';
    }
    echo '</div>';
    
    if(!$is_sent) {
        echo '</div></div>';
    }
    echo '</div>';
}
?>