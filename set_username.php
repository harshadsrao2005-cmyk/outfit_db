<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "outfit_db");
$userEmail = $_SESSION['user'];

// Ensure user is verified
$verifyCheck = $conn->query("SELECT is_verified FROM users WHERE email='$userEmail'");
if($row = $verifyCheck->fetch_assoc()) {
    if($row['is_verified'] == 0) {
        header("Location: verify_otp.php");
        exit();
    }
} else {
    // User not found in DB
    header("Location: logout.php");
    exit();
}

$message = "";

// Ensure username column exists
$columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'username'");
if($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL");
}

if(isset($_POST['set_username'])) {
    $username = trim($conn->real_escape_string($_POST['username']));

    if($username === '') {
        $message = "Please enter a username.";
    } else {
        $email = $_SESSION['user'];
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE email = ?");
        $stmt->bind_param('ss', $username, $email);

        if($stmt->execute()) {
            header("Location: home.php");
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Set Username</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

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

.welcome-emoji {
    font-size: 56px;
    margin-bottom: 16px;
    display: block;
    animation: wave 2s ease-in-out infinite;
}

@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(15deg); }
    75% { transform: rotate(-10deg); }
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
    box-sizing: border-box;
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

/* ========== RESPONSIVE ========== */
@media screen and (max-width: 480px) {
    body {
        padding: 16px;
        align-items: flex-start;
        padding-top: 40px;
    }

    .box {
        padding: 28px 20px;
        border-radius: 24px;
    }

    .logo-icon {
        font-size: 26px;
    }

    .logo h1 {
        font-size: 20px;
    }

    .box h2 {
        font-size: 20px;
    }

    .subtitle {
        font-size: 13px;
        margin-bottom: 22px;
    }

    input {
        max-width: 100%;
        padding: 14px 16px;
        font-size: 15px;
        border-radius: 16px;
    }

    button {
        padding: 14px 0;
        font-size: 15px;
    }

    .welcome-emoji {
        font-size: 44px;
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
    <span class="welcome-emoji">👋</span>
    <h2>Almost There!</h2>
    <p class="subtitle">Choose a username that will be displayed on your profile. You can pick anything you like!</p>

    <form method="post">
        <input type="text" name="username" placeholder="Enter your username" required autofocus maxlength="50">
        <button type="submit" name="set_username">Continue →</button>
    </form>

    <?php if($message !== ''): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>
</div>

</body>
</html>
