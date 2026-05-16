<?php
session_start();
set_time_limit(120);

if(!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// Configuration
$apiKeys = [
    'AIzaSyAd3cpXzMqaxrgKJ5d8rB_UHDJDDxM4oSQ',
    'AIzaSyAlBlB8Pc4Wp8OrwTv_51MwcjF8-vmsmFc',
    'AIzaSyA_6blHgjstu6dyY0w9l6NbtmgN9Wio6UE',
    'AIzaSyBUxIOe3PxyOgbLCx4bpARYAjgPR4mqEbU',
    'AIzaSyAxJM1amqbskUUoYJFdRdxuLYcJeTehAhk',
    'AIzaSyDHBPIy5DxHUeAT_s91l_9ytryyqN2gqqU'
];

// Gemini agents – ordered by priority; auto-fallback when limit is exceeded
$models = [
    'gemini-3.1-flash-lite',
    'gemini-3-flash',
    'gemini-2.5-flash',
    'gemini-1.5-flash',
];

/**
 * Call Gemini API with key rotation + model fallback
 */
function callGeminiAPI($payload) {
    global $apiKeys, $models;
    $lastError = 'Unknown error';

    foreach ($models as $model) {
        foreach ($apiKeys as $i => $key) {
            $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $lastError = curl_error($ch);
                curl_close($ch);
                continue;
            }
            curl_close($ch);

            if ($httpCode === 429 || $httpCode === 503) {
                $lastError = $model . ' rate-limited (HTTP ' . $httpCode . ')';
                continue;
            }

            $data = json_decode($response, true);

            if (isset($data['error'])) {
                $msg = strtolower($data['error']['message'] ?? '');
                $status = strtolower($data['error']['status'] ?? '');

                if (strpos($msg, 'quota') !== false || strpos($msg, 'resource_exhausted') !== false ||
                    strpos($msg, 'rate') !== false || strpos($msg, 'too many') !== false ||
                    $status === 'resource_exhausted') {
                    $lastError = $model . ': ' . ($data['error']['message'] ?? 'quota exceeded');
                    continue;
                }
                if ($httpCode === 404 || strpos($msg, 'not found') !== false) {
                    $lastError = $model . ' not available';
                    break;
                }
                return ['error' => $data['error']['message'] ?? 'Unknown error'];
            }

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return [
                    'success' => true,
                    'text' => $data['candidates'][0]['content']['parts'][0]['text'],
                    'model' => $model
                ];
            }

            $lastError = 'Empty response from ' . $model;
        }
    }

    // One retry after short wait
    sleep(3);
    foreach ($models as $model) {
        foreach ($apiKeys as $key) {
            $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            if (!curl_errno($ch)) {
                $data = json_decode($response, true);
                if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    curl_close($ch);
                    return ['success' => true, 'text' => $data['candidates'][0]['content']['parts'][0]['text'], 'model' => $model];
                }
            }
            curl_close($ch);
        }
    }

    return ['error' => 'All API keys exhausted. Please wait a minute and try again. (' . $lastError . ')'];
}

/**
 * Analyze image for age, gender, clothing, skin tone
 */
function analyzeImage($imageData) {
    $prompt = "You are an expert fashion and appearance analyst. Carefully analyze this photo of a person and provide:\n\n"
        . "1. age: Estimate their age as a single number (e.g. 25).\n\n"
        . "2. gender: Determine gender from physical appearance - male or female. Look at facial features (jawline, facial hair, brow ridge, cheekbones), body build (shoulder width, hip ratio), hairstyle, and visible clothing style. If facial hair, broad shoulders, or masculine jawline is visible, choose male. If softer facial features, wider hips, or feminine styling is visible, choose female. Only use other if truly ambiguous.\n\n"
        . "3. clothing: Describe what the person is currently wearing in detail - include garment types, colors, patterns, fit, and any accessories.\n\n"
        . "4. skin_tone: Identify their skin tone precisely using TWO parts:\n"
        . "   - A descriptive label: one of very fair, fair, light, light-medium, medium, olive, warm beige, tan, caramel, brown, dark brown, deep brown, ebony\n"
        . "   - The undertone: warm (golden/yellow/peachy), cool (pink/red/blue), or neutral\n"
        . "   Combine them like: medium with warm undertone or dark brown with neutral undertone\n\n"
        . "Return ONLY valid JSON with these exact keys: {\"age\": number, \"gender\": \"string\", \"clothing\": \"string\", \"skin_tone\": \"string\"}";

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => "image/jpeg",
                            "data" => $imageData
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json",
            "temperature" => 0.2
        ]
    ];

    $result = callGeminiAPI($payload);

    if (isset($result['error'])) {
        return $result;
    }

    $parsed = json_decode($result['text'], true);
    if ($parsed === null) {
        return ['error' => 'Invalid JSON from model: ' . $result['text']];
    }
    return $parsed;
}

