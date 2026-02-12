<?php
// get_unread_count.php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM private_messages 
    WHERE receiver_id = ? AND is_read = FALSE
");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode(['count' => $result['count']]);
?>