<?php

require_once __DIR__ . '/../../src/Services/AdService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

// Set JSON response type
header('Content-Type: application/json');

$adService = new AdService();
$logger = new Logger();

try {
    // Parse query parameters
    $positionId = $_GET['position_id'] ?? null;
    if (!$positionId) {
        throw new Exception('Position ID is required', 400);
    }

    // Get client info
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Handle different request types
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Serve ads for position
            $ads = $adService->getAdsForPosition($positionId, $ipAddress);
            
            // Record impressions
            foreach ($ads as $ad) {
                $adService->recordImpression($ad['id'], $ipAddress, $userAgent);
            }
            
            // Return ads
            echo json_encode([
                'success' => true,
                'data' => $ads
            ]);
            break;

        case 'POST':
            // Track click
            $adId = $_POST['ad_id'] ?? null;
            if (!$adId) {
                throw new Exception('Ad ID is required', 400);
            }
            
            $success = $adService->recordClick($adId, $ipAddress, $userAgent);
            
            echo json_encode([
                'success' => $success
            ]);
            break;

        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    // Log error
    $logger->error('Ad serving error', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'ip' => $ipAddress,
        'position_id' => $positionId ?? null
    ]);

    // Return error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
