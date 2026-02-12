<?php
// verify_success.php - COMPLETE FIXED VERSION
require 'config.php';

// FIX: Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user data
$stmt = $pdo->prepare("SELECT username, coins, email, is_verified, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If user is not verified, send back to OTP page
if(!$user || $user['is_verified'] == 0) {
    header("Location: verify_otp.php");
    exit;
}

// FIX: Clear any temp session data
unset($_SESSION['temp_user_id']);
unset($_SESSION['temp_email']);
unset($_SESSION['temp_username']);
unset($_SESSION['verification_success']);

// Get completed tasks count
$tasks_stmt = $pdo->prepare("
    SELECT COUNT(*) as completed_tasks 
    FROM user_tasks 
    WHERE user_id = ? AND completed = TRUE
");
$tasks_stmt->execute([$_SESSION['user_id']]);
$completed_tasks = $tasks_stmt->fetch()['completed_tasks'] ?? 0;

// Get messages count
$msg_stmt = $pdo->prepare("SELECT COUNT(*) FROM private_messages WHERE sender_id = ?");
$msg_stmt->execute([$_SESSION['user_id']]);
$messages_count = $msg_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to SUGO Chat!</title>
    <meta http-equiv="refresh" content="8;url=home.php">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray: #64748b;
            --radius: 12px;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
            padding: 20px;
        }
        
        .success-container {
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
        }
        
        .success-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .confetti {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            z-index: 10;
        }
        
        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, #10b981, #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 20;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        
        .success-icon i {
            font-size: 3.5rem;
            color: white;
        }
        
        .welcome-text {
            background: linear-gradient(45deg, #fff, #a5b4fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 20;
        }
        
        .coins-badge-large {
            background: linear-gradient(45deg, #f59e0b, #f97316);
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1.3rem;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 30px 0;
            position: relative;
            z-index: 20;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: var(--radius);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-item:hover {
            background: rgba(99, 102, 241, 0.1);
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            line-height: 1;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .feature-list {
            list-style: none;
            padding: 0;
            margin: 30px 0;
            position: relative;
            z-index: 20;
        }
        
        .feature-list li {
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
        }
        
        .feature-list li:last-child {
            border-bottom: none;
        }
        
        .feature-list i {
            width: 30px;
            color: var(--primary);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.4);
        }
        
        .btn-outline-light {
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 16px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: white;
            transform: translateY(-3px);
        }
        
        .auto-redirect {
            margin-top: 20px;
            padding: 10px;
            background: rgba(99, 102, 241, 0.1);
            border-radius: 8px;
            color: #94a3b8;
        }
        
        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-text {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-card">
            <!-- Confetti Container -->
            <div class="confetti" id="confetti"></div>
            
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="welcome-text text-center">
                Welcome, <?= htmlspecialchars($user['username']) ?>! 🎉
            </h1>
            
            <p class="text-center text-light mb-4">
                Your email has been successfully verified. Your account is now fully activated!
            </p>
            
            <div class="text-center mb-4">
                <span class="coins-badge-large">
                    <i class="fas fa-coins me-2"></i>
                    +50 Coins Earned!
                </span>
            </div>
            
            <div class="auto-redirect text-center">
                <i class="fas fa-spinner fa-spin me-2"></i>
                Redirecting to dashboard in <span id="countdown">8</span> seconds...
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-number"><?= $user['coins'] ?></div>
                    <div class="stat-label">Total Coins</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?= $completed_tasks ?></div>
                    <div class="stat-label">Tasks Done</div>
                </div>
                
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div class="stat-number"><?= $messages_count ?></div>
                    <div class="stat-label">Messages</div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="row g-3 mt-4">
                <div class="col-md-6">
                    <a href="home.php" class="btn btn-primary w-100 py-3">
                        <i class="fas fa-home me-2"></i>
                        Go to Dashboard
                    </a>
                </div>
                <div class="col-md-6">
                    <a href="profile.php" class="btn btn-outline-light w-100 py-3">
                        <i class="fas fa-user-edit me-2"></i>
                        Complete Profile
                    </a>
                </div>
                <div class="col-12">
                    <a href="discover.php" class="btn btn-outline-light w-100 py-3">
                        <i class="fas fa-users me-2"></i>
                        Discover People
                    </a>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-check-circle text-success me-1"></i>
                    Account verified on <?= date('F j, Y \a\t h:i A', strtotime($user['created_at'] ?? 'now')) ?>
                </small>
            </div>
        </div>
    </div>
    
    <script>
        // Countdown timer - FIXED
        let seconds = 8;
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(function() {
            seconds--;
            if(countdownElement) {
                countdownElement.textContent = seconds;
            }
            
            if(seconds <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'home.php';
            }
        }, 1000);
        
        // Confetti Effect - FIXED
        function createConfetti() {
            const confettiContainer = document.getElementById('confetti');
            if(!confettiContainer) return;
            
            for(let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: absolute;
                        width: ${Math.random() * 10 + 5}px;
                        height: ${Math.random() * 10 + 5}px;
                        background: ${getRandomColor()};
                        left: ${Math.random() * 100}%;
                        top: -20px;
                        opacity: ${Math.random() * 0.7 + 0.3};
                        transform: rotate(${Math.random() * 360}deg);
                        animation: fall ${Math.random() * 3 + 2}s linear forwards;
                        border-radius: ${Math.random() > 0.5 ? '50%' : '0'};
                        z-index: 1000;
                    `;
                    
                    confettiContainer.appendChild(confetti);
                    
                    setTimeout(() => {
                        if(confetti.parentNode) {
                            confetti.remove();
                        }
                    }, 5000);
                }, i * 50);
            }
        }
        
        function getRandomColor() {
            const colors = [
                '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#ec4899', '#06b6d4', '#f97316', '#84cc16', '#a855f7'
            ];
            return colors[Math.floor(Math.random() * colors.length)];
        }
        
        // Add confetti animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(720deg);
                }
            }
        `;
        document.head.appendChild(style);
        
        // Run confetti on page load
        window.addEventListener('load', function() {
            createConfetti();
            
            // Run confetti again every 3 seconds for 15 seconds
            let count = 0;
            const interval = setInterval(() => {
                if(count < 5) {
                    createConfetti();
                    count++;
                } else {
                    clearInterval(interval);
                }
            }, 3000);
        });
        
        // Prevent accidental navigation away
        window.addEventListener('beforeunload', function(e) {
            // Don't show warning, just ensure redirect works
        });
    </script>
</body>
</html>