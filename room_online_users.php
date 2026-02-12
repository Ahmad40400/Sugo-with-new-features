<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$room_id = filter_var($_GET['room_id'] ?? 0, FILTER_VALIDATE_INT);
if(!$room_id) exit;

// Get users active in this room (based on recent messages)
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.username, u.avatar, 
           TIMESTAMPDIFF(SECOND, u.last_active, NOW()) as seconds_ago 
    FROM users u 
    JOIN messages m ON u.id = m.user_id 
    WHERE m.room_id = ? 
    AND u.last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ORDER BY u.last_active DESC
");
$stmt->execute([$room_id]);
$users = $stmt->fetchAll();

foreach($users as $user) {
    $is_online = $user['seconds_ago'] < 300;
    echo '<div class="online-user">';
    echo '<img src="uploads/' . htmlspecialchars($user['avatar']) . '" class="online-user-avatar" alt="Avatar">';
    echo '<div class="flex-grow-1">';
    echo '<div class="d-flex align-items-center">';
    if($is_online) {
        echo '<span class="online-badge"></span>';
    }
    echo '<span class="text-white">' . htmlspecialchars($user['username']) . '</span>';
    echo '</div>';
    echo '<small class="text-muted">';
    if($is_online) {
        echo 'Online';
    } else {
        echo floor($user['seconds_ago'] / 60) . ' min ago';
    }
    echo '</small>';
    echo '</div>';
    echo '<a href="private_chat.php?user=' . $user['id'] . '" class="btn btn-primary btn-sm">';
    echo '<i class="fas fa-comment"></i>';
    echo '</a>';
    echo '</div>';
}
?>