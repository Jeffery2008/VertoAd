<?php
require_once '../../src/Services/AdService.php';
require_once '../../src/Utils/Logger.php';
require_once '../../src/Models/Advertisement.php';

// Initialize services
$adService = new AdService();
$logger = new Logger();

try {
    // Get request parameters
    $type = $_GET['type'] ?? null;
    $adId = $_GET['ad_id'] ?? null;
    $impressionId = $_GET['impression_id'] ?? null;
    
    if (!$type || !$adId) {
        throw new Exception('Missing required parameters');
    }
    
    // Get client information
    $clientInfo = json_decode(file_get_contents('php://input'), true) ?: [];
    
    // Default client info if not provided
    if (!isset($clientInfo['device'])) {
        $clientInfo['device'] = [
            'type' => detectDeviceType(),
            'os' => getOSInfo(),
            'browser' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
    }
    
    if (!isset($clientInfo['geo'])) {
        $clientInfo['geo'] = [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'country' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null
        ];
    }
    
    // Handle different tracking events
    switch ($type) {
        case 'impression':
            if (!$impressionId) {
                $impressionId = generateImpressionId($adId, $clientInfo);
            }
            $adService->trackImpression($adId, $impressionId, $clientInfo);
            break;
            
        case 'click':
            if (!$impressionId) {
                throw new Exception('Impression ID required for click tracking');
            }
            $adService->trackClick($adId, $impressionId, $clientInfo);
            
            // Get ad destination URL and redirect
            $ad = (new Advertisement())->findById($adId);
            if ($ad && !empty($ad['destination_url'])) {
                // Add tracking parameters to destination URL
                $destinationUrl = appendTrackingParams($ad['destination_url'], [
                    'ad_id' => $adId,
                    'impression_id' => $impressionId
                ]);
                
                header('Location: ' . $destinationUrl);
                exit;
            }
            break;
            
        case 'conversion':
            if (!$impressionId) {
                throw new Exception('Impression ID required for conversion tracking');
            }
            $adService->trackConversion($adId, $impressionId, $clientInfo);
            break;
            
        default:
            throw new Exception('Invalid tracking type');
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Event tracked successfully'
    ]);
    
} catch (Exception $e) {
    $logger->error('Error tracking event: ' . $e->getMessage(), [
        'type' => $type ?? null,
        'ad_id' => $adId ?? null,
        'impression_id' => $impressionId ?? null,
        'client_info' => $clientInfo ?? null
    ]);
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Helper function to detect device type
 */
function detectDeviceType() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', $userAgent)) {
        return 'tablet';
    }
    
    if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $userAgent)) {
        return 'mobile';
    }
    
    return 'desktop';
}

/**
 * Helper function to get OS information
 */
function getOSInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $os = 'unknown';
    
    $osArray = [
        '/windows nt 10/i'      => 'Windows 10',
        '/windows nt 6.3/i'     => 'Windows 8.1',
        '/windows nt 6.2/i'     => 'Windows 8',
        '/windows nt 6.1/i'     => 'Windows 7',
        '/macintosh|mac os x/i' => 'Mac OS X',
        '/mac_powerpc/i'        => 'Mac OS 9',
        '/linux/i'              => 'Linux',
        '/ubuntu/i'             => 'Ubuntu',
        '/iphone/i'             => 'iOS',
        '/ipad/i'               => 'iOS',
        '/android/i'            => 'Android',
    ];
    
    foreach ($osArray as $regex => $value) {
        if (preg_match($regex, $userAgent)) {
            $os = $value;
            break;
        }
    }
    
    return $os;
}

/**
 * Generate a unique impression ID
 */
function generateImpressionId($adId, $clientInfo) {
    $data = [
        'ad_id' => $adId,
        'timestamp' => microtime(true),
        'client' => hash('sha256', json_encode($clientInfo)),
        'random' => bin2hex(random_bytes(8))
    ];
    
    return hash('sha256', json_encode($data));
}

/**
 * Append tracking parameters to URL
 */
function appendTrackingParams($url, $params) {
    $parsedUrl = parse_url($url);
    
    // Start with base URL
    $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
    
    // Add path if exists
    if (isset($parsedUrl['path'])) {
        $newUrl .= $parsedUrl['path'];
    }
    
    // Get existing query parameters
    $existingParams = [];
    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $existingParams);
    }
    
    // Merge with new parameters
    $allParams = array_merge($existingParams, $params);
    
    // Add query string
    $newUrl .= '?' . http_build_query($allParams);
    
    // Add fragment if exists
    if (isset($parsedUrl['fragment'])) {
        $newUrl .= '#' . $parsedUrl['fragment'];
    }
    
    return $newUrl;
}
