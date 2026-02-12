<?php 
// private_send.php - FIXED VERSION
require 'config.php';

if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Not authenticated']));
}

$other_id = filter_var($_POST['user'] ?? 0, FILTER_VALIDATE_INT);
$message = trim($_POST['msg'] ?? '');

if(!$other_id || empty($message)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid input']));
}

// Check user coins
$coins_stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
$coins_stmt->execute([$_SESSION['user_id']]);
$user_coins = $coins_stmt->fetch()['coins'];

$message_cost = 20;

if($user_coins < $message_cost) {
    http_response_code(402);
    exit(json_encode([
        'error' => 'insufficient_coins',
        'coins_needed' => $message_cost,
        'current_coins' => $user_coins,
        'message' => 'Not enough coins to send message. Complete tasks to earn more coins.'
    ]));
}

// Check if other user exists
$check_stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$check_stmt->execute([$other_id]);
if(!$check_stmt->fetch()) {
    http_response_code(404);
    exit(json_encode(['error' => 'User not found']));
}

// Sanitize message
$message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
$message = substr($message, 0, 2000);

try {
    $pdo->beginTransaction();
    
    // Insert message
    $stmt = $pdo->prepare("INSERT INTO private_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $other_id, $message]);
    $message_id = $pdo->lastInsertId();
    
    // Deduct coins
    $pdo->prepare("UPDATE users SET coins = coins - ?, last_active = NOW() WHERE id = ?")
        ->execute([$message_cost, $_SESSION['user_id']]);
    
    // Get updated coins
    $coins_stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
    $coins_stmt->execute([$_SESSION['user_id']]);
    $new_coins = $coins_stmt->fetch()['coins'];
    
    $pdo->commit();
    
    // Check if user needs tasks
    $needs_tasks = $new_coins < $message_cost * 3;
    
    // Auto-complete first message task
    $task_check = $pdo->prepare("SELECT COUNT(*) FROM private_messages WHERE sender_id = ?");
    $task_check->execute([$_SESSION['user_id']]);
    $message_count = $task_check->fetchColumn();
    
    if($message_count == 1) {
        $task_stmt = $pdo->prepare("
            UPDATE user_tasks 
            SET completed = TRUE, completed_at = NOW() 
            WHERE user_id = ? AND task_type = 'first_message' AND completed = FALSE
        ");
        $task_stmt->execute([$_SESSION['user_id']]);
        
        if($task_stmt->rowCount() > 0) {
            $pdo->prepare("UPDATE users SET coins = coins + 25 WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
            $new_coins += 25;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'id' => $message_id,
        'coins_deducted' => $message_cost,
        'remaining_coins' => $new_coins,
        'needs_tasks' => $needs_tasks
    ]);
    
} catch(PDOException $e) {
    $pdo->rollBack();
    error_log("Message send failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to send message']);
}
?>