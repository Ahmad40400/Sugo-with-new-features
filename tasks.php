<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user coins
$user_stmt = $pdo->prepare("SELECT coins FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user_coins = $user_stmt->fetch()['coins'];

// Get user tasks
$tasks_stmt = $pdo->prepare("
    SELECT * FROM user_tasks 
    WHERE user_id = ? 
    ORDER BY completed ASC, coins_reward DESC
");
$tasks_stmt->execute([$_SESSION['user_id']]);
$tasks = $tasks_stmt->fetchAll();

// Complete task
if(isset($_POST['complete_task'])) {
    $task_id = filter_var($_POST['task_id'], FILTER_VALIDATE_INT);
    if($task_id) {
        // Check if task exists and not completed
        $check_stmt = $pdo->prepare("SELECT * FROM user_tasks WHERE id = ? AND user_id = ? AND completed = FALSE");
        $check_stmt->execute([$task_id, $_SESSION['user_id']]);
        $task = $check_stmt->fetch();
        
        if($task) {
            // Update task as completed
            $pdo->prepare("UPDATE user_tasks SET completed = TRUE, completed_at = NOW() WHERE id = ?")
                ->execute([$task_id]);
            
            // Add coins to user
            $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")
                ->execute([$task['coins_reward'], $_SESSION['user_id']]);
            
            // Update local coins
            $user_coins += $task['coins_reward'];
            
            header("Location: tasks.php?success=1");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earn Coins - SUGO Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f0f23; color: #fff; }
        .task-card { 
            background: #1a1a2e; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 15px;
            border-left: 4px solid #6366f1;
        }
        .task-card.completed { 
            opacity: 0.7; 
            border-left-color: #10b981;
        }
        .coins-badge {
            background: linear-gradient(45deg, #f59e0b, #f97316);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .progress {
            height: 10px;
            background: rgba(255,255,255,0.1);
        }
        .progress-bar {
            background: linear-gradient(45deg, #6366f1, #4f46e5);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <nav class="mb-4">
            <a href="home.php" class="btn btn-outline-light"><i class="fas fa-home"></i> Home</a>
            <a href="discover.php" class="btn btn-outline-info"><i class="fas fa-users"></i> Discover</a>
        </nav>
        
        <!-- Coin Balance -->
        <div class="card bg-dark border-primary mb-4">
            <div class="card-body text-center">
                <h2 style="color: #e5e7eb !important;"><i class="fas fa-coins text-warning"></i> Your Coins</h2>
                <div class="display-4" style="color: #e5e7eb !important;"><?= $user_coins ?></div>
                <p style="color: #e5e7eb !important;">Each message costs 20 coins</p>
                <div class="progress mb-3">
                    <div class="progress-bar" style="width: <?= min(($user_coins / 200) * 100, 100) ?>%"></div>
                </div>
                <small style="color: #e5e7eb !important;">Max 200 coins | <?= floor($user_coins / 20) ?> messages remaining</small>
            </div>
        </div>
        
        <h2><i class="fas fa-tasks"></i> Earn More Coins</h2>
        <p class="text-muted mb-4">Complete tasks to earn coins and keep chatting!</p>
        
        <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Task completed! Coins added to your account.
            </div>
        <?php endif; ?>
        
        <div class="row">
            <?php foreach($tasks as $task): ?>
            <div class="col-md-6">
                <div class="task-card <?= $task['completed'] ? 'completed' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h5><?= htmlspecialchars($task['task_name']) ?></h5>
                            <?php if($task['completed']): ?>
                                <span class="badge bg-success"><i class="fas fa-check"></i> Completed</span>
                            <?php else: ?>
                                <span class="badge bg-warning"><i class="fas fa-clock"></i> Pending</span>
                            <?php endif; ?>
                        </div>
                        <span class="coins-badge">+<?= $task['coins_reward'] ?> <i class="fas fa-coins"></i></span>
                    </div>
                    
                    <?php if(!$task['completed']): ?>
                        <form method="POST" class="mt-3">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <button type="submit" name="complete_task" class="btn btn-primary btn-sm">
                                <i class="fas fa-check"></i> Complete Task
                            </button>
                        </form>
                    <?php else: ?>
                        <small class="text-muted">
                            Completed on: <?= date('M d, Y', strtotime($task['completed_at'])) ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Quick Task Tips -->
        <div class="card bg-dark border-secondary mt-4 ">
            <div class="card-body">
                <h5 style="color: #e5e7eb !important;"><i class="fas fa-lightbulb"style="color: yellow !important;"></i> Quick Tips to Earn Coins</h5>
                <ul class="text-muted"style="color: #e5e7eb !important;">
                    <li>Update your profile picture daily</li>
                    <li>Invite friends to join the platform</li>
                    <li>Complete your bio information</li>
                    <li>Send your first message to someone</li>
                    <li>Join chat rooms and participate</li>
                    <li>Verify your email address</li>
                    <li>Watch short ads for quick coins</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>