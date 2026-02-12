<?php
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = filter_var($_GET['user'] ?? 0, FILTER_VALIDATE_INT);
if(!$user_id) {
    header("Location: discover.php");
    exit;
}

// Get user data
$stmt = $pdo->prepare("
    SELECT *, TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago 
    FROM users WHERE id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if(!$user) {
    header("Location: discover.php");
    exit;
}

$is_online = $user['minutes_ago'] < 5;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['username']) ?>'s Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f0f23; color: #fff; }
        .profile-header { background: #1a1a2e; border-radius: 15px; padding: 30px; }
        .profile-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 5px solid #4cc9f0; }
        .stat-box { background: rgba(255,255,255,0.1); border-radius: 10px; padding: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <nav class="mb-4">
            <a href="home.php" class="btn btn-outline-light"><i class="fas fa-home"></i> Home</a>
            <a href="discover.php" class="btn btn-outline-info"><i class="fas fa-users"></i> Discover</a>
            <a href="profile.php" class="btn btn-outline-warning"><i class="fas fa-user"></i> My Profile</a>
        </nav>
        
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <img src="uploads/<?= htmlspecialchars($user['avatar']) ?>" class="profile-avatar mb-3" alt="Avatar">
                </div>
                <div class="col-md-9">
                    <h2><?= htmlspecialchars($user['username']) ?></h2>
                    <p class="lead">
                        <?php if($is_online): ?>
                            <span class="badge bg-success"><i class="fas fa-circle"></i> Online Now</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="far fa-circle"></i> Offline</span>
                            <small class="text-muted"style="color: #e5e7eb !important;">Last seen <?= $user['minutes_ago'] < 60 ? $user['minutes_ago'] . ' minutes ago' : floor($user['minutes_ago']/60) . ' hours ago' ?></small>
                        <?php endif; ?>
                    </p>
                    
                    <div class="mt-3">
                        <h5>About</h5>
                        <p><?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : '<i class="text-muted">No bio yet</i>' ?></p>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <div class="stat-box">
                                <h4><i class="fas fa-coins text-warning"></i></h4>
                                <h5><?= $user['coins'] ?></h5>
                                <small>Coins</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-box">
                                <h4><i class="fas fa-calendar-alt text-info"></i></h4>
                                <h5><?= date('M d, Y', strtotime($user['last_active'])) ?></h5>
                                <small>Last Active</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <a href="private_chat.php?user=<?= $user['id'] ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-comment"></i> Send Message
            </a>
            <button class="btn btn-warning btn-lg" onclick="sendGift()">
                <i class="fas fa-gift"></i> Send Gift
            </button>
        </div>
    </div>
    
    <script>
        function sendGift() {
            alert('Gift system coming soon!');
        }
    </script>
</body>
</html>