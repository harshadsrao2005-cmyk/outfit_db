<?php
session_start();

if(!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "outfit_db");
$profileImg = 'images/default_avatar.png';
$userEmail = $_SESSION['user'];
$successMessage = '';
$errorMessage = '';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$columnCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
if($columnCheck && $columnCheck->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) NULL");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        $fileType = $_FILES['profile_pic']['type'];

        if (in_array($fileType, $allowedTypes)) {
            $uploadDir = __DIR__ . '/images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $newFileName = 'profile_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
            $targetFile = $uploadDir . $newFileName;
            $newPath = 'images/' . $newFileName;

            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetFile)) {
                $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE email = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param('ss', $newPath, $userEmail);
                    if ($updateStmt->execute()) {
                        $successMessage = 'Profile photo updated successfully.';
                        $profileImg = $newPath;
                    } else {
                        $errorMessage = 'Could not update profile photo in the database.';
                    }
                    $updateStmt->close();
                } else {
                    $errorMessage = 'Failed to prepare database update.';
                }
            } else {
                $errorMessage = 'Unable to move uploaded file.';
            }
        } else {
            $errorMessage = 'Profile picture must be JPG, PNG, or WEBP format.';
        }
    } else {
        $errorMessage = 'Please select a profile picture to upload.';
    }
}

$displayName = $userEmail;
$profileStmt = $conn->prepare("SELECT profile_pic, username FROM users WHERE email = ?");
if ($profileStmt) {
    $profileStmt->bind_param('s', $userEmail);
    $profileStmt->execute();
    $profileStmt->bind_result($profilePic, $dbUsername);
    if ($profileStmt->fetch()) {
        if (!empty($profilePic)) {
            $profileImg = $profilePic;
        }
        if (!empty($dbUsername)) {
            $displayName = $dbUsername;
        }
    }
    $profileStmt->close();
}

