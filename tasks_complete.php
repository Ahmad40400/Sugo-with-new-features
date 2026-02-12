<?php
// _tasks_complete.php - FIXED VERSION
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

$task_type = $_POST['task_type'] ?? '';

header('Content-Type: application/json');

try {
    // Check and complete tasks automatically
    switch($task_type) {
        case 'update_profile':
            // Check if user has uploaded avatar (not default.jpg)
            $check = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $check->execute([$_SESSION['user_id']]);
            $user = $check->fetch();
            
            if($user && $user['avatar'] != 'default.jpg') {
                // Complete task
                $stmt = $pdo->prepare("
                    UPDATE user_tasks 
                    SET completed = TRUE, completed_at = NOW() 
                    WHERE user_id = ? AND task_type = 'update_profile' AND completed = FALSE
                ");
                $stmt->execute([$_SESSION['user_id']]);
                
                if($stmt->rowCount() > 0) {
                    // Add coins
                    $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                        ->execute([$_SESSION['user_id']]);
                    
                    echo json_encode(['success' => true, 'coins_added' => 25]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Task already completed']);
                }
            }
            break;
            
        case 'complete_bio':
            // Check if bio is not empty and at least 10 characters
            $check = $pdo->prepare("SELECT bio FROM users WHERE id = ?");
            $check->execute([$_SESSION['user_id']]);
            $user = $check->fetch();
            
            if($user && !empty($user['bio']) && strlen($user['bio']) >= 10) {
                // Complete task
                $stmt = $pdo->prepare("
                    UPDATE user_tasks 
                    SET completed = TRUE, completed_at = NOW() 
                    WHERE user_id = ? AND task_type = 'complete_bio' AND completed = FALSE
                ");
                $stmt->execute([$_SESSION['user_id']]);
                
                if($stmt->rowCount() > 0) {
                    // Add coins
                    $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                        ->execute([$_SESSION['user_id']]);
                    
                    echo json_encode(['success' => true, 'coins_added' => 25]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Task already completed']);
                }
            }
            break;
            
        case 'first_message':
            // Check if user has sent any message
            $check = $pdo->prepare("SELECT COUNT(*) as count FROM private_messages WHERE sender_id = ?");
            $check->execute([$_SESSION['user_id']]);
            $result = $check->fetch();
            
            if($result && $result['count'] > 0) {
                // Complete task
                $stmt = $pdo->prepare("
                    UPDATE user_tasks 
                    SET completed = TRUE, completed_at = NOW() 
                    WHERE user_id = ? AND task_type = 'first_message' AND completed = FALSE
                ");
                $stmt->execute([$_SESSION['user_id']]);
                
                if($stmt->rowCount() > 0) {
                    // Add coins
                    $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                        ->execute([$_SESSION['user_id']]);
                    
                    echo json_encode(['success' => true, 'coins_added' => 25]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Task already completed']);
                }
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid task type']);
    }
} catch(PDOException $e) {
    error_log("Task completion error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>