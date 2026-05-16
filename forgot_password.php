<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata');

$conn = new mysqli("localhost", "root", "", "outfit_db");
require_once 'send_mail.php';

$message = "";
$messageType = "";

if(isset($_POST['request_reset'])) {
    $email = $conn->real_escape_string($_POST['email']);
    
    $check = $conn->query("SELECT * FROM users WHERE email='$email'");
    if($check->num_rows > 0) {
        $otp = rand(100000, 999999);
        $otp_expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        
        $conn->query("UPDATE users SET otp='$otp', otp_expiry='$otp_expiry' WHERE email='$email'");
        
        if(sendOTP($email, $otp)) {
            $_SESSION['reset_email'] = $email;
            header("Location: reset_password.php");
            exit();
        } else {
            $message = "Failed to send OTP. Please try again later.";
        }
    } else {
        $message = "No account found with this email.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Forgot Password</title>
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
    opacity: 0.95;
}

.message {
    margin-top: 16px;
    font-weight: 600;
    color: #ff7b7b;
    font-size: 14px;
}

a {
    color: #8bd5ff;
    text-decoration: none;
    font-size: 14px;
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
    <h2>Reset Password</h2>
    <p class="subtitle">Enter your registered email address and we'll send you an OTP to reset your password.</p>

    <form method="post">
        <input type="email" name="email" placeholder="Enter your email" required autofocus>
        <button type="submit" name="request_reset">Send OTP →</button>
    </form>

    <?php if($message !== ''): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
    
    <p style="margin-top: 24px;">
        <a href="login.php">← Back to Login</a>
    </p>
</div>

</body>
</html>
