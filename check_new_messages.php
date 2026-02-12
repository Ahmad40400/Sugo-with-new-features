<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
$last_id = filter_var($_GET['last_id'] ?? 0, FILTER_VALIDATE_INT);

if(!$other_id) exit;

// Check for new messages
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count, MAX(id) as last_id 
    FROM private_messages 
    WHERE ((sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?))
    AND id > ?
");

$stmt->execute([$_SESSION['user_id'], $other_id, $other_id, $_SESSION['user_id'], $last_id]);
$result = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode([
    'new_messages' => $result['count'] > 0,
    'last_id' => $result['last_id'] ?: $last_id
]);
?>