// Initialize history
if(!isset($_SESSION['history'])) {
    $_SESSION['history'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_history') {
    header('Content-Type: application/json');
    $item = trim($_POST['item'] ?? '');
    if ($item !== '') {
        array_unshift($_SESSION['history'], $item);
        if (count($_SESSION['history']) > 20) {
            $_SESSION['history'] = array_slice($_SESSION['history'], 0, 20);
        }
    }
    echo json_encode(['status' => 'ok', 'item' => $item]);
    exit();
}

$selectedOccasion = "";
if(isset($_POST['occasion'])) {
    $selectedOccasion = $_POST['occasion'];
}
?>

<!DOCTYPE html>
<html>
<head>
<title>E-Cart Recommender</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">

<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    overflow-x: hidden;
    background: linear-gradient(135deg, #090b12, #12182d, #1d2a48, #0b1324);
    background-size: 400% 400%;
    animation: gradientBG 14s ease infinite;
    color: white;
}

@keyframes gradientBG {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* TOP BAR */
.top-bar {
    position: relative;
    width: 100%;
    height: 100px;
    background: rgba(0,0,0,0.4);
}

/* MENU ICON (perfectly aligned left) */
.menu {
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 28px;
    cursor: pointer;
    
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    
    background: linear-gradient(135deg, #ff7eb3, #ff69b4);
    border-radius: 12px;
    color: white;
    
    box-shadow: 0 8px 20px rgba(255, 126, 179, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
    
    transition: all 0.3s ease;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    
    z-index: 100;
    position: relative;
}

.menu::before,
.menu::after {
    content: '';
    position: absolute;
    width: 28px;
    height: 3px;
    background: white;
    border-radius: 2px;
    transition: all 0.4s ease;
    left: 50%;
    transform: translateX(-50%);
}

.menu::before {
    top: 12px;
    box-shadow: 0 10px 0 white;
}

.menu::after {
    bottom: 12px;
}

.menu:hover {
    transform: translateY(-50%) scale(1.1);
    box-shadow: 0 12px 30px rgba(255, 126, 179, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.3),
                0 0 20px rgba(255, 215, 0, 0.5);
    background: linear-gradient(135deg, #ff69b4, #ff1493);
}

.menu:hover::before,
.menu:hover::after {
    background: #ffd700;
    box-shadow: 0 9px 0 #ffd700;
}

.menu:active {
    transform: translateY(-50%) scale(0.95);
}

/* CENTER TITLE */
.title {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
   
    text-align: center;
    font-size: 38px;
    font-weight: 900;
    color: white;
    font-family: 'Poppins', sans-serif;
    letter-spacing: 2px;
    line-height: 1.2;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
}




/* Sidebar hidden completely */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: #1e1e2f;
    padding: 20px;
    z-index: 999;

    transform: translateX(-100%);
    transition: 0.3s;

    overflow-y: auto;   /* 🔥 THIS IS THE SCROLL */
}

/* When active */
.sidebar.active {
    transform: translateX(0);       /* comes into screen */
}
.sidebar h2 {
    margin-bottom: 8px;
    font-size: 20px;
    letter-spacing: 0.03em;
}

.menu-section-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255,255,255,0.35);
    padding: 18px 10px 6px;
    font-weight: 700;
}

.menu-divider {
    height: 1px;
    background: rgba(255,255,255,0.08);
    margin: 10px 0;
}

.menu-item {
    padding: 11px 12px;
    cursor: pointer;
    border-radius: 10px;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s ease, transform 0.15s ease;
    margin-bottom: 2px;
}

.menu-item:hover {
    background: rgba(255,255,255,0.1);
    transform: translateX(3px);
}

.menu-item.active-item {
    background: linear-gradient(135deg, rgba(74,195,255,0.15), rgba(43,125,255,0.1));
    border: 1px solid rgba(74,195,255,0.2);
}

.menu-item a {
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
}

.menu-item.logout-item {
    color: #ff7b7b;
}

.menu-item.logout-item:hover {
    background: rgba(255,123,123,0.12);
}

/* OVERLAY (click outside) */
.overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: none;
    z-index: 998;
}

.overlay.active {
    display: block;
}

/* EXPANDABLE PANELS */
.history-panel,
.expandable-panel {
    display: none;
    margin-top: 8px;
    margin-bottom: 6px;
}

.history-item {
    background: rgba(255,255,255,0.08);
    padding: 9px 12px;
    margin-bottom: 5px;
    border-radius: 8px;
    font-size: 13px;
}

.panel-tip {
    background: rgba(255,255,255,0.06);
    border-left: 3px solid #4ac3ff;
    padding: 10px 12px;
    margin-bottom: 6px;
    border-radius: 0 8px 8px 0;
    font-size: 13px;
    color: rgba(255,255,255,0.8);
    line-height: 1.5;
}

.trend-item {
    background: rgba(255,255,255,0.06);
    padding: 10px 12px;
    margin-bottom: 5px;
    border-radius: 8px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.trend-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.trend-badge.hot { background: rgba(255,107,107,0.2); color: #ff7b7b; }
.trend-badge.new { background: rgba(74,195,255,0.2); color: #4ac3ff; }
.trend-badge.rising { background: rgba(255,216,102,0.2); color: #ffd866; }

.saved-empty {
    text-align: center;
    padding: 16px 10px;
    color: rgba(255,255,255,0.45);
    font-size: 13px;
}

.saved-outfit-card {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 8px;
    position: relative;
    transition: all 0.25s ease;
}

.saved-outfit-card:hover {
    background: rgba(255,255,255,0.12);
    border-color: rgba(255,126,179,0.3);
}

.saved-outfit-name {
    font-size: 12.5px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 6px;
    padding-right: 28px;
    line-height: 1.4;
}

.saved-outfit-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
}

.saved-platform-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.saved-platform-badge.amazon { background: rgba(255,153,0,0.2); color: #ffaa33; }
.saved-platform-badge.flipkart { background: rgba(47,128,237,0.2); color: #5ba3f5; }
.saved-platform-badge.meesho { background: rgba(243,68,130,0.2); color: #f56b9a; }

.saved-outfit-fabric {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    margin-bottom: 8px;
}

.saved-outfit-actions {
    display: flex;
    gap: 6px;
}

.saved-view-btn {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    color: white;
    text-decoration: none;
    font-size: 11px;
    font-weight: 600;
    transition: transform 0.2s ease;
}

.saved-view-btn:hover {
    transform: translateY(-1px);
}

.saved-remove-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: none;
    background: rgba(255,100,100,0.15);
    color: #ff7b7b;
    cursor: pointer;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    padding: 0;
    line-height: 1;
}

.saved-remove-btn:hover {
    background: rgba(255,100,100,0.3);
    transform: scale(1.15);
}

.saved-count-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: linear-gradient(135deg, #ff7eb3, #ff758c);
    color: white;
    font-size: 10px;
    font-weight: 700;
    margin-left: auto;
}

/* SEARCH */

.search-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
    margin-top: 80px;
}

.search-box {
    text-align: center;
    margin-top: 80px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 80px;
}

.search-box input {
    width: 320px;
    padding: 12px 18px;
    border-radius: 30px;
    border: none;
    outline: none;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    color: white;
}

.search-box input::placeholder {
    color: #eee;
}

.search-box button {
    padding: 12px 20px;
    border-radius: 30px;
    border: none;
    background: linear-gradient(45deg, #ff7eb3, #ff758c);
    color: white;
    cursor: pointer;
}

/* CAMERA */
.camera-container {
    text-align: center;
    margin-top: 80px;
}

.camera-btn {
    padding: 12px 20px;
    border-radius: 30px;
    border: none;
    background: linear-gradient(45deg, #ff7eb3, #ff758c);
    color: white;
    cursor: pointer;
    font-size: 18px;
    text-decoration: none;
    display: inline-block;
    transition: 0.3s;
}

.camera-btn:hover {
    transform: scale(1.05);
}

.content-section {
    max-width: 980px;
    margin: 35px auto 0;
    padding: 0 20px 40px;
}

.hero-card {
    background: rgba(8, 16, 34, 0.96);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 32px;
    padding: 40px 36px;
    display: grid;
    grid-template-columns: minmax(260px, 1.3fr) minmax(220px, 0.9fr);
    gap: 32px;
    box-shadow: 0 34px 90px rgba(0, 0, 0, 0.32);
    backdrop-filter: blur(18px);
}

.hero-copy h1 {
    margin: 0 0 18px;
    font-size: 44px;
    line-height: 1.05;
    letter-spacing: -0.04em;
    color: #f7fbff;
}

.hero-copy p {
    margin: 0;
    max-width: 720px;
    color: rgba(255, 255, 255, 0.82);
    font-size: 17px;
    line-height: 1.85;
}

.hero-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 24px;
}

.hero-badges span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    border-radius: 999px;
    background: rgba(74, 195, 255, 0.12);
    color: #a7e1ff;
    font-size: 13px;
    letter-spacing: 0.02em;
}

.hero-right-card {
    display: grid;
    gap: 18px;
    align-content: start;
}

.hero-stat {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 22px;
    padding: 20px 22px;
}

.stat-value {
    font-size: 34px;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 8px;
}

.stat-label {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.72);
}

.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin-top: 8px;
}

.primary-btn {
    padding: 14px 30px;
    border-radius: 999px;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    color: white;
    text-decoration: none;
    font-weight: 700;
    border: none;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}

.primary-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 45px rgba(42, 126, 255, 0.25);
}

.secondary-btn.alt {
    padding: 14px 30px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #ffffff;
    text-decoration: none;
    font-weight: 600;
    transition: transform 0.25s ease, background 0.25s ease, border-color 0.25s ease;
}

.secondary-btn.alt:hover {
    transform: translateY(-2px);
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.3);
}


/* PRODUCTS */
.products {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    padding: 20px;
}

.card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    border-radius: 15px;
    padding: 10px;
    text-align: center;
    transition: 0.3s;
    border: 1px solid rgba(255,255,255,0.2);
}


