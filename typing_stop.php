<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$other_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$other_id) exit;

// Remove typing status
$pdo->prepare("DELETE FROM typing_status WHERE user_id = ? AND to_user_id = ?")
    ->execute([$_SESSION['user_id'], $other_id]);

echo "OK";
?>