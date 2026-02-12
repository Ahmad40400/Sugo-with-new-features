<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
$last_id = filter_var($_GET['last_id'] ?? 0, FILTER_VALIDATE_INT);

// Check for new messages
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, MAX(id) as max_id 
    FROM private_messages 
    WHERE ((sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?))
    AND id > ?
");
$stmt->execute([$_SESSION['user_id'], $other_id, $other_id, $_SESSION['user_id'], $last_id]);
$result = $stmt->fetch();

// Check if any new messages are for current user (to play sound)
$new_for_me_stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM private_messages 
    WHERE receiver_id = ? 
    AND sender_id = ?
    AND id > ?
");
$new_for_me_stmt->execute([$_SESSION['user_id'], $other_id, $last_id]);
$new_for_me = $new_for_me_stmt->fetch();

header('Content-Type: application/json');
echo json_encode([
    'has_new' => $result['count'] > 0,
    'last_id' => $result['max_id'] ?: $last_id,
    'play_sound' => $new_for_me['count'] > 0
]);
?>