/**
 * Get outfit recommendations
 */
function getOutfitAdvice($age, $gender, $detectedClothing, $skinTone, $occasion) {
    $genderLabel = ($gender === 'male') ? "men's" : (($gender === 'female') ? "women's" : "unisex");

    $prompt = "You are an expert Indian fashion stylist specializing in $genderLabel fashion on Amazon India, Flipkart, and Meesho.\n\n"
        . "Person Details:\n"
        . "- Age: $age years old\n"
        . "- Gender: $gender\n"
        . "- Skin tone: $skinTone\n"
        . "- Currently wearing: $detectedClothing\n"
        . "- Occasion: $occasion\n\n"
        . "Skin Tone Colour Guidance - Based on the skin tone $skinTone, choose outfit colours that genuinely complement it:\n"
        . "- Very fair / fair / light skin: Pastels (lavender, blush, baby blue), jewel tones (emerald, sapphire), avoid washing out with pure white or beige.\n"
        . "- Light-medium / medium skin: Most colours work - earth tones, olive green, coral, teal, burgundy, mustard.\n"
        . "- Olive / warm beige / tan skin: Warm colours (burnt orange, rust, gold, deep green, terracotta), avoid neon or ashy tones.\n"
        . "- Caramel / brown skin: Rich jewel tones (royal blue, magenta, deep purple), warm metallics, bright coral, avoid muddy browns.\n"
        . "- Dark brown / deep brown / ebony skin: Bold and bright colours (bright red, cobalt blue, fuchsia, emerald, white, gold), pastels also pop beautifully.\n\n"
        . "Task: Recommend exactly 30 $genderLabel outfits - 10 from each platform (Amazon, Flipkart, Meesho).\n"
        . "Each outfit must be appropriate for the gender $gender and the occasion $occasion.\n"
        . "Each recommendation must include colours that complement the skin tone $skinTone.\n\n"
        . "For each item provide:\n"
        . "1. product_name: A specific realistic product name with colour and brand (e.g. Allen Solly Mens Slim Fit Navy Blue Formal Shirt)\n"
        . "2. fabric_details: Fabric type and qualities (cotton, linen, stretchable, breathable etc.)\n"
        . "3. platform: Exactly one of Amazon, Flipkart, or Meesho\n"
        . "4. styling_tip: A practical styling tip mentioning why this colour/fabric works for their skin tone and occasion\n\n"
        . "Do NOT include price. Return ONLY a valid JSON array of 30 objects.";

    $payload = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "response_mime_type" => "application/json",
            "temperature" => 0.7
        ]
    ];

    $result = callGeminiAPI($payload);

    if (isset($result['error'])) {
        return json_encode($result);
    }

    $jsonText = $result['text'];
    json_decode($jsonText);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return json_encode(['error' => 'Invalid JSON from model: ' . $jsonText]);
    }
    return $jsonText;
}

