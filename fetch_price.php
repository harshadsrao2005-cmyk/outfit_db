<?php
/**
 * fetch_price.php
 * 
 * Takes a platform and product query, finds the first matching product,
 * and returns the REAL price + direct product URL.
 * Called via AJAX from the recommendation cards.
 * 
 * Strategy:
 *   Amazon   – Scrape Amazon.in search directly (works with cURL)
 *   Flipkart – Google "site:flipkart.com" to find product URL, then scrape product page
 *   Meesho   – Google "site:meesho.com" to find product URL, then scrape product page
 */

header('Content-Type: application/json');

$platform = isset($_GET['platform']) ? strtolower(trim($_GET['platform'])) : '';
$query    = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($platform) || empty($query)) {
    echo json_encode(['error' => 'Missing parameters']);
    exit();
}

$encodedQuery = urlencode($query);

// Build the search URL (used as fallback)
switch ($platform) {
    case 'amazon':
        $searchUrl = "https://www.amazon.in/s?k=$encodedQuery";
        break;
    case 'flipkart':
        $searchUrl = "https://www.flipkart.com/search?q=$encodedQuery";
        break;
    case 'meesho':
        $searchUrl = "https://www.meesho.com/search?q=$encodedQuery";
        break;
    default:
        echo json_encode(['error' => 'Unknown platform']);
        exit();
}

/**
 * Fetch HTML from URL with realistic browser headers.
 */
function fetchPage($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-IN,en;q=0.9,hi;q=0.8',
            'Cache-Control: no-cache',
        ],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 400 && $html) {
        return $html;
    }
    return false;
}

/**
 * Use Google Search to find the first product URL on Flipkart/Meesho.
 */
function findProductViaGoogle($query, $platform) {
    $siteMap = [
        'flipkart' => 'flipkart.com',
        'meesho'   => 'meesho.com',
    ];

    if (!isset($siteMap[$platform])) return null;

    $site = $siteMap[$platform];
    $googleQuery = urlencode("site:$site $query");
    $googleUrl = "https://www.google.com/search?q=$googleQuery&num=3&hl=en";

    $ch = curl_init($googleUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-IN,en;q=0.9',
        ],
    ]);

    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) return null;

    $pattern = '/\/url\?q=(https?:\/\/(?:www\.)?' . preg_quote($site, '/') . '\/[^&"]+)/i';
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[1] as $url) {
            $url = urldecode(html_entity_decode($url));
            if (strpos($url, '/p/') !== false) {
                return strtok($url, '&');
            }
        }
        return strtok(urldecode(html_entity_decode($matches[1][0])), '&');
    }

    $directPattern = '/href="(https?:\/\/(?:www\.)?' . preg_quote($site, '/') . '\/[^"]+)"/i';
    if (preg_match($directPattern, $html, $match)) {
        return html_entity_decode($match[1]);
    }

    return null;
}

/**
 * Extract price from a product page HTML (generic ₹ extraction).
 */
function extractPrice($html) {
    // Amazon specific: class="a-price-whole"
    if (preg_match('/class="a-price-whole"[^>]*>([0-9,]+)/', $html, $m)) {
        return str_replace(',', '', $m[1]);
    }
    // Flipkart specific: class containing "selling-price" or ₹ in price containers
    if (preg_match('/class="[^"]*selling[^"]*"[^>]*>₹\s*([0-9,]+)/', $html, $m)) {
        return str_replace(',', '', $m[1]);
    }
    // Generic: first ₹ amount on the page (works for most Indian e-commerce)
    if (preg_match('/₹\s*([0-9,]+)/', $html, $m)) {
        return str_replace(',', '', $m[1]);
    }
    return null;
}

/**
 * Extract Amazon product from search results.
 */
function extractAmazonProduct($html) {
    $result = ['price' => null, 'url' => null];

    if (preg_match_all('/href="(\/[^"]*\/dp\/[A-Z0-9]{10}[^"]*)"/', $html, $urlMatches)) {
        foreach ($urlMatches[1] as $match) {
            if (strpos($match, 'slredirect') !== false) continue;
            $url = html_entity_decode($match);
            if (preg_match('/(\/[^?]*\/dp\/[A-Z0-9]{10})/', $url, $clean)) {
                $result['url'] = "https://www.amazon.in" . $clean[1];
            } else {
                $result['url'] = "https://www.amazon.in" . $url;
            }
            break;
        }
    }

    if (preg_match('/class="a-price-whole"[^>]*>([0-9,]+)/', $html, $priceMatch)) {
        $result['price'] = str_replace(',', '', $priceMatch[1]);
    } elseif (preg_match('/₹\s*([0-9,]+)/', $html, $priceMatch)) {
        $result['price'] = str_replace(',', '', $priceMatch[1]);
    }

    return $result;
}

// --- Main Logic ---

$result = ['price' => null, 'url' => null];

switch ($platform) {
    case 'amazon':
        $html = fetchPage($searchUrl);
        if ($html) {
            $result = extractAmazonProduct($html);
        }
        break;

    case 'flipkart':
    case 'meesho':
        // Use Google to find the exact product page URL
        $productUrl = findProductViaGoogle($query, $platform);
        if ($productUrl) {
            $result['url'] = $productUrl;
            // Try to fetch the product page for the price
            $productHtml = fetchPage($productUrl);
            if ($productHtml) {
                $result['price'] = extractPrice($productHtml);
            }
        }
        // Fallback: try direct search scraping
        if (!$result['url']) {
            $html = fetchPage($searchUrl);
            if ($html) {
                if (preg_match('/₹\s*([0-9,]+)/', $html, $priceMatch)) {
                    $result['price'] = str_replace(',', '', $priceMatch[1]);
                }
            }
        }
        break;
}

if ($result['price'] || $result['url']) {
    echo json_encode([
        'success' => true,
        'price'   => $result['price'],
        'url'     => $result['url'],
    ]);
    exit();
}

// Fallback
echo json_encode([
    'success' => false,
    'price'   => null,
    'url'     => null,
]);

