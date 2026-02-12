<?php
// check_new_messages_home.php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

// Get the last check timestamp
$last_check = filter_var($_GET['last_check'] ?? 0, FILTER_VALIDATE_INT);

// Check for new messages since last check
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as new_messages,
        GROUP_CONCAT(DISTINCT sender_id) as senders
    FROM private_messages 
    WHERE receiver_id = ? 
    AND is_read = FALSE
    AND created_at > FROM_UNIXTIME(?)
");

$stmt->execute([$_SESSION['user_id'], $last_check]);
$result = $stmt->fetch();

// Get sender usernames
$sender_names = [];
if($result['senders']) {
    $sender_ids = explode(',', $result['senders']);
    $placeholders = str_repeat('?,', count($sender_ids) - 1) . '?';
    $name_stmt = $pdo->prepare("
        SELECT id, username 
        FROM users 
        WHERE id IN ($placeholders)
    ");
    $name_stmt->execute($sender_ids);
    $sender_names = $name_stmt->fetchAll();
}

header('Content-Type: application/json');
echo json_encode([
    'has_new' => $result['new_messages'] > 0,
    'count' => $result['new_messages'],
    'senders' => $sender_names,
    'timestamp' => time()
]);
?>