<?php
// mark_all_read.php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$pdo->prepare("
    UPDATE private_messages 
    SET is_read = TRUE 
    WHERE receiver_id = ? AND is_read = FALSE
")->execute([$_SESSION['user_id']]);

echo "OK";
?>