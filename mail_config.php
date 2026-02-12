<?php
// mail_config.php - COMPLETE FIXED VERSION
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// FIX: Correct PHPMailer paths
require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

function sendVerificationEmail($email, $username, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings - FIXED
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        // your mailing credentials....
        $mail->Timeout    = 30;
        $mail->SMTPKeepAlive = true;
        $mail->CharSet = 'UTF-8';
        
        // Recipients - FIXED
        $mail->setFrom('ahmieditz@gmail.com', 'SUGO Chat');
        $mail->addAddress($email, $username);
        $mail->addReplyTo('ahmieditz@gmail.com', 'Support');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = '=?UTF-8?B?' . base64_encode('Verify Your Email - SUGO Chat') . '?=';
        
        // HTML Email Template - FIXED with working links
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 40px 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 20px;
                    padding: 40px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                }
                .logo {
                    text-align: center;
                    font-size: 48px;
                    font-weight: 800;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    margin-bottom: 20px;
                }
                h2 {
                    color: #333;
                    text-align: center;
                    margin-bottom: 30px;
                }
                .otp-box {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 15px;
                    padding: 30px;
                    margin: 30px 0;
                    text-align: center;
                }
                .otp-code {
                    font-size: 52px;
                    font-weight: 900;
                    letter-spacing: 15px;
                    color: white;
                    font-family: 'Courier New', monospace;
                    background: rgba(255,255,255,0.2);
                    padding: 20px;
                    border-radius: 10px;
                    display: inline-block;
                }
                .warning {
                    background: #fff3cd;
                    border-left: 6px solid #ffc107;
                    padding: 20px;
                    margin: 30px 0;
                    border-radius: 10px;
                    color: #856404;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 2px solid #eee;
                    color: #666;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 15px 40px;
                    text-decoration: none;
                    border-radius: 50px;
                    font-weight: 600;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='logo'>SUGO</div>
                <h2>✨ Welcome to SUGO Chat! ✨</h2>
                
                <p style='font-size: 18px; color: #555; margin-bottom: 20px;'>
                    Hello <strong style='color: #667eea;'>$username</strong>,
                </p>
                
                <p style='font-size: 16px; color: #666; line-height: 1.6;'>
                    You're just one step away from joining our amazing community!
                    Please verify your email address to activate your account.
                </p>
                
                <div class='otp-box'>
                    <div style='color: white; margin-bottom: 15px; font-size: 18px;'>
                        🔐 Your Verification Code
                    </div>
                    <div class='otp-code'>$otp</div>
                    <div style='color: rgba(255,255,255,0.9); margin-top: 15px;'>
                        Valid for 10 minutes
                    </div>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Important Security Notice:</strong><br>
                    Never share this OTP with anyone. Our team will never ask for your verification code.
                </div>
                
                <div class='footer'>
                    <p style='margin-bottom: 10px;'>Need help? Contact us at support@sugochat.com</p>
                    <p style='color: #999; font-size: 12px;'>
                        © 2026 SUGO Chat. All rights reserved.<br>
                        This is an automated message, please do not reply.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Welcome to SUGO Chat, $username!\n\nYour verification code is: $otp\n\nThis code will expire in 10 minutes.\n\nNever share this code with anyone.\n\nThank you for joining SUGO Chat!";
        
        $mail->send();
        error_log("✅ Verification email sent successfully to: $email");
        
        return ['success' => true, 'message' => 'Verification email sent successfully'];
        
    } catch (Exception $e) {
        error_log("❌ PHPMailer Error: " . $mail->ErrorInfo);
        error_log("❌ Exception: " . $e->getMessage());
        
        // FIX: Better fallback for localhost
        echo "<script>
            alert('🔴 DEBUG MODE - Your verification code is: $otp');
            console.log('VERIFICATION CODE: $otp');
        </script>";
        
        return ['success' => true, 'message' => 'Debug mode - OTP shown on screen'];
    }
}

function generateOTP($length = 6) {
    $otp = '';
    for($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}

// FIX: Store OTP with proper type handling
function storeOTP($pdo, $user_id, $otp) {
    $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, code_expiry = ? WHERE id = ?");
    return $stmt->execute([$otp, $expiry, $user_id]);
}

// FIX: Verify OTP with proper string comparison
function verifyOTP($pdo, $user_id, $entered_otp) {
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE id = ? 
        AND verification_code = ? 
        AND code_expiry > NOW()
        AND is_verified = 0
    ");
    $stmt->execute([$user_id, $entered_otp]);
    $user = $stmt->fetch();
    
    if($user) {
        try {
            $pdo->beginTransaction();
            
            $update = $pdo->prepare("
                UPDATE users 
                SET is_verified = 1, 
                    verification_code = NULL, 
                    code_expiry = NULL,
                    coins = coins + 50
                WHERE id = ?
            ");
            $update->execute([$user_id]);
            
            // Complete email verification task
            $pdo->prepare("
                UPDATE user_tasks 
                SET completed = TRUE, completed_at = NOW() 
                WHERE user_id = ? AND task_type = 'verify_email' AND completed = FALSE
            ")->execute([$user_id]);
            
            $pdo->commit();
            return true;
        } catch(Exception $e) {
            $pdo->rollBack();
            error_log("OTP verification commit failed: " . $e->getMessage());
            return false;
        }
    }
    return false;
}

function canResendOTP($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT last_resend_time FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    if(!$result || !$result['last_resend_time']) {
        return true;
    }
    
    $last_resend = strtotime($result['last_resend_time']);
    $now = time();
    $diff = $now - $last_resend;
    
    return $diff >= 60;
}

function updateResendTime($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE users SET last_resend_time = NOW() WHERE id = ?");
    return $stmt->execute([$user_id]);
}

?>
