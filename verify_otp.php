<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Set timezone to IST for India
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli("localhost", "root", "", "outfit_db");
require_once 'send_mail.php';
$message = "";
$messageType = "";
$userEmail = $_SESSION['user'];

// Check if user is already verified
$check = $conn->query("SELECT is_verified FROM users WHERE email='$userEmail'");
if($check && $row = $check->fetch_assoc()) {
    if($row['is_verified'] == 1) {
        header("Location: set_username.php");
        exit();
    }
}

if(isset($_POST['verify_otp'])) {
    $otpInput = trim($_POST['otp']);
    
    $stmt = $conn->prepare("SELECT otp, otp_expiry FROM users WHERE email = ?");
    $stmt->bind_param('s', $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        $dbOtp = $row['otp'];
        $expiry = $row['otp_expiry'];
        $currentTime = date('Y-m-d H:i:s');
        
        if($otpInput == $dbOtp) {
            if($currentTime <= $expiry) {
                // OTP matches and not expired
                $conn->query("UPDATE users SET is_verified = 1, otp = NULL, otp_expiry = NULL WHERE email = '$userEmail'");
                header("Location: set_username.php");
                exit();
            } else {
                $message = "OTP has expired. Please request a new one.";
                $messageType = "warning";
            }
        } else {
            $message = "Invalid OTP. Please try again.";
        }
    }
    $stmt->close();
}

if(isset($_POST['resend_otp'])) {
    $newOtp = rand(100000, 999999);
    $newExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    
    $update = $conn->query("UPDATE users SET otp='$newOtp', otp_expiry='$newExpiry' WHERE email='$userEmail'");
    if($update) {
        // Send OTP Email using PHPMailer
        sendOTP($userEmail, $newOtp);
        
        $message = "A new code has been sent to your email.";
        $messageType = "success";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Verify OTP</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #090b12, #12182d, #1d2a48, #0b1324);
    background-size: 400% 400%;
    animation: gradientBG 14s ease infinite;
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    padding: 24px;
}

@keyframes gradientBG {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.box {
    width: min(450px, 100%);
    padding: 38px 36px;
    border-radius: 32px;
    background: rgba(8, 16, 34, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 28px 80px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(16px);
    text-align: center;
}

.logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
}

.logo-icon {
    font-size: 32px;
    margin-right: 12px;
}

.logo h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.02em;
}

.box h2 {
    margin: 0 0 12px;
    font-size: 22px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.9);
}

.subtitle {
    margin: 0 0 28px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 15px;
    line-height: 1.6;
    max-width: 320px;
    margin-left: auto;
    margin-right: auto;
}

.otp-emoji {
    font-size: 56px;
    margin-bottom: 16px;
    display: block;
}

input {
    width: 100%;
    max-width: 280px;
    padding: 18px;
    margin: 14px auto;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(255,255,255,0.08);
    color: white;
    font-size: 24px;
    letter-spacing: 12px;
    text-align: center;
    font-weight: 700;
    outline: none;
    transition: all 0.3s ease;
    display: block;
    box-sizing: border-box;
}

input:focus {
    border-color: rgba(74, 195, 255, 0.8);
    background: rgba(255,255,255,0.12);
    box-shadow: 0 0 0 4px rgba(74, 195, 255, 0.15);
}

button {
    width: 100%;
    padding: 16px 0;
    margin-top: 16px;
    border-radius: 999px;
    border: none;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    color: white;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 40px rgba(42, 126, 255, 0.3);
    opacity: 0.95;
}

.resend-btn {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    margin-top: 12px;
    font-weight: 500;
    color: rgba(255,255,255,0.7);
}

.resend-btn:hover {
    background: rgba(255,255,255,0.05);
    border-color: rgba(255,255,255,0.4);
    color: white;
    box-shadow: none;
}

.message {
    margin-top: 20px;
    font-weight: 500;
    color: #ff7b7b;
    font-size: 14px;
    padding: 12px;
    border-radius: 12px;
    background: rgba(255, 123, 123, 0.1);
}

.message.success {
    color: #4affb1;
    background: rgba(74, 255, 177, 0.1);
}

.message.warning {
    color: #ffd866;
    background: rgba(255, 216, 102, 0.1);
}

@media screen and (max-width: 480px) {
    .box {
        padding: 28px 20px;
    }
}
</style>
</head>
<body>

<div class="box">
    <div class="logo">
        <span class="logo-icon">👗</span>
        <h1>Outfit Recommender</h1>
    </div>
    <span class="otp-emoji">📧</span>
    <h2>Verify Email</h2>
    <p class="subtitle">We've sent a 6-digit code to <b><?php echo htmlspecialchars($userEmail); ?></b>. Enter it below to continue.</p>


    <form method="post">
        <input type="text" name="otp" placeholder="000000" required autofocus maxlength="6" pattern="\d{6}">
        <button type="submit" name="verify_otp">Verify & Continue</button>
    </form>
    
    <form method="post">
        <button type="submit" name="resend_otp" class="resend-btn">Resend Code</button>
    </form>

    <?php if($message !== ''): ?>
        <p class="message <?php echo $messageType; ?>"><?php echo $message; ?></p>
    <?php endif; ?>
    
    <p style="margin-top: 24px; font-size: 13px; color: rgba(255,255,255,0.5);">
        Wrong email? <a href="signup.php" style="color: #4ac3ff; text-decoration: none;">Go back</a>
    </p>
</div>

</body>
</html>
