<?php
// verify_otp.php - COMPLETE FIXED WORKING VERSION
require 'config.php';
require 'mail_config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// FIX: Clear any existing user session to prevent conflicts
if(isset($_SESSION['user_id'])) {
    // Check if user is already verified
    $check = $pdo->prepare("SELECT is_verified FROM users WHERE id = ?");
    $check->execute([$_SESSION['user_id']]);
    $user = $check->fetch();
    
    if($user && $user['is_verified'] == 1) {
        header("Location: home.php");
        exit;
    }
}

// FIX: Get user ID from multiple sources
$user_id = null;
$email = null;
$username = null;

// Priority 1: temp_user_id (new registration)
if(isset($_SESSION['temp_user_id'])) {
    $user_id = $_SESSION['temp_user_id'];
    $email = $_SESSION['temp_email'] ?? '';
    $username = $_SESSION['temp_username'] ?? '';
} 
// Priority 2: user_id (logged in but not verified)
elseif(isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("SELECT email, username, is_verified FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if($user) {
        if($user['is_verified'] == 1) {
            header("Location: home.php");
            exit;
        }
        $email = $user['email'];
        $username = $user['username'];
        
        // Set temp session for consistency
        $_SESSION['temp_user_id'] = $user_id;
        $_SESSION['temp_email'] = $email;
        $_SESSION['temp_username'] = $username;
    }
}
// Priority 3: URL parameter (direct access)
elseif(isset($_GET['user_id'])) {
    $user_id = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare("SELECT email, username, is_verified FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if($user && $user['is_verified'] == 0) {
        $email = $user['email'];
        $username = $user['username'];
        
        $_SESSION['temp_user_id'] = $user_id;
        $_SESSION['temp_email'] = $email;
        $_SESSION['temp_username'] = $username;
    }
}

// If no user data found
if(!$user_id || !$email) {
    error_log("No user session in verify_otp.php - redirecting to register");
    header("Location: register.php");
    exit;
}

$error = '';
$success = '';
$resend_success = '';

