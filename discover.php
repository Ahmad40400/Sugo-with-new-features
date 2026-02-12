<?php 
require 'config.php';
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Pagination
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$limit = 12;
$offset = ($page - 1) * $limit;

// Get total users - FIXED: Use prepare() instead of query()
$total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE id != ?");
$total_stmt->execute([$_SESSION['user_id']]);
$total_users = $total_stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get users with sorting options
$sort = $_GET['sort'] ?? 'new'; // 'new' or 'active'
if($sort === 'new') {
    // Sort by newest users first
    $query = "
        SELECT id, username, avatar, bio, 
               TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago,
               DATE_FORMAT(created_at, '%Y-%m-%d') as join_date 
        FROM users 
        WHERE id != ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ";
} else {
    // Sort by recently active users
    $query = "
        SELECT id, username, avatar, bio, 
               TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago,
               DATE_FORMAT(created_at, '%Y-%m-%d') as join_date 
        FROM users 
        WHERE id != ? 
        ORDER BY last_active DESC 
        LIMIT ? OFFSET ?
    ";
}
// Get current user's gender
$current_user_stmt = $pdo->prepare("SELECT gender FROM users WHERE id = ?");
$current_user_stmt->execute([$_SESSION['user_id']]);
$current_gender = $current_user_stmt->fetch()['gender'];

// Get users with gender-based suggestions
// If current user is male, show females; if female, show males; if other, show all
if($current_gender === 'male') {
    $gender_filter = "AND gender = 'female'";
} elseif($current_gender === 'female') {
    $gender_filter = "AND gender = 'male'";
} else {
    $gender_filter = ""; // Show all for 'other'
}

// Get total users - with gender filter
$total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE id != ? $gender_filter");
$total_stmt->execute([$_SESSION['user_id']]);
$total_users = $total_stmt->fetch()['total'];
$total_pages = ceil($total_users / $limit);

// Get users with sorting options and gender filter
$sort = $_GET['sort'] ?? 'new'; // 'new' or 'active'
if($sort === 'new') {
    $query = "
        SELECT id, username, avatar, bio, gender,
               TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago,
               DATE_FORMAT(created_at, '%Y-%m-%d') as join_date 
        FROM users 
        WHERE id != ? 
        $gender_filter
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ";
} else {
    $query = "
        SELECT id, username, avatar, bio, gender,
               TIMESTAMPDIFF(MINUTE, last_active, NOW()) as minutes_ago,
               DATE_FORMAT(created_at, '%Y-%m-%d') as join_date 
        FROM users 
        WHERE id != ? 
        $gender_filter
        ORDER BY last_active DESC 
        LIMIT ? OFFSET ?
    ";
}

