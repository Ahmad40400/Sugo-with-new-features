<?php 
// login.php - COMPLETE FIXED VERSION
require 'config.php';

if(isset($_SESSION['user_id'])) {
    $check_verified = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
    $check_verified->execute([$_SESSION['user_id']]);
    $user = $check_verified->fetch();
    
    if($user && $user['is_verified'] == 1) {
        header("Location: home.php");
        exit;
    } else {
        header("Location: verify_otp.php");
        exit;
    }
}

if($_SERVER['REQUEST_METHOD'] == "POST") {
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token invalid";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user = $stmt->fetch();
        
        if($user && password_verify($_POST['password'], $user['password'])) {
            // Check if email is verified
            if($user['is_verified'] == 0) {
                // Not verified - send to verification page
                $_SESSION['temp_user_id'] = $user['id'];
                $_SESSION['temp_email'] = $user['email'];
                $_SESSION['temp_username'] = $user['username'];
                
                // Generate and send new OTP
                require 'mail_config.php';
                $otp = generateOTP();
                storeOTP($pdo, $user['id'], $otp);
                sendVerificationEmail($user['email'], $user['username'], $otp);
                
                header("Location: verify_otp.php?resend=1");
                exit;
            }
            
            // Verified - login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            
            // Update last active and last login date
            $pdo->prepare("UPDATE users SET last_active = NOW(), last_login_date = CURDATE() WHERE id = ?")
                ->execute([$user['id']]);
            
            header("Location: home.php");
            exit;
        }
        $error = "Invalid email or password";
    }
}
?>
<!-- REST OF YOUR HTML REMAINS THE SAME -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SUGO Chat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #0f172a;
            --light: #f8fafc;
            --radius: 12px;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }
        
        .login-container {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        
        .login-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary), #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1.5rem;
        }
        
        .form-control, .input-group-text {
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .form-control:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: var(--primary);
            color: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }
        
        .alert {
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">SUGO</div>
            <h2 class="text-center text-white mb-4">Welcome Back</h2>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label class="form-label text-white">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-white">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary mb-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
                
                <div class="text-center mt-3">
                    <span class="text-muted">Don't have an account?</span>
                    <a href="register.php" class="text-primary ms-1">Create one</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>