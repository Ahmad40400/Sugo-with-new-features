<?php
require 'config.php';
if(isset($_SESSION['user_id'])) {
    $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?")
        ->execute([$_SESSION['user_id']]);
}
?>