<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_POST['other_id'] ?? 0, FILTER_VALIDATE_INT);
$is_typing = filter_var($_POST['is_typing'] ?? 0, FILTER_VALIDATE_INT);

if(!$other_id) exit;

if($is_typing) {
    // User is typing - update timestamp
    $stmt = $pdo->prepare("
        INSERT INTO typing_status (user_id, to_user_id, last_typing) 
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_typing = NOW()
    ");
    $stmt->execute([$_SESSION['user_id'], $other_id]);
} else {
    // User stopped typing - remove entry
    $pdo->prepare("DELETE FROM typing_status WHERE user_id = ? AND to_user_id = ?")
        ->execute([$_SESSION['user_id'], $other_id]);
}

echo "OK";
?>