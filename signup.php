<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "outfit_db");

$message = "";

if(isset($_POST['signup'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $profilePicPath = '';

    if(isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $fileType = $_FILES['profile_pic']['type'];

        if(in_array($fileType, $allowedTypes)) {
            $uploadDir = __DIR__ . '/images/';
            if(!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $newFileName = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $targetFile = $uploadDir . $newFileName;

            if(move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
                $profilePicPath = 'images/' . $newFileName;
            } else {
                $message = 'Unable to save uploaded profile image.';
            }
        } else {
            $message = 'Profile picture must be JPG, PNG, or WEBP format.';
        }
    }

    if($message === '') {
        $columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
        if($columnCheck && $columnCheck->num_rows === 0) {
            $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) NULL");
        }

        if($profilePicPath === '') {
            $profilePicPath = 'https://via.placeholder.com/120/111827/ffffff?text=User';
        }

        $check = "SELECT * FROM users WHERE email='$email'";
        $result = $conn->query($check);

        if($result->num_rows > 0) {
            $message = "already_registered";
        } else {
            $sql = "INSERT INTO users (email, password, profile_pic) VALUES ('$email', '$password', '$profilePicPath')";
            
            if($conn->query($sql) === TRUE) {
                $_SESSION['user'] = $email;
                header("Location: home.php");
                exit();
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Signup</title>
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

.box h1 {
    margin: 0 0 10px;
    font-size: 28px;
    letter-spacing: -0.04em;
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

.login-link {
    margin-top: 20px;
    color: rgba(255,255,255,0.75);
    font-size: 14px;
}


input,
input[type="file"] {
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

input[type="file"] {
    padding: 14px 16px;
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

a {
    color: #8bd5ff;
    text-decoration: none;
}

a:hover {
    color: #d1e9ff;
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

    input,
    input[type="file"] {
        max-width: 100%;
        padding: 14px 16px;
        font-size: 15px;
        border-radius: 16px;
        box-sizing: border-box;
    }

    button {
        padding: 14px 0;
        font-size: 15px;
    }

    .login-link {
        font-size: 13px;
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
    <h2>Join Us</h2>
    <p class="subtitle">Create your account and upload a profile image to personalize your dashboard experience.</p>

    <form method="post" enctype="multipart/form-data">
        <input type="email" name="email" placeholder="Enter your email" required>
        <input type="password" name="password" placeholder="Create a password" required>
        <label for="profile_pic" style="display:block; text-align:center; margin-top: 16px; color: rgba(255,255,255,0.75); font-size: 14px; font-weight: 500;">Select profile image</label>
        <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
        <button type="submit" name="signup">Create Account</button>
    </form>

    <?php if($message === 'already_registered'): ?>
        <p class="message warning">⚠️ This email is already registered.<br>Please <a href="login.php">Sign In</a> instead.</p>
    <?php elseif($message !== ''): ?>
        <p class="message"><?php echo $message; ?></p>
    <?php endif; ?>

    <p class="login-link">Already have an account? <a href="login.php">Sign In</a></p>
</div>

</body>
</html>