$users = $pdo->prepare($query);
$users->execute([$_SESSION['user_id'], $limit, $offset]);
$users = $users->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discover People</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f0f23; color: #fff; }
        .user-card { 
            background: #1a1a2e; 
            border-radius: 10px; 
            padding: 15px; 
            margin-bottom: 20px;
            transition: transform 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .user-card:hover { 
            transform: translateY(-5px); 
            border-color: #4cc9f0;
            box-shadow: 0 10px 25px rgba(76, 201, 240, 0.2);
        }
        .avatar { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 3px solid #4cc9f0; 
        }
        .online { color: #0f0; }
        .offline { color: #888; }
        .new-user-badge {
            background: linear-gradient(45deg, #ff6b6b, #ff8e53);
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 10px;
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .sort-options {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
<style>
    /* Override Bootstrap's text-muted for better visibility */
    .text-muted {
        color: #d1d5db !important; /* Light gray - visible but not too bright */
        opacity: 0.95 !important;
    }
    
    /* Make all muted text elements look consistent */
    .small.text-muted,
    p.text-muted,
    span.text-muted,
    div.text-muted,
    small.text-muted {
        color: #d1d5db !important;
        opacity: 0.9 !important;
    }
    
    /* For paragraphs with muted class */
    p.text-muted {
        color: #e5e7eb !important;
        line-height: 1.6;
    }
    
    /* Bio text specifically */
    .bio.small.text-muted {
        color: #e5e7eb !important;
        opacity: 0.9 !important;
    }
    
    /* User status (offline) */
    .offline {
        color: #9ca3af !important; /* Medium gray */
    }
    
    /* Online status stays green */
    .online {
        color: #10b981 !important; /* Green */
    }
    
    /* Headings and regular text should be white */
    h1, h2, h3, h4, h5, h6, p:not(.text-muted), strong, b {
        color: #ffffff !important;
    }
    
    /* Sort options section */
    .sort-options {
        color: #f3f4f6 !important; /* Very light gray */
    }
    
    .sort-options h5 {
        color: #ffffff !important;
    }
    
    .sort-options small {
        color: #d1d5db !important; /* Same as text-muted */
    }
    
    /* Page links in pagination */
    .page-link {
        color: #e5e7eb !important; /* Light gray */
    }
    
    .page-item.active .page-link {
        color: #0f0f23 !important; /* Dark text on active */
    }
    
    /* Gender icons with appropriate colors */
    .fa-mars.text-primary {
        color: #60a5fa !important; /* Soft blue */
    }
    
    .fa-venus.text-danger {
        color: #f87171 !important; /* Soft red */
    }
    
    .fa-genderless.text-secondary {
        color: #9ca3af !important; /* Medium gray */
    }
    
    /* Ensure contrast for better readability */
    body {
        background: #0f0f23;
        color: #f3f4f6 !important; /* Default text color */
    }
    
    /* For very small text that needs to be subtle */
    .text-muted.small {
        color: #9ca3af !important;
    }
    
    /* Button text */
    .btn-outline-light {
        color: #f9fafb !important;
    }
    
    .btn-outline-light:hover {
        color: #0f0f23 !important;
    }
    
    /* Fix for Bootstrap's default styles */
    .text-muted {
        --bs-text-opacity: 1 !important;
    }
</style>
    </style>
</head>
<body>
    <div class="container mt-4">
        <nav class="mb-4">
            <a href="home.php" class="btn btn-outline-light"><i class="fas fa-home"></i> Home</a>
            <a href="profile.php" class="btn btn-outline-info"><i class="fas fa-user"></i> Profile</a>
            <a href="logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>
        
        <h2><i class="fas fa-users"></i> Discover People</h2>
        <p class="text-muted">Connect with users from around the world</p>
        
        <!-- Sort Options -->
        <div class="sort-options">
            <h5>Sort by:</h5>
            <div class="btn-group" role="group">
                <a href="?sort=new" class="btn <?= ($sort === 'new') ? 'btn-primary' : 'btn-outline-light' ?>">
                    <i class="fas fa-user-plus"></i> Newest Users
                </a>
                <a href="?sort=active" class="btn <?= ($sort === 'active') ? 'btn-primary' : 'btn-outline-light' ?>">
                    <i class="fas fa-bolt"></i> Recently Active
                </a>
            </div>
            <small class="text-muted mt-2 d-block">
                <?= $total_users ?> users available to connect
            </small>
        </div>
        
        <div class="row">
            <?php foreach($users as $u): 
                $is_online = $u['minutes_ago'] < 5;
                $is_new = (strtotime($u['join_date']) > strtotime('-7 days'));
            ?>
            <div class="col-md-4 col-lg-3">
                <div class="user-card text-center position-relative">
                    <?php if($is_new): ?>
                        <span class="new-user-badge">NEW</span>
                    <?php endif; ?>
                    
                    <img src="uploads/<?= htmlspecialchars($u['avatar'] ?? 'default.jpg') ?>" 
                         class="avatar mb-3" alt="Avatar">
                    <h5><?= htmlspecialchars($u['username']) ?></h5>
                    <p class="small">
                        <?php if($is_online): ?>
                            <span class="online"><i class="fas fa-circle"></i> Online Now</span>
                        <?php else: ?>
                            <span class="offline"><i class="far fa-circle"></i> 
                                <?= $u['minutes_ago'] < 60 ? $u['minutes_ago'] . ' min ago' : 
                                   floor($u['minutes_ago']/60) . ' hours ago' ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <!-- In the user card section -->
<p class="small">
    <?php 
    $gender_icon = '';
    switch($u['gender']) {
        case 'male': $gender_icon = '<i class="fas fa-mars text-primary"></i>'; break;
        case 'female': $gender_icon = '<i class="fas fa-venus text-danger"></i>'; break;
        default: $gender_icon = '<i class="fas fa-genderless text-secondary"></i>';
    }
    echo $gender_icon . ' ' . ucfirst($u['gender']);
    ?>
</p>
                    <p class="bio small text-muted">
                        <?= !empty($u['bio']) ? htmlspecialchars(substr($u['bio'], 0, 100)) . '...' : 'No bio yet' ?>
                    </p>
                    
                    <div class="d-flex justify-content-center gap-2">
                        <a href="private_chat.php?user=<?= $u['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-comment"></i> Message
                        </a>
                        <a href="profile_view.php?user=<?= $u['id'] ?>" class="btn btn-outline-light btn-sm">
                            <i class="fas fa-eye"></i> View
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&sort=<?= $sort ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</body>
</html>