<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$conn = new mysqli("localhost", "root", "", "outfit_db");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

// Auto-create saved_outfits table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS saved_outfits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_email VARCHAR(255) NOT NULL,
    product_name VARCHAR(500) NOT NULL,
    fabric_details VARCHAR(500),
    platform VARCHAR(50),
    styling_tip TEXT,
    product_link VARCHAR(1000),
    saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_save (user_email, product_name)
)");

$userEmail = $_SESSION['user'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// SAVE an outfit
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = trim($_POST['product_name'] ?? '');
    $fabricDetails = trim($_POST['fabric_details'] ?? '');
    $platform = trim($_POST['platform'] ?? '');
    $stylingTip = trim($_POST['styling_tip'] ?? '');
    $productLink = trim($_POST['product_link'] ?? '');

    if ($productName === '') {
        echo json_encode(['error' => 'Product name is required']);
        exit();
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO saved_outfits (user_email, product_name, fabric_details, platform, styling_tip, product_link) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssssss', $userEmail, $productName, $fabricDetails, $platform, $stylingTip, $productLink);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'saved']);
    } else {
        echo json_encode(['status' => 'already_saved']);
    }
    $stmt->close();
    exit();
}

// UNSAVE an outfit
if ($action === 'unsave' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productName = trim($_POST['product_name'] ?? '');

    $stmt = $conn->prepare("DELETE FROM saved_outfits WHERE user_email = ? AND product_name = ?");
    $stmt->bind_param('ss', $userEmail, $productName);
    $stmt->execute();

    echo json_encode(['status' => 'removed']);
    $stmt->close();
    exit();
}

// GET all saved outfits for current user
if ($action === 'list') {
    $stmt = $conn->prepare("SELECT product_name, fabric_details, platform, styling_tip, product_link, saved_at FROM saved_outfits WHERE user_email = ? ORDER BY saved_at DESC");
    $stmt->bind_param('s', $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    $outfits = [];
    while ($row = $result->fetch_assoc()) {
        $outfits[] = $row;
    }

    echo json_encode(['outfits' => $outfits]);
    $stmt->close();
    exit();
}

// CHECK if a specific outfit is saved
if ($action === 'check' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $productNames = json_decode($_POST['product_names'] ?? '[]', true);

    if (!is_array($productNames) || empty($productNames)) {
        echo json_encode(['saved' => []]);
        exit();
    }

    $placeholders = implode(',', array_fill(0, count($productNames), '?'));
    $types = 's' . str_repeat('s', count($productNames));
    $params = array_merge([$userEmail], $productNames);

    $stmt = $conn->prepare("SELECT product_name FROM saved_outfits WHERE user_email = ? AND product_name IN ($placeholders)");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $saved = [];
    while ($row = $result->fetch_assoc()) {
        $saved[] = $row['product_name'];
    }

    echo json_encode(['saved' => $saved]);
    $stmt->close();
    exit();
}

echo json_encode(['error' => 'Invalid action']);
$conn->close();
?>
