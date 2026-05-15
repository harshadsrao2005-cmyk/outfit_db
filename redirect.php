<?php
/**
 * redirect.php
 * 
 * Takes a platform name and product query, finds the first matching product's
 * direct URL, and redirects the user there (single product page, not search).
 * 
 * Strategy per platform:
 *   Amazon   – Scrape Amazon.in search (works with cURL)
 *   Flipkart – Use Google "site:flipkart.com" search (Flipkart blocks cURL with 403)
 *   Meesho   – Use Google "site:meesho.com" search (Meesho is a JS SPA, no static links)
 * 
 * Falls back to the platform search URL if everything fails.
 */

$platform = isset($_GET['platform']) ? strtolower(trim($_GET['platform'])) : '';
$query    = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($platform) || empty($query)) {
    die('Missing parameters: platform and q are required.');
}

$encodedQuery = urlencode($query);

// Build the search/fallback URL for each platform
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
        die('Unknown platform: ' . htmlspecialchars($platform));
}

/**
 * Fetch HTML from a URL using cURL with a realistic browser User-Agent.
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
 * Extract the first Amazon product URL from Amazon search HTML.
 */
function extractAmazonProductUrl($html) {
    if (preg_match_all('/href="(\/[^"]*\/dp\/[A-Z0-9]{10}[^"]*)"/', $html, $matches)) {
        foreach ($matches[1] as $match) {
            if (strpos($match, 'slredirect') !== false) continue;
            $url = html_entity_decode($match);
            if (preg_match('/(\/[^?]*\/dp\/[A-Z0-9]{10})/', $url, $clean)) {
                return "https://www.amazon.in" . $clean[1];
            }
            return "https://www.amazon.in" . $url;
        }
    }
    return null;
}

/**
 * Use Google Search to find the first product URL on a given platform.
 * Searches for: site:platform.com "product name"
 * Then extracts the first organic result URL pointing to that platform.
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

    // Google wraps result URLs in /url?q=ACTUAL_URL&... format
    // Extract URLs that point to the target platform
    $pattern = '/\/url\?q=(https?:\/\/(?:www\.)?' . preg_quote($site, '/') . '\/[^&"]+)/i';
    if (preg_match_all($pattern, $html, $matches)) {
        foreach ($matches[1] as $url) {
            $url = urldecode(html_entity_decode($url));
            // For Flipkart: look for /p/ in the URL (product page)
            if ($platform === 'flipkart' && strpos($url, '/p/') !== false) {
                return strtok($url, '&'); // Remove trailing Google tracking
            }
            // For Meesho: look for /p/ in the URL (product page)
            if ($platform === 'meesho' && strpos($url, '/p/') !== false) {
                return strtok($url, '&');
            }
        }
        // If no /p/ URL found, return the first result anyway (could be a category page)
        $firstUrl = urldecode(html_entity_decode($matches[1][0]));
        return strtok($firstUrl, '&');
    }

    // Alternative: Google sometimes uses data-href or direct href patterns
    $directPattern = '/href="(https?:\/\/(?:www\.)?' . preg_quote($site, '/') . '\/[^"]+)"/i';
    if (preg_match($directPattern, $html, $match)) {
        return html_entity_decode($match[1]);
    }

    return null;
}

// --- Main Logic ---

ob_start();

$productUrl = null;

switch ($platform) {
    case 'amazon':
        // Amazon works fine with direct scraping
        $html = fetchPage($searchUrl);
        if ($html) {
            $productUrl = extractAmazonProductUrl($html);
        }
        break;

    case 'flipkart':
    case 'meesho':
        // These platforms block cURL or use JS rendering
        // Use Google to find the exact product page
        $productUrl = findProductViaGoogle($query, $platform);

        // If Google fails, try direct scraping as a last resort
        if (!$productUrl) {
            $html = fetchPage($searchUrl);
            if ($html) {
                if ($platform === 'flipkart') {
                    if (preg_match('/href="(\/[^"]*\/p\/itm[^"]*)"/', $html, $m)) {
                        $productUrl = "https://www.flipkart.com" . html_entity_decode($m[1]);
                    }
                } elseif ($platform === 'meesho') {
                    if (preg_match('/href="(\/[^"]*\/p\/[a-z0-9]{4,})"/', $html, $m)) {
                        $productUrl = "https://www.meesho.com" . html_entity_decode($m[1]);
                    }
                }
            }
        }
        break;
}

if ($productUrl) {
    ob_end_clean();
    header("Location: $productUrl");
    exit();
}

// Fallback: redirect to platform search page
ob_end_clean();
header("Location: $searchUrl");
exit();