// ============== FIXED: OTP VERIFICATION WITH PROPER COMPARISON ==============
if($_SERVER['REQUEST_METHOD'] == "POST" && isset($_POST['verify'])) {
    
    error_log("========== VERIFICATION ATTEMPT ==========");
    error_log("User ID: " . $user_id);
    
    if(!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token invalid. Please refresh the page.";
        error_log("CSRF token mismatch");
    } else {
        // FIX: Get OTP and trim whitespace
        $otp = '';
        
        if(isset($_POST['otp']) && !empty($_POST['otp'])) {
            $otp = trim($_POST['otp']);
        } elseif(isset($_POST['otp_combined']) && !empty($_POST['otp_combined'])) {
            $otp = trim($_POST['otp_combined']);
        } else {
            // Try individual fields
            for($i = 1; $i <= 6; $i++) {
                if(isset($_POST['otp_' . $i])) {
                    $otp .= trim($_POST['otp_' . $i]);
                }
            }
        }
        
        // Remove any non-numeric characters
        $otp = preg_replace('/[^0-9]/', '', $otp);
        
        error_log("OTP entered: [" . $otp . "]");
        error_log("OTP length: " . strlen($otp));
        
        if(strlen($otp) != 6) {
            $error = "Please enter a valid 6-digit verification code";
        } else {
            // FIX: Get current OTP from database
            $stmt = $pdo->prepare("
                SELECT verification_code, code_expiry, is_verified 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            $db_user = $stmt->fetch();
            
            if($db_user) {
                error_log("DB Code: [" . ($db_user['verification_code'] ?? 'NULL') . "]");
                error_log("DB Expiry: " . ($db_user['code_expiry'] ?? 'NULL'));
                error_log("DB Verified: " . ($db_user['is_verified'] ?? 'NULL'));
                
                // FIX: Compare as strings
                $db_code = trim($db_user['verification_code'] ?? '');
                
                if($db_user['is_verified'] == 1) {
                    // Already verified - just redirect
                    $_SESSION['user_id'] = $user_id;
                    unset($_SESSION['temp_user_id']);
                    unset($_SESSION['temp_email']);
                    unset($_SESSION['temp_username']);
                    header("Location: home.php");
                    exit;
                }
                
                // FIX: Check expiry
                $is_expired = false;
                if($db_user['code_expiry']) {
                    $expiry_time = strtotime($db_user['code_expiry']);
                    $is_expired = $expiry_time < time();
                }
                
                if($is_expired) {
                    $error = "Verification code has expired. Please request a new one.";
                    error_log("❌ Code expired");
                }
                // FIX: String comparison with trimming
                elseif($db_code === $otp) {
                    error_log("✅ OTP VERIFICATION SUCCESSFUL for user: " . $user_id);
                    
                    try {
                        $pdo->beginTransaction();
                        
                        // Mark user as verified and add coins
                        $update = $pdo->prepare("
                            UPDATE users 
                            SET is_verified = 1, 
                                verification_code = NULL, 
                                code_expiry = NULL,
                                coins = coins + 50,
                                last_active = NOW()
                            WHERE id = ?
                        ");
                        $update_result = $update->execute([$user_id]);
                        
                        if($update_result) {
                            // Complete email verification task
                            $pdo->prepare("
                                UPDATE user_tasks 
                                SET completed = TRUE, completed_at = NOW() 
                                WHERE user_id = ? AND task_type = 'verify_email' AND completed = FALSE
                            ")->execute([$user_id]);
                            
                            $pdo->commit();
                            
                            // FIX: Set session properly
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user_id;
                            
                            // Clear temp session
                            unset($_SESSION['temp_user_id']);
                            unset($_SESSION['temp_email']);
                            unset($_SESSION['temp_username']);
                            
                            error_log("✅ User verified successfully, redirecting to verify_success.php");
                            
                            // FIX: Force redirect
                            echo '<script>window.location.href = "verify_success.php";</script>';
                            echo '<meta http-equiv="refresh" content="0;url=verify_success.php">';
                            header("Location: verify_success.php");
                            exit;
                        } else {
                            $pdo->rollBack();
                            $error = "Failed to update user status. Please try again.";
                        }
                    } catch(PDOException $e) {
                        $pdo->rollBack();
                        error_log("❌ Verification database error: " . $e->getMessage());
                        $error = "Verification failed. Please try again.";
                    }
                } else {
                    error_log("❌ VERIFICATION FAILED - Code mismatch");
                    $error = "Invalid verification code. Please check and try again.";
                }
            } else {
                $error = "User not found. Please register again.";
                error_log("❌ User not found in database: " . $user_id);
            }
        }
    }
}

// Handle resend OTP
if(isset($_GET['resend'])) {
    if(canResendOTP($pdo, $user_id)) {
        $otp = generateOTP();
        storeOTP($pdo, $user_id, $otp);
        $result = sendVerificationEmail($email, $username, $otp);
        
        if($result['success']) {
            updateResendTime($pdo, $user_id);
            $resend_success = "New verification code has been sent to your email!";
            error_log("✅ OTP resent to: " . $email);
        } else {
            $error = "Failed to send verification email. Please try again.";
        }
    } else {
        $error = "Please wait 60 seconds before requesting another code.";
    }
}

// Mask email for display
function maskEmail($email) {
    if(!$email) return '';
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    
    if(strlen($name) > 2) {
        $masked = substr($name, 0, 2) . str_repeat('*', strlen($name) - 2);
    } else {
        $masked = $name[0] . '**';
    }
    
    return $masked . '@' . $domain;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - SUGO Chat</title>
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
        
        .verify-container {
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        
        .verify-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary), #10b981);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }
        
        .email-icon {
            width: 80px;
            height: 80px;
            background: rgba(99, 102, 241, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            border: 2px solid var(--primary);
        }
        
        .email-icon i {
            font-size: 2.5rem;
            color: var(--primary);
        }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 30px 0;
        }
        
        .otp-input {
            width: 60px;
            height: 70px;
            background: rgba(15, 23, 42, 0.8);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-size: 2rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .otp-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            outline: none;
            transform: scale(1.05);
        }
        
        .timer {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
        }
        
        .timer.warning {
            color: #f59e0b;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 14px;
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
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #86efac;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .email-masked {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            color: white;
            word-break: break-all;
        }
        
        .resend-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .resend-link:hover {
            color: #a5b4fc;
            text-decoration: underline;
        }
        
        .resend-link.disabled {
            color: var(--gray);
            pointer-events: none;
            opacity: 0.6;
        }
        
        .otp-container {
            margin-bottom: 20px;
        }
        
        .otp-label {
            color: #e5e7eb;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .otp-main-input {
            background: #0f172a;
            border: 2px solid #6366f1;
            color: white;
            font-size: 2rem;
            letter-spacing: 10px;
            text-align: center;
            padding: 15px;
            border-radius: 12px;
            width: 100%;
        }
        
        .otp-main-input:focus {
            background: #1e293b;
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.3);
            outline: none;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="logo">SUGO</div>
            
            <div class="email-icon">
                <i class="fas fa-envelope"></i>
            </div>
            
            <h2 class="text-center text-white mb-3">Verify Your Email</h2>
            <p class="text-center text-muted mb-4">
                We've sent a 6-digit verification code to:
            </p>
            
            <div class="email-masked text-center mb-4">
                <i class="fas fa-envelope me-2"></i>
                <?= htmlspecialchars(maskEmail($email)) ?>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if($resend_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?= htmlspecialchars($resend_success) ?>
                </div>
            <?php endif; ?>
            
            <!-- FIXED: Simple form that WILL work -->
            <form method="POST" action="" id="verifyForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="verify" value="1">
                
                <div class="otp-container">
                    <label class="otp-label">Enter 6-digit verification code</label>
                    <input type="text" 
                           name="otp" 
                           class="otp-main-input" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           inputmode="numeric"
                           placeholder="123456"
                           autocomplete="off"
                           autofocus
                           required>
                    <small class="text-muted d-block mt-2 text-center">
                        <i class="fas fa-info-circle"></i> Enter the 6-digit code from your email
                    </small>
                </div>
                
                <!-- Hidden OTP combined field for compatibility -->
                <input type="hidden" name="otp_combined" id="otp_combined">
                
                <button type="submit" name="verify" class="btn btn-primary mb-3 py-3" id="verifyBtn">
                    <i class="fas fa-check-circle me-2"></i>Verify Email
                </button>
            </form>
            
            <div class="text-center mb-4">
                <div class="timer" id="timer">
                    <i class="fas fa-clock me-2"></i>
                    <span id="timerText">01:00</span>
                </div>
            </div>
            
            <div class="text-center">
                <p class="text-muted mb-2">Didn't receive the code?</p>
                <a href="?resend=1" class="resend-link" id="resendLink">
                    <i class="fas fa-redo-alt me-2"></i>Resend Code
                </a>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Code expires in 10 minutes. Check spam folder.
                </small>
            </div>
            
            <!-- Debug info - only shown when debug parameter is present -->
            <?php if(isset($_GET['debug'])): ?>
            <div class="mt-4 p-3 bg-dark rounded" style="font-size: 0.8rem;">
                <strong>Debug Info:</strong><br>
                User ID: <?= $user_id ?><br>
                Email: <?= $email ?><br>
                Username: <?= $username ?><br>
                Session: <pre><?= print_r($_SESSION, true) ?></pre>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // FIXED: OTP Verification JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('verifyForm');
            const verifyBtn = document.getElementById('verifyBtn');
            const otpInput = document.querySelector('input[name="otp"]');
            const otpCombined = document.getElementById('otp_combined');
            const timerElement = document.getElementById('timerText');
            const resendLink = document.getElementById('resendLink');
            
            // Auto-focus on the OTP input
            if(otpInput) {
                otpInput.focus();
            }
            
            // Update hidden combined field when OTP changes
            if(otpInput && otpCombined) {
                otpInput.addEventListener('input', function() {
                    otpCombined.value = this.value;
                });
            }
            
            // Form submit handler - FIXED
            form.addEventListener('submit', function(e) {
                const otpValue = otpInput ? otpInput.value.trim() : '';
                
                if(otpValue.length !== 6 || !/^\d+$/.test(otpValue)) {
                    e.preventDefault();
                    alert('Please enter a valid 6-digit verification code (numbers only)');
                    return false;
                }
                
                // Disable button to prevent double submission
                verifyBtn.disabled = true;
                verifyBtn.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Verifying...
                `;
                
                return true;
            });
            
            // Timer functionality
            let timeLeft = 60;
            let timerInterval;
            
            function startTimer() {
                clearInterval(timerInterval);
                timerInterval = setInterval(function() {
                    timeLeft--;
                    
                    if(timeLeft <= 0) {
                        clearInterval(timerInterval);
                        timerElement.textContent = '00:00';
                        if(resendLink) {
                            resendLink.classList.remove('disabled');
                            resendLink.style.pointerEvents = 'auto';
                            resendLink.style.opacity = '1';
                        }
                        document.querySelector('.timer')?.classList.remove('warning');
                    } else {
                        const minutes = Math.floor(timeLeft / 60);
                        const seconds = timeLeft % 60;
                        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                        
                        if(timeLeft < 30) {
                            document.querySelector('.timer')?.classList.add('warning');
                        }
                    }
                }, 1000);
            }
            
            // Start timer if resend was just clicked
            <?php if(isset($_GET['resend']) || !isset($_SESSION['last_resend_' . $user_id])): ?>
                timeLeft = 60;
                if(resendLink) {
                    resendLink.classList.add('disabled');
                    resendLink.style.pointerEvents = 'none';
                    resendLink.style.opacity = '0.6';
                }
                startTimer();
            <?php endif; ?>
            
            // Prevent resend link click if disabled
            if(resendLink) {
                resendLink.addEventListener('click', function(e) {
                    if(resendLink.classList.contains('disabled')) {
                        e.preventDefault();
                        alert('Please wait 60 seconds before requesting another code.');
                    }
                });
            }
            
            // Handle resend success
            <?php if($resend_success): ?>
                timeLeft = 60;
                if(resendLink) {
                    resendLink.classList.add('disabled');
                    resendLink.style.pointerEvents = 'none';
                    resendLink.style.opacity = '0.6';
                }
                clearInterval(timerInterval);
                startTimer();
                
                // Auto-hide success message after 5 seconds
                setTimeout(function() {
                    const alerts = document.querySelectorAll('.alert-success');
                    alerts.forEach(function(alert) {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(function() {
                            if(alert.parentNode) alert.remove();
                        }, 500);
                    });
                }, 5000);
            <?php endif; ?>
            
            // Auto-hide error messages after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert-danger');
                alerts.forEach(function(alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        if(alert.parentNode) alert.remove();
                    }, 500);
                });
            }, 5000);
            
            // Allow only numbers in OTP input
            if(otpInput) {
                otpInput.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    if(!/[0-9]/.test(char)) {
                        e.preventDefault();
                    }
                });
                
                otpInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    const numbersOnly = pasteData.replace(/[^0-9]/g, '').substring(0, 6);
                    this.value = numbersOnly;
                    if(otpCombined) otpCombined.value = numbersOnly;
                });
            }
        });
    </script>
</body>
</html>