.card img {
    width: 100%;
    height: 160px;        /* fixed height */
    object-fit: cover;    /* 🔥 keeps image clean without distortion */
    border-radius: 10px;
}

.card:hover {
    transform: scale(1.08);
}

/* Floating Menu Icon */
.floating-menu {
    position: fixed;
    top: 15px;
    left: 15px;
    font-size: 28px;
    cursor: pointer;
    z-index: 2000;
    background: rgba(0,0,0,0.5);
    padding: 8px 12px;
    border-radius: 8px;
}
.header {
    width: 100%;
    text-align: center;   /* THIS centers horizontally */
    padding: 20px 0;
    font-size: 28px;
    font-weight: bold;
}

.floating-menu:hover {
    background: #ff7eb3;
}

/* Fullscreen background */
.fullscreen {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.9);
    justify-content: center;
    align-items: center;
    z-index: 2000;
}

/* Image inside fullscreen */
.fullscreen img {
    max-width: 90%;
    max-height: 90%;
    border-radius: 10px;
}

/* Close button */
.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 25px;
    cursor: pointer;
}

.bg-blur {
    position: fixed;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(255,126,179,0.5), transparent);
    top: -100px;
    left: -100px;
    filter: blur(120px);
    z-index: -1;
}

/* Background overlay */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(5px);

    justify-content: center;
    align-items: center;
}

