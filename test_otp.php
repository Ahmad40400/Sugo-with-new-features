<?php
// test_otp.php - Test OTP verification directly
require 'config.php';

if(!isset($_SESSION['temp_user_id']) && !isset($_GET['user_id'])) {
    die("No user session found. Please register first.");
}

$user_id = $_GET['user_id'] ?? $_SESSION['temp_user_id'] ?? 0;

// Get user data
$stmt = $pdo->prepare("SELECT id, username, email, verification_code, code_expiry, is_verified FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if(!$user) {
    die("User not found");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>OTP Test Tool</title>
    <style>
        body { background: #0f172a; color: white; font-family: Arial; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #1e293b; padding: 30px; border-radius: 10px; }
        .code { font-size: 32px; background: #0f172a; padding: 20px; text-align: center; border-radius: 8px; letter-spacing: 10px; }
        .success { color: #10b981; }
        .danger { color: #ef4444; }
        .warning { color: #f59e0b; }
        a { color: #6366f1; text-decoration: none; }
        a:hover { text-decoration: underline; }
        button { background: #6366f1; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 OTP Test Tool</h1>
        
        <div style="margin-bottom: 20px;">
            <strong>User:</strong> <?= htmlspecialchars($user['username']) ?><br>
            <strong>Email:</strong> <?= htmlspecialchars($user['email']) ?><br>
            <strong>Verified Status:</strong> 
            <?php if($user['is_verified']): ?>
                <span class="success">✅ Verified</span>
            <?php else: ?>
                <span class="warning">⏳ Not Verified</span>
            <?php endif; ?>
        </div>
        
        <?php if(!$user['is_verified']): ?>
        <div style="margin-bottom: 20px;">
            <strong>Current OTP Code:</strong>
            <div class="code"><?= $user['verification_code'] ?? 'NO CODE' ?></div>
            
            <?php if($user['code_expiry']): ?>
            <small>Expires: <?= $user['code_expiry'] ?></small>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 30px;">
            <h3>Quick Actions:</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="verify_otp.php" style="background: #6366f1; color: white; padding: 10px 20px; border-radius: 5px;">Go to OTP Page</a>
                
                <form method="post" action="verify_otp.php" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="otp" value="<?= $user['verification_code'] ?>">
                    <input type="hidden" name="verify" value="1">
                    <button type="submit">Auto-Fill OTP & Verify</button>
                </form>
                
                <a href="?resend=1&user_id=<?= $user_id ?>" style="background: #f59e0b; color: white; padding: 10px 20px; border-radius: 5px;">Resend OTP</a>
            </div>
        </div>
        <?php else: ?>
        <div style="margin-top: 30px;">
            <a href="home.php" style="background: #10b981; color: white; padding: 10px 20px; border-radius: 5px; display: inline-block;">Go to Home</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>