$recommendations = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image'], $_POST['age'], $_POST['occasion'])) {
    $imageData = $_POST['image'];
    $userAge = intval($_POST['age']);
    $occasion = trim($_POST['occasion']);
    if ($userAge <= 0 || $occasion === '') {
        $error = 'Please enter a valid age and occasion.';
    } else {
        // Remove data:image/jpeg;base64, prefix
        $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
        $analysis = analyzeImage($imageData);
        if (!isset($analysis['error'])) {
            $age = $userAge;
            $gender = $analysis['gender'] ?? 'unspecified';
            $clothing = $analysis['clothing'] ?? 'a stylish outfit';
            $skinTone = $analysis['skin_tone'] ?? 'neutral';
            $recommendationsJson = getOutfitAdvice($age, $gender, $clothing, $skinTone, $occasion);
            $recData = json_decode($recommendationsJson, true);
            if (isset($recData['error'])) {
                $error = $recData['error'];
            } else {
                if (is_array($recData)) {
                    foreach ($recData as &$rec) {
                        $platform = strtolower($rec['platform']);
                        $productName = urlencode($rec['product_name']);
                        if ($platform == 'amazon' || $platform == 'flipkart' || $platform == 'meesho') {
                            $rec['product_link'] = "redirect.php?platform=$platform&q=$productName";
                        } else {
                            $rec['product_link'] = "#";
                        }
                        $rec['image_url'] = "https://via.placeholder.com/200x200?text=" . urlencode(substr($rec['product_name'], 0, 20));
                    }
                }
                $recommendations = $recData;
            }
        } else {
            $error = $analysis['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Outfit Recommendations</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
body {
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #090b12, #12182d, #1d2a48, #0b1324);
    background-size: 400% 400%;
    animation: gradientBG 14s ease infinite;
    color: white;
    text-align: center;
    padding: 24px 16px 40px;
}

@keyframes gradientBG {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.camera-container {
    margin: 18px auto 0;
    max-width: 640px;
    display: grid;
    gap: 16px;
    align-items: center;
    justify-items: center;
}

video, canvas {
    width: 100%;
    max-width: 440px;
    height: auto;
    border-radius: 20px;
    margin: 0;
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.25);
}

button {
    padding: 12px 24px;
    margin: 0;
    border: none;
    border-radius: 28px;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    color: white;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
}

button:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 35px rgba(42, 126, 255, 0.22);
    opacity: 0.98;
}

form {
    display: grid;
    gap: 20px;
    max-width: 520px;
    margin: 25px auto 0;
    padding: 28px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 28px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(18px);
    align-items: stretch;
    justify-items: center;
}

.form-group {
    width: 100%;
    margin-bottom: 16px;
    text-align: left;
}

label {
    display: block;
    margin-bottom: 10px;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.03em;
    color: rgba(255, 255, 255, 0.9);
}

input[type="number"],
input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    padding: 16px 18px;
    border-radius: 18px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.08);
    color: white;
    font-size: 16px;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
}

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

input[type="number"]:focus,
input[type="text"]:focus {
    border-color: rgba(74, 195, 255, 0.9);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 4px rgba(74, 195, 255, 0.12);
}

input::placeholder {
    color: rgba(255, 255, 255, 0.55);
}

#analyzeBtn {
    display: block;
    width: 100%;
    margin-top: 8px;
    padding: 16px 18px;
    border-radius: 24px;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 0.02em;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    border: none;
    color: white;
}

.recommendations {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    margin-top: 40px;
}

#recommendation-container {
    margin-top: 40px;
}

#nextBtn {
    padding: 12px 24px;
    margin: 20px 0;
    border: none;
    border-radius: 28px;
    background: linear-gradient(135deg, #4ac3ff, #2b7dff);
    color: white;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#nextBtn:hover {
    transform: translateY(-1px);
    box-shadow: 0 16px 35px rgba(42, 126, 255, 0.2);
}

.recommendation-card {
    position: relative;
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(14px);
    border-radius: 20px;
    padding: 22px;
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 18px 45px rgba(0,0,0,0.16);
}

.back-btn {
    position: absolute;
    top: 20px;
    left: 20px;
    padding: 10px 15px;
    background: rgba(0,0,0,0.5);
    color: white;
    text-decoration: none;
    border-radius: 10px;
}

.price-loading {
    color: rgba(255, 255, 255, 0.5);
    font-style: italic;
    animation: pricePulse 1.5s ease-in-out infinite;
}

@keyframes pricePulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

.price-loaded {
    color: #4ade80;
    font-weight: 600;
}

/* Save button */
.save-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(8px);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.save-btn:hover {
    background: rgba(255, 126, 179, 0.25);
    transform: scale(1.15);
    box-shadow: 0 6px 20px rgba(255, 126, 179, 0.3);
    border-color: rgba(255, 126, 179, 0.4);
}

.save-btn svg {
    width: 22px;
    height: 22px;
    transition: all 0.3s ease;
}

