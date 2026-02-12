<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$other_id) exit;

// Update typing status in database
$stmt = $pdo->prepare("
    INSERT INTO typing_status (user_id, to_user_id, last_typing) 
    VALUES (?, ?, NOW())
    ON DUPLICATE KEY UPDATE last_typing = NOW()
");
$stmt->execute([$_SESSION['user_id'], $other_id]);

// Also update user activity
$pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")
    ->execute([$_SESSION['user_id']]);

echo "OK";
?>