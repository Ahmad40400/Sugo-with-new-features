<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$other_id) exit;

// Get status of sent messages
$stmt = $pdo->prepare("
    SELECT id, is_read 
    FROM private_messages 
    WHERE sender_id = ? AND receiver_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id'], $other_id]);

$status = [];
while($row = $stmt->fetch()) {
    $status[$row['id']] = $row['is_read'];
}

header('Content-Type: application/json');
echo json_encode($status);
?>