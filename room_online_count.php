<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$room_id = filter_var($_GET['room_id'] ?? 0, FILTER_VALIDATE_INT);
if(!$room_id) exit;

// Count online users in room
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT u.id) as count 
    FROM users u 
    JOIN messages m ON u.id = m.user_id 
    WHERE m.room_id = ? 
    AND u.last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$stmt->execute([$room_id]);
$result = $stmt->fetch();

echo $result['count'] . ' users online';
?>