/* Center box */
.modal-content {
    background: rgba(0, 0, 0, 0.8);
    width: 75%;
    max-width: 600px;
    max-height: 600px;
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    position: relative;
    border: 2px solid rgba(255, 126, 179,0.5);
}

/* Image inside modal */
.modal-content img {
    width: 100%;
    max-height: 450px;
    object-fit: contain;
    border-radius: 10px;
    background: rgba(255,255,255,0.05);
}

/* Close button */
.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 25px;
    cursor: pointer;
}

/* Profile section */
.profile {
    text-align: center;
    margin-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    padding-bottom: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
}

/* Circular image container */
.profile-img-container {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff7eb3, #ff758c);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    box-shadow: 0 10px 30px rgba(255, 126, 179, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
    border: 4px solid white;
    
}

/* Circular image */
.profile-img {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid rgba(255, 255, 255, 0.9);
    box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
}

/* Email text */
.profile-email {
    color: white;
    font-size: 14px;
    margin-top: 8px;
    word-break: break-all;
}

.profile-panel {
    display: none;
    width: 100%;
    margin-top: 12px;
}

.profile-panel.active {
    display: block;
}

.profile-form {
    width: 100%;
    display: grid;
    gap: 10px;
    margin-top: 14px;
}

.profile-label {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
    letter-spacing: 0.02em;
    text-align: center;
}

.profile-form input[type="file"] {
    width: 100%;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.08);
    color: white;
}

.profile-form input[type="file"]::-webkit-file-upload-button {
    cursor: pointer;
    color: #fff;
    background: #4ac3ff;
    border: none;
    border-radius: 12px;
    padding: 8px 12px;
}

.upload-btn {
    width: 100%;
    padding: 12px 0;
    border: none;
    border-radius: 999px;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    color: white;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.upload-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 30px rgba(42, 126, 255, 0.2);
}

.profile-message {
    font-size: 13px;
    margin-top: 10px;
    line-height: 1.3;
    text-align: center;
}

.profile-message.success {
    color: #8be7a8;
}

.profile-message.error {
    color: #ff9aa8;
}