.save-btn .save-outline {
    stroke: rgba(255, 255, 255, 0.8);
    fill: none;
    stroke-width: 2;
    transition: all 0.3s ease;
}

.save-btn.saved .save-outline {
    fill: #ff7eb3;
    stroke: #ff7eb3;
}

.save-btn.saved {
    background: rgba(255, 126, 179, 0.2);
    border-color: rgba(255, 126, 179, 0.4);
    animation: savePopIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

@keyframes savePopIn {
    0% { transform: scale(1); }
    40% { transform: scale(1.3); }
    100% { transform: scale(1); }
}

.save-btn .save-tooltip {
    position: absolute;
    bottom: -32px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.85);
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
}

.save-btn:hover .save-tooltip {
    opacity: 1;
}

/* ========== RESPONSIVE DESIGN ========== */

/* Tablet */
@media screen and (max-width: 768px) {
    body {
        padding: 20px 12px 30px;
    }

    h1 {
        font-size: 22px;
        margin-top: 40px;
    }

    h2 {
        font-size: 20px;
    }

    .back-btn {
        top: 12px;
        left: 12px;
        padding: 8px 12px;
        font-size: 14px;
        border-radius: 8px;
    }

    video, canvas {
        max-width: 100%;
        border-radius: 16px;
    }

    form {
        max-width: 100%;
        padding: 22px 18px;
        border-radius: 22px;
        margin-top: 20px;
    }

    .recommendation-card {
        padding: 18px;
        border-radius: 16px;
    }

    .recommendation-card h3 {
        font-size: 16px;
        padding-right: 50px;
    }

    .recommendation-card p {
        font-size: 13px;
        line-height: 1.6;
    }

    .save-btn {
        width: 38px;
        height: 38px;
    }

    .save-btn svg {
        width: 20px;
        height: 20px;
    }
}

/* Mobile */
@media screen and (max-width: 480px) {
    body {
        padding: 14px 10px 24px;
    }

    h1 {
        font-size: 18px;
        margin-top: 48px;
        line-height: 1.3;
    }

    h2 {
        font-size: 18px;
    }

    .back-btn {
        top: 10px;
        left: 10px;
        padding: 7px 10px;
        font-size: 13px;
    }

    .camera-container {
        gap: 12px;
    }

    video, canvas {
        border-radius: 14px;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.2);
    }

    button {
        padding: 11px 20px;
        font-size: 14px;
        border-radius: 22px;
    }

    form {
        padding: 18px 14px;
        border-radius: 18px;
        gap: 14px;
    }

    .form-group {
        margin-bottom: 10px;
    }

    label {
        font-size: 13px;
        margin-bottom: 8px;
    }

    input[type="number"],
    input[type="text"] {
        padding: 14px 14px;
        font-size: 15px;
        border-radius: 14px;
    }

    #analyzeBtn {
        padding: 14px 16px;
        font-size: 15px;
        border-radius: 20px;
    }

    .recommendations {
        gap: 14px;
        margin-top: 24px;
    }

    .recommendation-card {
        padding: 16px 14px;
        border-radius: 14px;
    }

    .recommendation-card h3 {
        font-size: 15px;
        margin-top: 0;
        padding-right: 46px;
        line-height: 1.4;
    }

    .recommendation-card p {
        font-size: 12.5px;
        margin: 6px 0;
        word-break: break-word;
    }

    .recommendation-card a {
        font-size: 13px;
        padding: 7px 14px !important;
    }

    .save-btn {
        width: 36px;
        height: 36px;
        top: 12px;
        right: 12px;
    }

    .save-btn svg {
        width: 18px;
        height: 18px;
    }

    .save-btn .save-tooltip {
        display: none;
    }

    #nextBtn {
        padding: 11px 20px;
        font-size: 14px;
    }
}

/* Small mobile */
@media screen and (max-width: 360px) {
    h1 {
        font-size: 16px;
    }

    form {
        padding: 14px 12px;
    }

    .recommendation-card {
        padding: 14px 12px;
    }

    .recommendation-card h3 {
        font-size: 14px;
    }
}
</style>
</head>
<body>
<a href="home.php" class="back-btn">← Back</a>
<h1>📷 AI Outfit Recommendations</h1>

