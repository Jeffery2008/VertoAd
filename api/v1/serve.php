<?php

require_once __DIR__ . '/../../src/Services/AdService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

header('Content-Type: application/json');

$adService = new AdService();
$logger = new Logger();

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data', 400);
    }

    // Validate required fields
    if (!isset($data['position'])) {
        throw new Exception('Position ID is required', 400);
    }

    // Get targeting parameters
    $targeting = [
        'url' => $data['url'] ?? '',
        'referrer' => $data['referrer'] ?? '',
        'format' => $data['format'] ?? 'display',
        'viewport' => $data['viewport'] ?? null,
        'screen' => $data['screen'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'timestamp' => time()
    ];

    // Get matching ad for the position
    $ad = $adService->getAd($data['position'], $targeting);
    if (!$ad) {
        throw new Exception('No ad available', 404);
    }

    // Return ad data
    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $ad['id'],
            'position_id' => $ad['position_id'],
            'advertiser_id' => $ad['advertiser_id'],
            'image_url' => $ad['image_url'],
            'click_url' => $ad['click_url'],
            'format' => $ad['format'],
            'width' => $ad['width'],
            'height' => $ad['height']
        ]
    ]);

} catch (Exception $e) {
    $status = $e->getCode() ?: 500;
    http_response_code($status);

    // Log error
    $logger->error('Ad serve error', [
        'error' => $e->getMessage(),
        'code' => $status,
        'request' => $data ?? null
    ]);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