@keyframes gradientBG {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* CHATBOT STYLES */
.chatbot-container {
    display: none;
    position: fixed;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    width: 600px;
    max-height: 400px;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    border-radius: 15px;
    padding: 20px;
    z-index: 1000;
    border: 1px solid rgba(255, 255, 255, 0.2);
    overflow-y: auto;
}

#recommendationsArea {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 12px;
}

.recommendation-item {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border-radius: 10px;
    padding: 12px;
    text-align: center;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 126, 179, 0.5);
}

.recommendation-item:hover {
    background: rgba(255, 126, 179, 0.3);
    transform: scale(1.05);
    box-shadow: 0 0 15px rgba(255, 126, 179, 0.6);
}

.recommendation-emoji {
    font-size: 32px;
    margin-bottom: 8px;
}

.recommendation-name {
    font-size: 12px;
    font-weight: 600;
}

</style>

<style>
/* ========== RESPONSIVE DESIGN ========== */

/* Tablet & small laptop */
@media screen and (max-width: 900px) {
    .hero-card {
        grid-template-columns: 1fr;
        padding: 30px 24px;
        gap: 24px;
    }

    .hero-copy h1 {
        font-size: 34px;
    }

    .hero-copy p {
        font-size: 15px;
    }

    .hero-right-card {
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
    }

    .hero-actions {
        justify-content: center;
    }

    .content-section {
        padding: 0 16px 30px;
    }

    .title {
        font-size: 26px;
        letter-spacing: 1px;
    }
}

