<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);

// Check if other user is typing
$stmt = $pdo->prepare("
    SELECT 1 
    FROM typing_status 
    WHERE user_id = ? 
    AND to_user_id = ?
    AND last_typing > DATE_SUB(NOW(), INTERVAL 3 SECOND)
    LIMIT 1
");
$stmt->execute([$other_id, $_SESSION['user_id']]);
$is_typing = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode([
    'is_typing' => $is_typing ? true : false
]);
?>