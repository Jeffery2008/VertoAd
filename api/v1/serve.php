<?php
/**
 * Ad Serving API Endpoint
 * Serves ads to iframes based on position and targeting
 */

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\AdService;
use App\Services\CompetitionService;
use App\Utils\Logger;

header('Content-Type: application/json');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

$logger = new Logger('serve-api');
$adService = new AdService();
$competitionService = new CompetitionService();

try {
    // Get ad position
    $positionId = isset($_GET['position']) ? intval($_GET['position']) : null;
    if (!$positionId) {
        throw new \Exception('Position ID is required');
    }

    // Get targeting data
    $targeting = [
        'url' => $_SERVER['HTTP_REFERER'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'timestamp' => time(),
        'viewport' => [
            'width' => isset($_GET['vw']) ? intval($_GET['vw']) : null,
            'height' => isset($_GET['vh']) ? intval($_GET['vh']) : null
        ]
    ];

    // Device detection
    $deviceType = 'desktop';
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobile))/i', $targeting['user_agent'])) {
        $deviceType = 'tablet';
    } else if (preg_match('/(mobile|android|iphone|ipod|webos)/i', $targeting['user_agent'])) {
        $deviceType = 'mobile';
    }
    $targeting['device_type'] = $deviceType;

    // Get geographic info if available
    if (function_exists('geoip_record_by_name')) {
        $geo = @geoip_record_by_name($targeting['ip']);
        if ($geo) {
            $targeting['geo'] = [
                'country' => $geo['country_code'],
                'region' => $geo['region'],
                'city' => $geo['city']
            ];
        }
    }

    // Get eligible ads for this position
    $eligibleAds = $adService->getEligibleAds($positionId, $targeting);
    if (empty($eligibleAds)) {
        echo json_encode([
            'success' => true,
            'ad' => null
        ]);
        exit;
    }

    // Run auction/competition
    $winningAd = $competitionService->runAuction($eligibleAds, $targeting);
    if (!$winningAd) {
        throw new \Exception('Failed to select winning ad');
    }

    // Format ad content for serving
    $adContent = $adService->prepareAdContent($winningAd);

    // Log winning ad selection
    $logger->info('Ad served', [
        'position_id' => $positionId,
        'ad_id' => $winningAd['id'],
        'advertiser_id' => $winningAd['advertiser_id'],
        'device_type' => $deviceType,
        'url' => $targeting['url']
    ]);

    // Return winning ad
    echo json_encode([
        'success' => true,
        'ad' => [
            'id' => $winningAd['id'],
            'content' => $adContent,
            'click_url' => $winningAd['click_url'],
            'width' => $winningAd['width'],
            'height' => $winningAd['height']
        ]
    ]);

} catch (\Exception $e) {
    $logger->error('Error serving ad', [
        'error' => $e->getMessage(),
        'position_id' => $positionId ?? null
    ]);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to serve ad'
    ]);
}
