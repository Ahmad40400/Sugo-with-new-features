<?php
// get_online_users.php
require 'config.php';
if(!isset($_SESSION['user_id'])) exit;

// Get online friends
$online_stmt = $pdo->prepare("
    SELECT id, username, avatar, 
           TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago 
    FROM users 
    WHERE last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND id != ?
    ORDER BY last_active DESC 
    LIMIT 10
");
$online_stmt->execute([$_SESSION['user_id']]);
$online_friends = $online_stmt->fetchAll();

foreach($online_friends as $friend): 
    $is_online = $friend['minutes_ago'] < 5;
?>
<div class="online-user">
    <img src="uploads/<?= htmlspecialchars($friend['avatar']) ?>" class="online-user-avatar" alt="Avatar">
    <div class="flex-grow-1">
        <h6 class="mb-0"><?= htmlspecialchars($friend['username']) ?></h6>
        <small class="<?= $is_online ? 'text-success' : 'text-muted' ?>">
            <span class="online-badge" style="background: <?= $is_online ? '#10b981' : '#64748b' ?>"></span>
            <?= $is_online ? 'Online' : 'Offline' ?>
        </small>
    </div>
    <a href="private_chat.php?user=<?= $friend['id'] ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-comment"></i>
    </a>
</div>
<?php endforeach; ?>

<?php if(count($online_friends) === 0): ?>
<div class="text-center text-muted py-3">
    <i class="fas fa-users fa-2x mb-2"></i>
    <p>No users online at the moment</p>
</div>
<?php endif; ?>