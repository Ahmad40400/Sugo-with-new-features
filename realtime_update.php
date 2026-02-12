<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];
$last_check = $_GET['last_check'] ?? time();

// Check for new messages AND typing status
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT CASE WHEN receiver_id = ? AND is_read = FALSE THEN id END) as new_messages,
        GROUP_CONCAT(DISTINCT CASE WHEN receiver_id = ? AND is_read = FALSE THEN sender_id END) as new_senders,
        COUNT(DISTINCT CASE WHEN sender_id = ? AND is_read = TRUE THEN id END) as delivered_messages,
        MAX(created_at) as last_message_time
    FROM private_messages
    WHERE (receiver_id = ? OR sender_id = ?)
    AND created_at > FROM_UNIXTIME(?)
");

$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $last_check]);
$result = $stmt->fetch();

// Check typing status (you need to create a typing_status table)
$typing_stmt = $pdo->prepare("
    SELECT user_id FROM typing_status 
    WHERE to_user_id = ? AND last_typing > DATE_SUB(NOW(), INTERVAL 3 SECOND)
");
$typing_stmt->execute([$user_id]);
$typing_users = $typing_stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json');
echo json_encode([
    'has_updates' => $result['new_messages'] > 0 || $result['delivered_messages'] > 0,
    'new_messages' => $result['new_messages'],
    'delivered_messages' => $result['delivered_messages'],
    'typing_users' => $typing_users,
    'last_message_time' => $result['last_message_time'],
    'timestamp' => time()
]);
?>