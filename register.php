<?php 
// register.php - COMPLETE FIXED VERSION
require 'config.php';
require 'mail_config.php';

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

$error = '';

if($_SERVER['REQUEST_METHOD'] == "POST") {
    if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token invalid. Please refresh the page and try again.";
    } else {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : 'male';
        
        // Validation
        $errors = [];
        
        if(empty($username)) {
            $errors[] = "Username is required.";
        } elseif(strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        } elseif(strlen($username) > 50) {
            $errors[] = "Username cannot exceed 50 characters.";
        } elseif(!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores.";
        }
        
        if(empty($email)) {
            $errors[] = "Email address is required.";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if(empty($password)) {
            $errors[] = "Password is required.";
        } elseif(strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        
        if($password !== $confirm_password) {
            $errors[] = "Passwords do not match.";
        }
        
        if(empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Check if username or email already exists
                $check_stmt = $pdo->prepare("SELECT id, username, email, is_verified FROM users WHERE username = ? OR email = ?");
                $check_stmt->execute([$username, $email]);
                $existing_user = $check_stmt->fetch();
                
                if($existing_user) {
                    $pdo->rollBack();
                    
                    if(strtolower($existing_user['email']) == strtolower($email)) {
                        if($existing_user['is_verified'] == 0) {
                            // Unverified account - resend OTP
                            $_SESSION['temp_user_id'] = $existing_user['id'];
                            $_SESSION['temp_email'] = $existing_user['email'];
                            $_SESSION['temp_username'] = $existing_user['username'];
                            
                            $otp = generateOTP();
                            storeOTP($pdo, $existing_user['id'], $otp);
                            $mail_result = sendVerificationEmail($existing_user['email'], $existing_user['username'], $otp);
                            
                            header("Location: verify_otp.php?resend=1");
                            exit;
                        } else {
                            $error = "Email already registered. Please <a href='login.php'>login here</a>.";
                        }
                    } else {
                        $error = "Username already taken. Please choose another.";
                    }
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Generate OTP
                    $otp = generateOTP();
                    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    
                    // Insert new user (unverified) with 20 coins
                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            username, email, password, gender, coins, 
                            created_at, last_active, is_verified, 
                            verification_code, code_expiry, avatar
                        ) VALUES (
                            ?, ?, ?, ?, 20, 
                            NOW(), NOW(), 0, 
                            ?, ?, 'default.jpg'
                        )
                    ");
                    
                    $insert_result = $stmt->execute([$username, $email, $hashed_password, $gender, $otp, $expiry]);
                    
                    if($insert_result) {
                        $user_id = $pdo->lastInsertId();
                        
                        // Send verification email
                        $mail_result = sendVerificationEmail($email, $username, $otp);
                        
                        if($mail_result['success']) {
                            // Store in session
                            $_SESSION['temp_user_id'] = $user_id;
                            $_SESSION['temp_email'] = $email;
                            $_SESSION['temp_username'] = $username;
                            
                            // Create initial tasks
                            $tasks = [
                                ['update_profile', 'Update Your Profile Picture', 25],
                                ['complete_bio', 'Complete Your Bio Information', 25],
                                ['first_message', 'Send Your First Message', 25],
                                ['daily_login', 'Login 3 Days in a Row', 50],
                                ['verify_email', 'Verify Email Address', 50],
                                ['join_room', 'Join a Chat Room', 25],
                                ['add_friend', 'Add a Friend', 50]
                            ];
                            
                            $task_stmt = $pdo->prepare("
                                INSERT INTO user_tasks (user_id, task_type, task_name, coins_reward) 
                                VALUES (?, ?, ?, ?)
                            ");
                            
                            foreach($tasks as $task) {
                                $task_stmt->execute([$user_id, $task[0], $task[1], $task[2]]);
                            }
                            
                            $pdo->commit();
                            
                            header("Location: verify_otp.php");
                            exit;
                        } else {
                            $pdo->rollBack();
                            $error = "Failed to send verification email. Please try again.";
                        }
                    } else {
                        $pdo->rollBack();
                        $error = "Database error. Please try again.";
                    }
                }
            } catch(PDOException $e) {
                $pdo->rollBack();
                $error = "Database error. Please try again.";
                error_log("Registration PDO Error: " . $e->getMessage());
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
}

$old_username = $_POST['username'] ?? '';
$old_email = $_POST['email'] ?? '';
$old_gender = $_POST['gender'] ?? 'male';
?>
<!-- REST OF YOUR HTML REMAINS THE SAME -->
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SUGO Chat</title>
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
        
        .register-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .register-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary), #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
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
        
        .input-group-text {
            color: var(--primary);
        }
        
        .gender-options {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        
        .gender-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(15, 23, 42, 0.8);
            color: #94a3b8;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .gender-btn:hover {
            border-color: var(--primary);
            color: white;
            background: rgba(99, 102, 241, 0.1);
        }
        
        .gender-btn.active {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.2);
            color: white;
        }
        
        .gender-btn i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 5px;
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
            margin-bottom: 20px;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .password-strength {
            height: 4px;
            background: #374151;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .strength-meter {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #10b981; width: 75%; }
        .strength-strong { background: #10b981; width: 100%; }
        
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 20px 0;
            color: #64748b;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .divider span {
            padding: 0 10px;
        }
        
        .terms-link {
            color: var(--primary);
            text-decoration: none;
        }
        
        .terms-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo">SUGO</div>
            <h4 class="text-center text-white mb-3">Create Your Account</h4>
            <p class="text-center text-muted mb-4">Join 10,000+ users worldwide</p>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-3">
                    <label class="form-label text-white">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" 
                               name="username" 
                               class="form-control" 
                               placeholder="Choose a username" 
                               value="<?= htmlspecialchars($old_username) ?>"
                               required
                               minlength="3"
                               maxlength="50"
                               pattern="[a-zA-Z0-9_]+"
                               title="Username can only contain letters, numbers, and underscores"
                               id="username">
                    </div>
                    <small class="text-muted">3-50 characters. Letters, numbers, and underscores only.</small>
                    <div id="usernameFeedback" class="mt-1" style="font-size: 0.85rem;"></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-white">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="Enter your email" 
                               value="<?= htmlspecialchars($old_email) ?>"
                               required
                               id="email">
                    </div>
                    <small class="text-muted">We'll send a 6-digit verification code to this email.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-white">Gender</label>
                    <div class="gender-options">
                        <label class="gender-btn <?= $old_gender == 'male' ? 'active' : '' ?>">
                            <input type="radio" name="gender" value="male" class="d-none" <?= $old_gender == 'male' ? 'checked' : '' ?>>
                            <i class="fas fa-mars"></i>
                            <span>Male</span>
                        </label>
                        <label class="gender-btn <?= $old_gender == 'female' ? 'active' : '' ?>">
                            <input type="radio" name="gender" value="female" class="d-none" <?= $old_gender == 'female' ? 'checked' : '' ?>>
                            <i class="fas fa-venus"></i>
                            <span>Female</span>
                        </label>
                        <label class="gender-btn <?= $old_gender == 'other' ? 'active' : '' ?>">
                            <input type="radio" name="gender" value="other" class="d-none" <?= $old_gender == 'other' ? 'checked' : '' ?>>
                            <i class="fas fa-genderless"></i>
                            <span>Other</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-white">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               name="password" 
                               id="password"
                               class="form-control" 
                               placeholder="Create a password" 
                               required
                               minlength="6">
                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength mt-2">
                        <div class="strength-meter" id="passwordStrength"></div>
                    </div>
                    <small class="text-muted">At least 6 characters. Use mix of letters & numbers.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-white">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" 
                               name="confirm_password" 
                               id="confirm_password"
                               class="form-control" 
                               placeholder="Confirm your password" 
                               required>
                    </div>
                    <div id="passwordMatch" class="mt-1" style="font-size: 0.85rem;"></div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label text-muted" for="terms">
                        I agree to the <a href="#" class="terms-link">Terms of Service</a> and 
                        <a href="#" class="terms-link">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary mb-3">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <div class="text-center">
                    <span class="text-muted">Already have an account?</span>
                    <a href="login.php" class="terms-link ms-1">Sign In</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthMeter = document.getElementById('passwordStrength');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if(password.length >= 6) strength++;
            if(password.length >= 8) strength++;
            if(/[A-Z]/.test(password)) strength++;
            if(/[0-9]/.test(password)) strength++;
            if(/[^A-Za-z0-9]/.test(password)) strength++;
            
            strengthMeter.className = 'strength-meter';
            if(strength <= 1) {
                strengthMeter.classList.add('strength-weak');
            } else if(strength <= 2) {
                strengthMeter.classList.add('strength-fair');
            } else if(strength <= 3) {
                strengthMeter.classList.add('strength-good');
            } else {
                strengthMeter.classList.add('strength-strong');
            }
        });
        
        // Password match checker
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatchDiv = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirm = confirmPassword.value;
            
            if(confirm.length === 0) {
                passwordMatchDiv.innerHTML = '';
                passwordMatchDiv.className = '';
            } else if(password === confirm) {
                passwordMatchDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match';
                passwordMatchDiv.className = 'text-success';
            } else {
                passwordMatchDiv.innerHTML = '<i class="fas fa-exclamation-circle text-danger"></i> Passwords do not match';
                passwordMatchDiv.className = 'text-danger';
            }
        }
        
        passwordInput.addEventListener('input', checkPasswordMatch);
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        // Gender selection
        document.querySelectorAll('.gender-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.gender-btn').forEach(b => {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                const input = this.querySelector('input[type="radio"]');
                input.checked = true;
            });
        });
        
        // Username availability check
        const usernameInput = document.getElementById('username');
        const usernameFeedback = document.getElementById('usernameFeedback');
        let usernameCheckTimer;
        
        usernameInput.addEventListener('input', function() {
            clearTimeout(usernameCheckTimer);
            const username = this.value;
            
            if(username.length >= 3) {
                usernameCheckTimer = setTimeout(() => {
                    fetch('check_username.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `username=${encodeURIComponent(username)}`
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.available) {
                            usernameFeedback.innerHTML = '<i class="fas fa-check-circle text-success"></i> Username available';
                            usernameFeedback.className = 'text-success';
                        } else {
                            usernameFeedback.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Username already taken';
                            usernameFeedback.className = 'text-danger';
                        }
                    });
                }, 500);
            } else {
                usernameFeedback.innerHTML = '';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirm = confirmPassword.value;
            
            if(password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            const username = usernameInput.value;
            const usernameRegex = /^[a-zA-Z0-9_]+$/;
            
            if(!usernameRegex.test(username)) {
                e.preventDefault();
                alert('Username can only contain letters, numbers, and underscores!');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                Creating Account...
            `;
            
            return true;
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>