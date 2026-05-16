<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

if(!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "outfit_db");
require_once 'send_mail.php';

$message = "";
$messageType = "";
$email = $_SESSION['reset_email'];
$otpVerified = false;

if(isset($_POST['verify_otp'])) {
    $otpInput = trim($_POST['otp']);
    
    $stmt = $conn->prepare("SELECT otp, otp_expiry FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($row = $result->fetch_assoc()) {
        if($otpInput == $row['otp'] && date('Y-m-d H:i:s') <= $row['otp_expiry']) {
            $otpVerified = true;
            $_SESSION['otp_verified'] = true;
        } else {
            $message = "Invalid or expired OTP.";
        }
    }
}

if(isset($_POST['reset_password'])) {
    if(!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        $message = "Please verify your OTP first.";
    } else {
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        
        if($newPass === $confirmPass) {
            $stmt = $conn->prepare("UPDATE users SET password = ?, otp = NULL, otp_expiry = NULL WHERE email = ?");
            $stmt->bind_param('ss', $newPass, $email);
            if($stmt->execute()) {
                unset($_SESSION['reset_email']);
                unset($_SESSION['otp_verified']);
                header("Location: login.php?reset=success");
                exit();
            } else {
                $message = "Error updating password.";
            }
        } else {
            $otpVerified = true; // Keep the password form visible
            $message = "Passwords do not match.";
        }
    }
}

$showPasswordForm = $otpVerified || (isset($_SESSION['otp_verified']) && $_SESSION['otp_verified'] === true);
?>

<!DOCTYPE html>
<html>
<head>
<title>Reset Password</title>
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
}

input {
    width: 100%;
    max-width: 320px;
    padding: 16px 18px;
    margin: 14px auto;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.18);
    background: rgba(255,255,255,0.08);
    color: white;
    font-size: 16px;
    outline: none;
    transition: all 0.3s ease;
    display: block;
    box-sizing: border-box;
}

input:focus {
    border-color: rgba(74, 195, 255, 0.8);
    background: rgba(255,255,255,0.12);
    box-shadow: 0 0 0 3px rgba(74, 195, 255, 0.15);
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
}

.message {
    margin-top: 16px;
    font-weight: 600;
    color: #ff7b7b;
    font-size: 14px;
}

.otp-input {
    letter-spacing: 8px;
    text-align: center;
    font-size: 24px;
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
    
    <?php if(!$showPasswordForm): ?>
        <h2>Verify OTP</h2>
        <p class="subtitle">Enter the 6-digit code sent to <b><?php echo htmlspecialchars($email); ?></b></p>

        <form method="post">
            <input type="text" name="otp" class="otp-input" placeholder="000000" required autofocus maxlength="6">
            <button type="submit" name="verify_otp">Verify Code →</button>
        </form>
    <?php else: ?>
        <h2>New Password</h2>
        <p class="subtitle">Set a strong password for your account.</p>

        <form method="post">
            <input type="password" name="new_password" placeholder="New Password" required autofocus>
            <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
            <button type="submit" name="reset_password">Reset Password →</button>
        </form>
    <?php endif; ?>

    <?php if($message !== ''): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
</div>

</body>
</html>
