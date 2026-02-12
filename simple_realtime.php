<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];
$last_id = $_GET['last_id'] ?? 0;

// Simply check if ANY new messages exist
$stmt = $pdo->prepare("
    SELECT MAX(id) as max_id 
    FROM private_messages 
    WHERE receiver_id = ? 
    AND id > ?
");
$stmt->execute([$user_id, $last_id]);
$result = $stmt->fetch();

// Also check for message delivery status
$delivered_stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM private_messages 
    WHERE sender_id = ? 
    AND is_read = 1 
    AND id > ?
");
$delivered_stmt->execute([$user_id, $last_id]);
$delivered = $delivered_stmt->fetch();

header('Content-Type: application/json');
echo json_encode([
    'has_new' => ($result['max_id'] ?? 0) > 0,
    'new_id' => $result['max_id'] ?? $last_id,
    'delivered' => $delivered['count'] > 0
]);
?>