<?php if ($recommendations): ?>
    <h2>Your Recommendations</h2>
    <div class="recommendations">
        <?php foreach ($recommendations as $index => $rec): ?>
        <div class="recommendation-card" id="card-<?php echo $index; ?>" data-platform="<?php echo htmlspecialchars(strtolower($rec['platform'])); ?>" data-product="<?php echo htmlspecialchars($rec['product_name']); ?>" data-fabric="<?php echo htmlspecialchars($rec['fabric_details']); ?>" data-tip="<?php echo htmlspecialchars($rec['styling_tip']); ?>" data-link="<?php echo htmlspecialchars($rec['product_link']); ?>">
            <button class="save-btn" onclick="toggleSave(this, <?php echo $index; ?>)" title="Save outfit">
                <svg viewBox="0 0 24 24"><path class="save-outline" d="M5 3a2 2 0 0 0-2 2v16l9-4 9 4V5a2 2 0 0 0-2-2H5z"/></svg>
                <span class="save-tooltip">Save outfit</span>
            </button>
            <h3><?php echo htmlspecialchars($rec['product_name']); ?></h3>
            <p><strong>Fabric:</strong> <?php echo htmlspecialchars($rec['fabric_details']); ?></p>
            <p><strong>Platform:</strong> <?php echo htmlspecialchars($rec['platform']); ?></p>
            <p class="price-display" id="price-<?php echo $index; ?>"><strong>Price:</strong> <span class="price-loading">⏳ Fetching real price...</span></p>
            <p><strong>Tip:</strong> <?php echo htmlspecialchars($rec['styling_tip']); ?></p>
            <a href="<?php echo htmlspecialchars($rec['product_link']); ?>" target="_blank" id="link-<?php echo $index; ?>" style="display:inline-block; margin-top:10px; padding:8px 16px; background:#ff7eb3; color:white; text-decoration:none; border-radius:20px;">View on <?php echo htmlspecialchars($rec['platform']); ?></a>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    // Toggle save/unsave an outfit
    function toggleSave(btn, index) {
        const card = document.getElementById('card-' + index);
        const isSaved = btn.classList.contains('saved');
        const productName = card.getAttribute('data-product');
        const action = isSaved ? 'unsave' : 'save';

        const formData = new FormData();
        formData.append('action', action);
        formData.append('product_name', productName);

        if (!isSaved) {
            formData.append('fabric_details', card.getAttribute('data-fabric'));
            formData.append('platform', card.getAttribute('data-platform'));
            formData.append('styling_tip', card.getAttribute('data-tip'));
            formData.append('product_link', card.getAttribute('data-link'));
        }

        fetch('save_outfit.php', { method: 'POST', body: formData })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.status === 'saved' || data.status === 'already_saved') {
                    btn.classList.add('saved');
                    btn.querySelector('.save-tooltip').textContent = 'Saved!';
                } else if (data.status === 'removed') {
                    btn.classList.remove('saved');
                    btn.querySelector('.save-tooltip').textContent = 'Save outfit';
                }
            })
            .catch(function(err) {
                console.error('Save error:', err);
            });
    }

    // Fetch real prices and check saved status after page loads
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.recommendation-card[data-platform]');
        let delay = 0;

        // Collect all product names to check saved status
        const productNames = [];
        cards.forEach(function(card) {
            productNames.push(card.getAttribute('data-product'));
        });

        // Check which outfits are already saved
        if (productNames.length > 0) {
            const checkData = new FormData();
            checkData.append('action', 'check');
            checkData.append('product_names', JSON.stringify(productNames));

            fetch('save_outfit.php', { method: 'POST', body: checkData })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.saved && data.saved.length > 0) {
                        cards.forEach(function(card) {
                            const name = card.getAttribute('data-product');
                            if (data.saved.indexOf(name) !== -1) {
                                const btn = card.querySelector('.save-btn');
                                btn.classList.add('saved');
                                btn.querySelector('.save-tooltip').textContent = 'Saved!';
                            }
                        });
                    }
                })
                .catch(function(err) { console.error('Check saved error:', err); });
        }

        // Fetch prices
        cards.forEach(function(card, i) {
            const platform = card.getAttribute('data-platform');
            const product  = card.getAttribute('data-product');
            const priceEl  = document.getElementById('price-' + i);
            const linkEl   = document.getElementById('link-' + i);

            setTimeout(function() {
                fetch('fetch_price.php?platform=' + encodeURIComponent(platform) + '&q=' + encodeURIComponent(product))
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.success && data.price) {
                            priceEl.innerHTML = '<strong>Price:</strong> ₹' + Number(data.price).toLocaleString('en-IN');
                            priceEl.classList.add('price-loaded');
                        } else {
                            priceEl.innerHTML = '<strong>Price:</strong> <a href="' + linkEl.href + '" target="_blank" style="color:#4ac3ff;">Check price on ' + platform.charAt(0).toUpperCase() + platform.slice(1) + '</a>';
                        }
                        if (data.url) {
                            linkEl.href = data.url;
                        }
                    })
                    .catch(function() {
                        priceEl.innerHTML = '<strong>Price:</strong> <a href="' + linkEl.href + '" target="_blank" style="color:#4ac3ff;">Check price on ' + platform.charAt(0).toUpperCase() + platform.slice(1) + '</a>';
                    });
            }, delay);
            delay += 300;
        });
    });
    </script>
