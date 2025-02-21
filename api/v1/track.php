<?php

require_once __DIR__ . '/../../src/Services/AdService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$adService = new AdService();
$logger = new Logger();

try {
    // Validate request parameters
    $type = $_GET['type'] ?? null;
    $adId = $_GET['ad_id'] ?? null;

    if (!$type || !$adId) {
        throw new Exception('Missing required parameters', 400);
    }

    // Collect tracking data
    $data = [
        'ad_id' => intval($adId),
        'timestamp' => time(),
        'url' => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'device_type' => getDeviceType($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'viewport' => $_GET['viewport'] ?? null,
        'position' => $_GET['position'] ?? null
    ];

    // Record tracking event based on type
    switch ($type) {
        case 'impression':
            $adService->recordImpression($data);
            break;
            
        case 'viewable':
            $adService->recordViewability($data);
            break;
            
        case 'click':
            $adService->recordClick($data);
            break;
            
        default:
            throw new Exception('Invalid tracking type', 400);
    }

    // Return 1x1 transparent GIF
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

} catch (Exception $e) {
    $status = $e->getCode() ?: 500;
    http_response_code($status);

    // Log error
    $logger->error('Tracking error', [
        'type' => $type ?? null,
        'ad_id' => $adId ?? null,
        'error' => $e->getMessage(),
        'code' => $status
    ]);

    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Helper function to determine device type from user agent
 */
function getDeviceType($userAgent) {
    $tablet = "/tablet|ipad|playbook|silk/i";
    $mobile = "/mobile|android|iphone|phone|opera mini|iemobile/i";
    
    if (preg_match($tablet, $userAgent)) {
        return 'tablet';
    } else if (preg_match($mobile, $userAgent)) {
        return 'mobile';
    } else {
        return 'desktop';
    }
}
