<?php
// test_verification.php - Simple test script
require 'config.php';

echo "<h1>OTP Verification Test</h1>";

// Get the most recent unverified user
$stmt = $pdo->prepare("SELECT id, username, email, verification_code, code_expiry, is_verified FROM users WHERE is_verified = 0 ORDER BY id DESC LIMIT 1");
$stmt->execute();
$user = $stmt->fetch();

if($user) {
    echo "<div style='background: #1a1a2e; color: white; padding: 20px; border-radius: 10px; margin: 20px;'>";
    echo "<h3>Latest Unverified User:</h3>";
    echo "<p><strong>User ID:</strong> " . $user['id'] . "</p>";
    echo "<p><strong>Username:</strong> " . $user['username'] . "</p>";
    echo "<p><strong>Email:</strong> " . $user['email'] . "</p>";
    echo "<p><strong>OTP Code:</strong> <span style='font-size: 24px; background: #0f172a; padding: 10px; border-radius: 5px; letter-spacing: 5px;'>" . $user['verification_code'] . "</span></p>";
    echo "<p><strong>Expires:</strong> " . $user['code_expiry'] . "</p>";
    echo "<p><strong>Is Verified:</strong> " . ($user['is_verified'] ? 'Yes' : 'No') . "</p>";
    
    echo "<hr>";
    echo "<h4>Quick Verify Button:</h4>";
    echo "<form method='post' action='verify_otp.php' style='margin-top: 10px;'>";
    echo "<input type='hidden' name='csrf_token' value='" . $_SESSION['csrf_token'] . "'>";
    echo "<input type='hidden' name='otp' value='" . $user['verification_code'] . "'>";
    echo "<button type='submit' name='verify' style='background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;'>Verify This User Now</button>";
    echo "</form>";
    
    echo "<hr>";
    echo "<h4>Direct Verification Link:</h4>";
    echo "<p><a href='verify_otp.php?debug=1' style='color: #6366f1;'>Go to OTP Page</a></p>";
    echo "</div>";
} else {
    echo "<p style='color: red;'>No unverified users found. Please register first.</p>";
}

// Show session data
echo "<div style='background: #1e293b; color: white; padding: 20px; border-radius: 10px; margin: 20px;'>";
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
echo "</div>";
?>