<?php
/**
 * Ad Serving API
 * Delivers advertisements to websites based on position and targeting criteria
 */

// Initialize the application
require_once __DIR__ . '/../../config/init.php';

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow cross-origin requests
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize database connection and logger
$db = new App\Utils\Database();
$logger = new App\Utils\Logger();

try {
    // Get request parameters
    $positionId = filter_input(INPUT_GET, 'position_id', FILTER_VALIDATE_INT);
    
    // Validate position ID
    if (!$positionId) {
        http_response_code(400);
        echo json_encode(['error' => 'Position ID is required']);
        exit;
    }
    
    // Get position details
    $positionModel = new App\Models\AdPosition();
    $position = $positionModel->find($positionId);
    
    if (!$position) {
        http_response_code(404);
        echo json_encode(['error' => 'Ad position not found']);
        exit;
    }
    
    // Verify position is active
    if ($position['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['error' => 'Ad position is not active']);
        exit;
    }
    
    // Get visitor information for targeting
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Get location data
    $locationData = getLocationFromIp($ipAddress);
    
    // Get device data
    $deviceData = getDeviceFromUserAgent($userAgent);
    
    // Query for eligible advertisements
    $query = "SELECT a.* FROM advertisements a
              WHERE a.position_id = :position_id
              AND a.status = 'active'
              AND a.start_date <= CURRENT_DATE
              AND (a.end_date IS NULL OR a.end_date >= CURRENT_DATE)
              AND a.budget > 0";
    
    // Add sorting by bid amount (highest bidder first)
    $query .= " ORDER BY a.bid_amount DESC";
    
    // Execute query
    $stmt = $db->prepare($query);
    $stmt->execute([
        'position_id' => $positionId
    ]);
    $allAds = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($allAds)) {
        // No eligible ads found
        http_response_code(404);
        echo json_encode(['error' => 'No eligible advertisements found']);
        exit;
    }
    
    // Initialize targeting criteria
    $targetingCriteria = [];
    
    // Add location criteria if available
    if (!empty($locationData['country'])) {
        $targetingCriteria['location'] = $locationData['country'];
    }
    
    // Add device criteria if available
    if (!empty($deviceData['device_type'])) {
        $targetingCriteria['device'] = $deviceData['device_type'];
    }
    
    // Add time-based criteria
    $now = new DateTime();
    $currentDay = $now->format('l'); // Returns day of week (Monday, Tuesday, etc.)
    $currentTime = $now->format('H:i'); // Returns time in 24-hour format (e.g., 14:30)
    
    $targetingCriteria['day'] = $currentDay;
    $targetingCriteria['time'] = $currentTime;
    
    // Add browser and OS criteria if available
    if (!empty($deviceData['browser'])) {
        $targetingCriteria['browser'] = $deviceData['browser'];
    }
    
    if (!empty($deviceData['os'])) {
        $targetingCriteria['os'] = $deviceData['os'];
    }
    
    // Filter ads by targeting criteria
    $adTargetingModel = new App\Models\AdTargeting();
    $eligibleAds = [];
    
    foreach ($allAds as $ad) {
        // Check if ad meets targeting criteria
        if ($adTargetingModel->matchesTargeting($ad['id'], $targetingCriteria)) {
            $eligibleAds[] = $ad;
        }
    }
    
    if (empty($eligibleAds)) {
        // No ads match the targeting criteria
        http_response_code(404);
        echo json_encode(['error' => 'No advertisements match your targeting criteria']);
        exit;
    }
    
    // Select the winning ad (currently just the highest bidder among eligible ads)
    $selectedAd = $eligibleAds[0];
    
    // Parse the ad content
    $content = json_decode($selectedAd['content'], true);
    
    // Build the ad HTML
    $adHtml = !empty($content['html']) ? $content['html'] : '';
    
    // Add tracking pixel for impression tracking
    $trackingUrl = getBaseUrl() . "/api/v1/track.php?ad_id={$selectedAd['id']}&position_id={$positionId}";
    $trackingPixel = "<img src=\"{$trackingUrl}\" style=\"position:absolute; visibility:hidden; width:1px; height:1px;\" alt=\"\" />";
    
    // Wrap links with click tracking
    $adHtml = wrapLinksWithTracking($adHtml, $selectedAd['id'], $positionId);
    
    // Prepare response data
    $response = [
        'success' => true,
        'ad' => [
            'id' => $selectedAd['id'],
            'width' => $position['width'],
            'height' => $position['height'],
            'html' => $adHtml . $trackingPixel
        ]
    ];
    
    // Log the ad serve event
    $logger->info("Ad served", [
        'ad_id' => $selectedAd['id'],
        'position_id' => $positionId,
        'ip' => $ipAddress,
        'referer' => $referer,
        'location' => $locationData['country'] ?? 'Unknown',
        'device' => $deviceData['device_type'] ?? 'Unknown'
    ]);
    
    // Return the ad data
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error
    $logger->error('Ad serving error: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode(['error' => 'An error occurred while processing the request']);
}

/**
 * Get location information from IP address
 * 
 * @param string $ip IP address
 * @return array Location data
 */
function getLocationFromIp($ip)
{
    // Use the PConline location API
    $url = "https://whois.pconline.com.cn/ipJson.jsp?ip={$ip}&json=true";
    
    try {
        $response = file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && empty($data['err'])) {
                return [
                    'country' => 'CN', // API returns only data for China, use country code
                    'region' => $data['pro'] ?? null,
                    'city' => $data['city'] ?? null
                ];
            }
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    // If IP geolocation fails, try to determine country from Accept-Language header
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if (!empty($acceptLanguage)) {
        $languages = explode(',', $acceptLanguage);
        foreach ($languages as $language) {
            if (preg_match('/^([a-z]{2})-([A-Z]{2})/', $language, $matches)) {
                return [
                    'country' => $matches[2], // Country code from language, e.g., en-US -> US
                    'region' => null,
                    'city' => null
                ];
            }
        }
    }
    
    return [];
}

/**
 * Get device information from user agent
 * 
 * @param string $userAgent User agent string
 * @return array Device data
 */
function getDeviceFromUserAgent($userAgent)
{
    $result = [
        'device_type' => 'Unknown',
        'browser' => 'Unknown',
        'os' => 'Unknown'
    ];
    
    // Simple device detection
    if (preg_match('/mobile|android|iphone|ipad|ipod/i', $userAgent)) {
        $result['device_type'] = 'Mobile';
    } elseif (preg_match('/tablet/i', $userAgent)) {
        $result['device_type'] = 'Tablet';
    } else {
        $result['device_type'] = 'Desktop';
    }
    
    // Simple browser detection
    if (preg_match('/MSIE|Trident/i', $userAgent)) {
        $result['browser'] = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $result['browser'] = 'Firefox';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $result['browser'] = 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $result['browser'] = 'Safari';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        $result['browser'] = 'Opera';
    }
    
    // Simple OS detection
    if (preg_match('/windows/i', $userAgent)) {
        $result['os'] = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $result['os'] = 'Mac OS';
    } elseif (preg_match('/linux/i', $userAgent)) {
        $result['os'] = 'Linux';
    } elseif (preg_match('/android/i', $userAgent)) {
        $result['os'] = 'Android';
    } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
        $result['os'] = 'iOS';
    }
    
    return $result;
}

/**
 * Get base URL of the application
 * 
 * @return string Base URL
 */
function getBaseUrl()
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    return $protocol . $domainName;
}

/**
 * Wrap links in the ad HTML with click tracking
 * 
 * @param string $html Ad HTML
 * @param int $adId Ad ID
 * @param int $positionId Position ID
 * @return string Modified HTML
 */
function wrapLinksWithTracking($html, $adId, $positionId)
{
    // Simple regex to find href attributes in anchors
    return preg_replace_callback(
        '/href=(["\'])(.*?)\1/i',
        function($matches) use ($adId, $positionId) {
            $originalUrl = $matches[2];
            $baseUrl = getBaseUrl();
            $clickTrackUrl = "{$baseUrl}/api/v1/click.php?ad_id={$adId}&position_id={$positionId}&url=" . urlencode($originalUrl);
            return "href={$matches[1]}{$clickTrackUrl}{$matches[1]}";
        },
        $html
    );
}
