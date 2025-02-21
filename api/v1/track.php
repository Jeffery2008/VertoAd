<?php
/**
 * Ad Tracking API Endpoint
 * Handles impression, click, and viewability tracking
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\AdService;
use App\Utils\Logger;
use App\Utils\Validator;

header('Content-Type: application/json');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$logger = new Logger('track-api');
$adService = new AdService();
$validator = new Validator();

try {
    // Get tracking type from URL path
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $trackingType = basename($path);
    if ($trackingType === 'track.php') {
        $trackingType = 'impression';
    }

    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new \Exception('Invalid request data');
    }

    // Validate required fields
    $required = ['ad_id', 'position_id'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new \Exception("Missing required field: {$field}");
        }
    }

    // Validate IDs
    $adId = $validator->validateInteger($data['ad_id']);
    $positionId = $validator->validateInteger($data['position_id']);

    // Common tracking data
    $trackingData = [
        'ad_id' => $adId,
        'position_id' => $positionId,
        'url' => $data['url'] ?? $_SERVER['HTTP_REFERER'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'timestamp' => date('Y-m-d H:i:s'),
        'device_type' => detectDeviceType($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'viewport' => $data['viewport'] ?? null,
        'position' => $data['position'] ?? null
    ];

    // Process based on tracking type
    switch ($trackingType) {
        case 'impression':
            $result = $adService->recordImpression($trackingData);
            break;

        case 'click':
            // Add click-specific data
            $trackingData['referrer'] = $_SERVER['HTTP_REFERER'] ?? '';
            $result = $adService->recordClick($trackingData);
            break;

        case 'viewability':
            // Add viewability-specific data
            $trackingData['visible_time'] = $validator->validateInteger($data['visible_time'] ?? 0);
            $result = $adService->recordViewability($trackingData);
            break;

        default:
            throw new \Exception('Invalid tracking type');
    }

    // Log tracking event
    $logger->info("Tracked {$trackingType}", [
        'ad_id' => $adId,
        'position_id' => $positionId,
        'url' => $trackingData['url']
    ]);

    // Return success response
    echo json_encode([
        'success' => true,
        'tracking_id' => $result['tracking_id'] ?? null
    ]);

} catch (\Exception $e) {
    $logger->error('Tracking error', [
        'error' => $e->getMessage(),
        'type' => $trackingType ?? 'unknown'
    ]);

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to record tracking event'
    ]);
}

/**
 * Detect device type from user agent
 */
function detectDeviceType($userAgent) {
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $userAgent)) {
        return 'tablet';
    }
    if (preg_match('/(mobile|android|iphone|ipod|webos)/i', $userAgent)) {
        return 'mobile';
    }
    return 'desktop';
}