/* Mobile */
@media screen and (max-width: 640px) {
    .top-bar {
        height: 70px;
        display: flex;
        align-items: center;
        padding: 0 12px;
    }

    .menu {
        position: relative;
        left: 0;
        top: auto;
        transform: none;
        width: 42px;
        height: 42px;
        border-radius: 10px;
        flex-shrink: 0;
    }

    .menu::before {
        top: 10px;
        box-shadow: 0 8px 0 white;
    }

    .menu::after {
        bottom: 10px;
    }

    .menu::before,
    .menu::after {
        width: 22px;
        height: 2.5px;
    }

    .menu:hover {
        transform: scale(1.05);
    }

    .menu:active {
        transform: scale(0.95);
    }

    .title {
        position: relative;
        left: auto;
        top: auto;
        transform: none;
        font-size: 16px;
        letter-spacing: 0.5px;
        flex: 1;
        text-align: center;
        padding: 0 8px;
        line-height: 1.3;
    }

    .sidebar {
        width: calc(100vw - 60px);
        max-width: 300px;
        padding: 16px;
    }

    .profile-img-container {
        width: 90px;
        height: 90px;
    }

    .profile-img {
        width: 80px;
        height: 80px;
    }

    .content-section {
        margin-top: 20px;
        padding: 0 12px 30px;
    }

    .hero-card {
        grid-template-columns: 1fr;
        padding: 24px 18px;
        border-radius: 22px;
        gap: 20px;
    }

    .hero-copy h1 {
        font-size: 26px;
        letter-spacing: -0.02em;
    }

    .hero-copy p {
        font-size: 14px;
        line-height: 1.7;
    }

    .hero-badges {
        gap: 8px;
        margin-top: 16px;
    }

    .hero-badges span {
        padding: 8px 14px;
        font-size: 12px;
    }

    .hero-right-card {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .hero-stat {
        padding: 14px 12px;
        border-radius: 16px;
    }

    .stat-value {
        font-size: 22px;
        margin-bottom: 4px;
    }

    .stat-label {
        font-size: 11px;
    }

    .hero-actions {
        justify-content: center;
        gap: 10px;
    }

    .primary-btn,
    .secondary-btn.alt {
        padding: 12px 22px;
        font-size: 14px;
    }

    .search-box {
        margin-top: 40px;
        flex-direction: column;
        gap: 10px;
    }

    .search-box input {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
    }

    .search-box button {
        width: 100%;
    }

    .search-container {
        margin-top: 40px;
        flex-direction: column;
    }

    .camera-container {
        margin-top: 40px;
    }

    .modal-content {
        width: 92%;
        max-width: none;
        padding: 14px;
        border-radius: 12px;
    }

    .modal-content img {
        max-height: 350px;
    }

    .products {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 12px;
    }

    .card img {
        height: 120px;
    }

    .floating-menu {
        top: 10px;
        left: 10px;
        font-size: 22px;
        padding: 6px 10px;
    }

    .chatbot-container {
        width: calc(100% - 32px);
        left: 16px;
        transform: none;
        bottom: 70px;
        max-height: 350px;
    }
}

/* Small mobile */
@media screen and (max-width: 400px) {
    .title {
        font-size: 13px;
    }

    .hero-copy h1 {
        font-size: 22px;
    }

    .hero-card {
        padding: 20px 14px;
    }

    .hero-right-card {
        grid-template-columns: 1fr 1fr 1fr;
        gap: 8px;
    }

    .stat-value {
        font-size: 18px;
    }

    .stat-label {
        font-size: 10px;
    }

    .hero-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .primary-btn,
    .secondary-btn.alt {
        text-align: center;
        padding: 12px 16px;
        font-size: 13px;
    }

    .sidebar {
        width: calc(100vw - 40px);
        padding: 14px;
    }

    .profile-img-container {
        width: 76px;
        height: 76px;
    }

    .profile-img {
        width: 68px;
        height: 68px;
    }

    .products {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 8px;
    }

    .card {
        padding: 8px;
        border-radius: 12px;
    }

    .card img {
        height: 100px;
    }
}
</style>
</head>

<body>

<div class="bg-blur"></div>

<div class="top-bar">
    <div id="menuBtn" class="menu" onclick="openSidebar()"></div>
    <div class="title">WELCOME TO OUTFIT RECOMMENDER 👗</div>
</div>

<!-- FLOATING MENU -->


<!-- SIDEBAR -->
<div id="sidebar" class="sidebar">
    <h2><br><br>☰ Menu</h2>
    <!-- PROFILE -->
    <div class="profile">
        <div class="profile-img-container">
            <img src="<?php echo htmlspecialchars($profileImg); ?>" class="profile-img" alt="Profile Picture">
        </div>
        <p class="profile-email">
            <?php echo htmlspecialchars($displayName); ?>
        </p>
    </div>

    <!-- MAIN FEATURES -->
    <div class="menu-section-label">Features</div>

    <div class="menu-item active-item">
        <a href="home.php">🏠 Home</a>
    </div>


    <div class="menu-divider"></div>
    <div class="menu-section-label">My Account</div>

    <div class="menu-item" onclick="togglePanel('profilePanel')">📷 Update Profile Image</div>
    <div id="profilePanel" class="expandable-panel<?php echo ($successMessage || $errorMessage) ? ' active' : ''; ?>" <?php echo ($successMessage || $errorMessage) ? 'style="display:block"' : ''; ?>>
        <?php if ($successMessage): ?>
            <p class="profile-message success"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <p class="profile-message error"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="profile-form">
            <label class="profile-label" for="profile_pic">Select profile image</label>
            <input type="file" name="profile_pic" id="profile_pic" accept="image/*">
            <button type="submit" name="update_profile" class="upload-btn">Upload Profile Photo</button>
        </form>
    </div>

    <div class="menu-item" onclick="togglePanel('historyPanel')">🕘 Search History</div>
    <div id="historyPanel" class="expandable-panel">
        <?php
        if(count($_SESSION['history']) > 0) {
            foreach($_SESSION['history'] as $item) {
                echo "<div class='history-item'>🔍 $item</div>";
            }
        } else {
            echo "<div class='history-item' style='color:rgba(255,255,255,0.45);'>No searches yet</div>";
        }
        ?>
    </div>

    <div class="menu-item" onclick="togglePanel('savedPanel'); loadSavedOutfits();">💾 Saved Outfits <span id="savedCountBadge" class="saved-count-badge" style="display:none;"></span></div>
    <div id="savedPanel" class="expandable-panel">
        <div id="savedOutfitsContainer">
            <div class="saved-empty">✨ Your saved outfits will appear here.<br>Use the AI Recommender to discover looks!</div>
        </div>
    </div>

    <div class="menu-divider"></div>
    <div class="menu-section-label">Discover</div>

    <div class="menu-item" onclick="togglePanel('trendPanel')">🔥 Trending Now</div>
    <div id="trendPanel" class="expandable-panel">
        <div class="trend-item"><span class="trend-badge hot">Hot</span> Oversized blazers with wide-leg pants</div>
        <div class="trend-item"><span class="trend-badge new">New</span> Pastel linen co-ord sets</div>
        <div class="trend-item"><span class="trend-badge rising">Rising</span> Minimalist earth-tone layering</div>
        <div class="trend-item"><span class="trend-badge hot">Hot</span> Y2K streetwear revival</div>
        <div class="trend-item"><span class="trend-badge new">New</span> Quiet luxury basics</div>
    </div>

    <div class="menu-item" onclick="togglePanel('tipsPanel')">💡 Style Tips</div>
    <div id="tipsPanel" class="expandable-panel">
        <div class="panel-tip">👔 <strong>Rule of Three:</strong> Limit your outfit to three main colors for a balanced, cohesive look.</div>
        <div class="panel-tip">👟 <strong>Shoe Game:</strong> Shoes can make or break an outfit. Match formality — sneakers for casual, loafers for smart-casual.</div>
        <div class="panel-tip">🧥 <strong>Layering:</strong> Add dimension with light layers — a jacket or cardigan elevates any basic outfit.</div>
        <div class="panel-tip">🎨 <strong>Contrast:</strong> Pair dark bottoms with lighter tops (or vice versa) for a visually appealing silhouette.</div>
    </div>

    <div class="menu-divider"></div>
    <div class="menu-section-label">Account</div>

    <div class="menu-item logout-item">
        <a href="logout.php" style="color: #ff7b7b;">🚪 Logout</a>
    </div>
</div>

<!-- OVERLAY -->
<div id="overlay" class="overlay" onclick="closeSidebar()"></div>

<div class="content-section">
    <div class="hero-card">
        <div class="hero-copy">
            <span class="small-label">AI Outfit Recommender</span>
            <h1>Style made effortless with smart outfit choices.</h1>
            <p>Our AI recommends the best outfits for your image, age and occasion. Whether it’s a wedding, party, workday or weekend hangout—discover looks that fit your mood and moment.</p>
            <div class="hero-badges">
                <span>Smart picks</span>
                <span>Fast suggestions</span>
                <span>Style confidence</span>
            </div>
        </div>
        <div class="hero-right-card">
            <div class="hero-stat">
                <div class="stat-value">25+</div>
                <div class="stat-label">Outfit ideas</div>
            </div>
            <div class="hero-stat">
                <div class="stat-value">Various</div>
                <div class="stat-label">Occasion types</div>
            </div>
            <div class="hero-stat">
                <div class="stat-value">24/7</div>
                <div class="stat-label">Style support</div>
            </div>
            <div class="hero-actions">
                <a href="recommend.php" class="primary-btn">Try AI Recommender</a>
                <a href="logout.php" class="secondary-btn alt">Logout</a>
            </div>
        </div>
    </div>
</div>

<script>
function openSidebar() {
    document.getElementById("sidebar").classList.add("active");
    document.getElementById("overlay").classList.add("active");
    document.getElementById("menuBtn").style.display = "none";
}

function closeSidebar() {
    document.getElementById("sidebar").classList.remove("active");
    document.getElementById("overlay").classList.remove("active");
    document.getElementById("menuBtn").style.display = "block";
}

function togglePanel(panelId) {
    const allPanels = document.querySelectorAll('.expandable-panel');
    const target = document.getElementById(panelId);
    const isOpen = target.style.display === 'block';

    // Close all panels
    allPanels.forEach(p => p.style.display = 'none');

    // Toggle the clicked one
    if (!isOpen) {
        target.style.display = 'block';
    }
}

let savedOutfitsLoaded = false;

function loadSavedOutfits() {
    const container = document.getElementById('savedOutfitsContainer');

    fetch('save_outfit.php?action=list')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.outfits || data.outfits.length === 0) {
                container.innerHTML = '<div class="saved-empty">✨ Your saved outfits will appear here.<br>Use the AI Recommender to discover looks!</div>';
                updateSavedBadge(0);
                return;
            }

            updateSavedBadge(data.outfits.length);
            let html = '';
            data.outfits.forEach(function(outfit) {
                const platformClass = (outfit.platform || '').toLowerCase();
                const platformLabel = outfit.platform ? outfit.platform.charAt(0).toUpperCase() + outfit.platform.slice(1) : '';
                const link = outfit.product_link || '#';
                const fabricText = outfit.fabric_details ? outfit.fabric_details.substring(0, 60) : '';

                html += '<div class="saved-outfit-card" data-name="' + escapeAttr(outfit.product_name) + '">';
                html += '<button class="saved-remove-btn" onclick="removeSavedOutfit(this, \'' + escapeAttr(outfit.product_name).replace(/'/g, "\\'") + '\')" title="Remove">✕</button>';
                html += '<div class="saved-outfit-name">' + escapeHtml(outfit.product_name) + '</div>';
                html += '<div class="saved-outfit-meta">';
                if (platformLabel) {
                    html += '<span class="saved-platform-badge ' + platformClass + '">' + platformLabel + '</span>';
                }
                html += '</div>';
                if (fabricText) {
                    html += '<div class="saved-outfit-fabric">' + escapeHtml(fabricText) + '</div>';
                }
                html += '<div class="saved-outfit-actions">';
                if (link !== '#') {
                    html += '<a href="' + escapeAttr(link) + '" target="_blank" class="saved-view-btn">View on ' + platformLabel + '</a>';
                }
                html += '</div>';
                html += '</div>';
            });
            container.innerHTML = html;
            savedOutfitsLoaded = true;
        })
        .catch(function(err) {
            console.error('Failed to load saved outfits:', err);
        });
}

