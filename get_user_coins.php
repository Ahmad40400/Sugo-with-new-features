<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$result = $stmt->fetch();

header('Content-Type: application/json');
echo json_encode(['coins' => $result['coins']]);
?>