<?php
/**
 * Ad Impression Tracking API
 * Handles recording of ad impressions
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

// Only accept GET/POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Initialize database connection
$db = new App\Utils\Database();
$logger = new App\Utils\Logger();

try {
    // Validate request parameters
    $adId = filter_input(INPUT_GET, 'ad_id', FILTER_VALIDATE_INT) ?: 
            filter_input(INPUT_POST, 'ad_id', FILTER_VALIDATE_INT);

    $positionId = filter_input(INPUT_GET, 'position_id', FILTER_VALIDATE_INT) ?:
                 filter_input(INPUT_POST, 'position_id', FILTER_VALIDATE_INT);

    // Additional parameters (optional)
    $viewerId = filter_input(INPUT_GET, 'viewer_id', FILTER_SANITIZE_STRING) ?:
                filter_input(INPUT_POST, 'viewer_id', FILTER_SANITIZE_STRING);

    // Validate required parameters
    if (!$adId || !$positionId) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    // Get visitor information
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Get advertisement and position details
    $adModel = new App\Models\Advertisement();
    $ad = $adModel->find($adId);

    if (!$ad) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Advertisement not found']);
        exit;
    }

    // Get ad position to verify match
    $positionModel = new App\Models\AdPosition();
    $position = $positionModel->find($positionId);

    if (!$position) {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Ad position not found']);
        exit;
    }

    // Verify the ad is associated with this position
    if ($ad['position_id'] != $positionId) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Ad does not match position']);
        exit;
    }

    // Check if ad is active
    if ($ad['status'] !== 'active') {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Ad is not active']);
        exit;
    }

    // Check ad dates
    $currentDate = date('Y-m-d');
    if ($currentDate < $ad['start_date'] || ($ad['end_date'] && $currentDate > $ad['end_date'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['error' => 'Ad is not currently scheduled for display']);
        exit;
    }

    // Get location information from IP (if possible)
    $locationData = getLocationFromIp($ipAddress);
    
    // Get device information from user agent
    $deviceData = getDeviceFromUserAgent($userAgent);
    
    // Calculate impression cost based on bid amount
    // For CPM (cost per thousand impressions), divide by 1000
    $cost = $ad['bid_amount'] / 1000;

    // Prepare impression data
    $impressionData = [
        'ad_id' => $adId,
        'position_id' => $positionId,
        'viewer_id' => $viewerId, // Will be generated if not provided
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'referer' => $referer,
        'cost' => $cost,
        // Add location data if available
        'location_country' => $locationData['country'] ?? null,
        'location_region' => $locationData['region'] ?? null,
        'location_city' => $locationData['city'] ?? null,
        // Add device data if available
        'device_type' => $deviceData['device_type'] ?? null,
        'browser' => $deviceData['browser'] ?? null,
        'os' => $deviceData['os'] ?? null,
    ];

    // Record the impression
    $impressionModel = new App\Models\Impression();
    $impressionId = $impressionModel->record($impressionData);
    
    if (!$impressionId) {
        // Error occurred while recording impression
        $logger->error('Failed to record impression', $impressionData);
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Failed to record impression']);
        exit;
    }

    // Update budget (deduct the cost)
    $remainingBudget = $ad['budget'] - $cost;
    if ($remainingBudget <= 0) {
        // Budget exhausted, deactivate the ad
        $adModel->update($adId, [
            'status' => 'completed',
            'budget' => 0
        ]);
    } else {
        // Update remaining budget
        $adModel->update($adId, [
            'budget' => $remainingBudget
        ]);
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'impression_id' => $impressionId
    ]);

} catch (Exception $e) {
    // Log the error
    $logger->error('Impression tracking error: ' . $e->getMessage());
    
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