function removeSavedOutfit(btn, productName) {
    const card = btn.closest('.saved-outfit-card');
    card.style.opacity = '0.4';
    card.style.pointerEvents = 'none';

    const formData = new FormData();
    formData.append('action', 'unsave');
    formData.append('product_name', productName);

    fetch('save_outfit.php', { method: 'POST', body: formData })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            card.style.transition = 'all 0.3s ease';
            card.style.maxHeight = card.scrollHeight + 'px';
            requestAnimationFrame(function() {
                card.style.maxHeight = '0';
                card.style.opacity = '0';
                card.style.marginBottom = '0';
                card.style.padding = '0 12px';
                card.style.borderColor = 'transparent';
            });
            setTimeout(function() {
                card.remove();
                const container = document.getElementById('savedOutfitsContainer');
                const remaining = container.querySelectorAll('.saved-outfit-card');
                if (remaining.length === 0) {
                    container.innerHTML = '<div class="saved-empty">✨ Your saved outfits will appear here.<br>Use the AI Recommender to discover looks!</div>';
                }
                updateSavedBadge(remaining.length);
            }, 350);
        })
        .catch(function(err) {
            card.style.opacity = '1';
            card.style.pointerEvents = 'auto';
            console.error('Remove error:', err);
        });
}

function updateSavedBadge(count) {
    const badge = document.getElementById('savedCountBadge');
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

function escapeAttr(text) {
    return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Load saved count on page load
fetch('save_outfit.php?action=list')
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.outfits && data.outfits.length > 0) {
            updateSavedBadge(data.outfits.length);
        }
    })
    .catch(function() {});
</script>

</body>
</html>