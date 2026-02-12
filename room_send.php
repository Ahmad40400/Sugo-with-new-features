<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$room_id = filter_var($_POST['room_id'] ?? 0, FILTER_VALIDATE_INT);
$message = filter_var($_POST['message'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if($room_id && !empty($message)) {
    $stmt = $pdo->prepare("INSERT INTO messages (room_id, user_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$room_id, $_SESSION['user_id'], $message]);
    
    // Update last activity
    $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")
        ->execute([$_SESSION['user_id']]);
}
?>