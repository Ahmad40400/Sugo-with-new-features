<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$other_id) exit;

// Mark all messages from this user as read
$pdo->prepare("
    UPDATE private_messages 
    SET is_read = TRUE 
    WHERE sender_id = ? 
    AND receiver_id = ?
")->execute([$other_id, $_SESSION['user_id']]);

echo "OK";
?>