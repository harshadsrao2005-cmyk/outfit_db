<?php
session_start();
$conn = new mysqli("localhost", "root", "", "outfit_db");

$message = "";

if(isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $check_email = "SELECT * FROM users WHERE email='$email'";
    $email_result = $conn->query($check_email);

    if($email_result->num_rows == 0) {
        $message = "signup_first";
    } else {
        $sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
        $result = $conn->query($sql);

        if($result->num_rows > 0) {
            $_SESSION['user'] = $email;

            // 👉 Redirect to home page
            header("Location: home.php");
            exit();
        } else {
            $message = "invalid_password";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>

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

.signup-link {
    margin-top: 20px;
    color: rgba(255,255,255,0.75);
    font-size: 14px;
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
    transition: border-color 0.3s ease, background 0.3s ease, box-shadow 0.3s ease;
    display: block;
}

input::placeholder {
    color: rgba(255, 255, 255, 0.55);
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

button:active {
    transform: translateY(0);
}


.message {
    margin-top: 16px;
    font-weight: 600;
    color: #ff7b7b;
    font-size: 14px;
    line-height: 1.6;
}

.message.warning {
    color: #ffd866;
}

.message a {
    color: #4ac3ff;
    text-decoration: underline;
    font-weight: 700;
}

.message a:hover {
    color: #8bd5ff;
}

.success {
    color: #8be7a8;
}

a {
    color: #8bd5ff;
    text-decoration: none;
}

a:hover {
    color: #d1e9ff;
}
</style>

</head>

<body>

<div class="box">
    <div class="logo">
        <span class="logo-icon">👗</span>
        <h1>Outfit Recommender</h1>
    </div>
    <h2>Welcome Back</h2>
    <p class="subtitle">Sign in to your account and discover personalized outfit recommendations powered by AI.</p>

    <form method="post">
        <input type="email" name="email" placeholder="Enter your email" required>
        <input type="password" name="password" placeholder="Enter your password" required>
        <button type="submit" name="login">Sign In</button>
    </form>

    <?php if($message === 'signup_first'): ?>
        <p class="message warning">⚠️ No account found with this email.<br>Please <a href="signup.php">Sign Up</a> first before logging in.</p>
    <?php elseif($message === 'invalid_password'): ?>
        <p class="message">❌ Incorrect password. Please try again.</p>
    <?php endif; ?>

    <p class="signup-link">New here? <a href="signup.php">Create an account</a></p>
</div>

</body>
</html>