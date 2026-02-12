<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$task_type = $_POST['task_type'] ?? '';

// Check and complete tasks automatically
switch($task_type) {
    case 'update_profile':
        // Check if user has uploaded avatar
        $check = $pdo->prepare("SELECT avatar FROM users WHERE id = ? AND avatar != 'default.jpg'");
        $check->execute([$_SESSION['user_id']]);
        if($check->fetch()) {
            // Complete task
            $stmt = $pdo->prepare("
                UPDATE user_tasks 
                SET completed = TRUE, completed_at = NOW() 
                WHERE user_id = ? AND task_type = 'update_profile' AND completed = FALSE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Add coins
            $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
                
            echo json_encode(['success' => true, 'coins_added' => 25]);
        }
        break;
        
    case 'complete_bio':
        // Check if bio is not empty
        $check = $pdo->prepare("SELECT bio FROM users WHERE id = ? AND bio IS NOT NULL AND bio != ''");
        $check->execute([$_SESSION['user_id']]);
        if($check->fetch()) {
            // Complete task
            $stmt = $pdo->prepare("
                UPDATE user_tasks 
                SET completed = TRUE, completed_at = NOW() 
                WHERE user_id = ? AND task_type = 'complete_bio' AND completed = FALSE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Add coins
            $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
                
            echo json_encode(['success' => true, 'coins_added' => 25]);
        }
        break;
        
    case 'first_message':
        // Check if user has sent any message
        $check = $pdo->prepare("SELECT COUNT(*) as count FROM private_messages WHERE sender_id = ?");
        $check->execute([$_SESSION['user_id']]);
        if($check->fetch()['count'] > 0) {
            // Complete task
            $stmt = $pdo->prepare("
                UPDATE user_tasks 
                SET completed = TRUE, completed_at = NOW() 
                WHERE user_id = ? AND task_type = 'first_message' AND completed = FALSE
            ");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Add coins
            $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
                
            echo json_encode(['success' => true, 'coins_added' => 25]);
        }
        break;
}
?>