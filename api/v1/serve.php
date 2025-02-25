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
    
    // Add targeting filters
    if (!empty($locationData['region'])) {
        // Location targeting will be implemented in a future update
        // For now, we're getting the location data but not filtering by it
    }
    
    if (!empty($deviceData['device_type'])) {
        // Device targeting will be implemented in a future update
        // For now, we're getting the device data but not filtering by it
    }
    
    // Add sorting by bid amount (highest bidder first)
    $query .= " ORDER BY a.bid_amount DESC";
    
    // Execute query
    $stmt = $db->prepare($query);
    $stmt->execute([
        'position_id' => $positionId
    ]);
    $ads = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    if (empty($ads)) {
        // No eligible ads found
        http_response_code(404);
        echo json_encode(['error' => 'No eligible advertisements found']);
        exit;
    }
    
    // Select the winning ad (currently just the highest bidder)
    $selectedAd = $ads[0];
    
    // Parse the ad content
    $content = json_decode($selectedAd['content'], true);
    
    // Build the ad HTML
    $adHtml = !empty($content['html']) ? $content['html'] : '';
    
    // Add tracking pixel for impression tracking
    $trackingUrl = getBaseUrl() . "/api/v1/track.php?ad_id={$selectedAd['id']}&position_id={$positionId}";
    $trackingPixel = "<img src=\"{$trackingUrl}\" style=\"position:absolute; visibility:hidden; width:1px; height:1px;\" alt=\"\" />";
    
    // Wrap links with click tracking (to be implemented)
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
        'referer' => $referer
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
                    'country' => 'China', // API returns only data for China
                    'region' => $data['pro'] ?? null,
                    'city' => $data['city'] ?? null
                ];
            }
        }
    } catch (Exception $e) {
        // Silent fail
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
            // In a future update, we'll implement click tracking
            // For now, return the original URL
            return "href={$matches[1]}{$originalUrl}{$matches[1]}";
        },
        $html
    );
}