<?php elseif (isset($error)): ?>
    <p>Error: <?php echo htmlspecialchars($error); ?></p>
<?php else: ?>
    <div class="camera-container">
        <video id="video" autoplay></video>
        <canvas id="canvas" style="display:none;"></canvas>
        <button id="startBtn">Start Camera</button>
        <button id="captureBtn" style="display:none;">Capture Photo</button>
        <button id="retakeBtn" style="display:none;">Retake</button>
        <p id="click-msg" style="display:none; margin-top:10px;">After capture, fill your age and occasion, then press Recommend Outfit.</p>
    </div>
    <form id="imageForm" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="ageInput">Your Age</label>
            <input type="number" name="age" id="ageInput" placeholder="Enter your age" min="13" max="120" required>
        </div>
        <div class="form-group">
            <label for="occasionInput">Occasion</label>
            <input type="text" name="occasion" id="occasionInput" placeholder="e.g. office party, wedding, casual brunch" required>
        </div>
        <button id="analyzeBtn" type="button" style="display:none;">Recommend Outfit</button>
        <input type="hidden" name="image" id="imageInput">
    </form>
<?php endif; ?>

<script>
const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const startBtn = document.getElementById('startBtn');
const captureBtn = document.getElementById('captureBtn');
const retakeBtn = document.getElementById('retakeBtn');
const analyzeBtn = document.getElementById('analyzeBtn');
const ctx = canvas ? canvas.getContext('2d') : null;
const imageForm = document.getElementById('imageForm');

let stream;

if (startBtn) {
    startBtn.addEventListener('click', async () => {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            startBtn.style.display = 'none';
            captureBtn.style.display = 'inline';
            if (analyzeBtn) analyzeBtn.style.display = 'none';
        } catch (err) {
            alert('Camera access denied or not available');
        }
    });
}

if (captureBtn) {
    captureBtn.addEventListener('click', () => {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        stream.getTracks().forEach(track => track.stop());
        video.style.display = 'none';
        canvas.style.display = 'block';
        captureBtn.style.display = 'none';
        retakeBtn.style.display = 'inline';
        if (analyzeBtn) analyzeBtn.style.display = 'inline';
        document.getElementById('click-msg').style.display = 'block';
    });
}

if (analyzeBtn) {
    analyzeBtn.addEventListener('click', () => {
        const ageInput = document.getElementById('ageInput');
        const occasionInput = document.getElementById('occasionInput');
        if (!ageInput.value || !occasionInput.value.trim()) {
            alert('Please enter your age and occasion before analyzing.');
            return;
        }
        const imageData = canvas.toDataURL('image/jpeg', 0.8);
        document.getElementById('imageInput').value = imageData;
        imageForm.submit();
    });
}

if (retakeBtn) {
    retakeBtn.addEventListener('click', () => {
        canvas.style.display = 'none';
        video.style.display = 'block';
        retakeBtn.style.display = 'none';
        if (analyzeBtn) analyzeBtn.style.display = 'none';
        startBtn.style.display = 'inline';
        document.getElementById('click-msg').style.display = 'none';
    });
}
</script>
</body>
</html>