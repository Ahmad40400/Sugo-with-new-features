<?php
// resend_otp.php
require 'config.php';
require 'mail_config.php';

if(!isset($_SESSION['temp_user_id'])) {
    header("Location: register.php");
    exit;
}

$user_id = $_SESSION['temp_user_id'];
$email = $_SESSION['temp_email'];
$username = $_SESSION['temp_username'];

if(canResendOTP($pdo, $user_id)) {
    $otp = generateOTP();
    storeOTP($pdo, $user_id, $otp);
    $result = sendVerificationEmail($email, $username, $otp);
    
    if($result['success']) {
        updateResendTime($pdo, $user_id);
        $_SESSION['resend_success'] = "New verification code sent!";
    } else {
        $_SESSION['resend_error'] = "Failed to send email. Please try again.";
    }
} else {
    $_SESSION['resend_error'] = "Please wait 60 seconds before requesting another code.";
}

header("Location: verify_otp.php");
exit;
?>