<?php

require_once __DIR__ . '/../../src/Services/AdService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

// Set JSON response type
header('Content-Type: application/json');

$adService = new AdService();
$logger = new Logger();

try {
    // Require POST method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Parse POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data', 400);
    }

    // Validate required fields
    $required = ['ad_id', 'event_type'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }

    // Get client info
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $sessionId = $data['session_id'] ?? session_id();

    // Handle different event types
    switch ($data['event_type']) {
        case 'impression':
            $success = $adService->recordImpression($data['ad_id'], $ipAddress, $userAgent);
            break;

        case 'click':
            $success = $adService->recordClick($data['ad_id'], $ipAddress, $userAgent);
            break;

        case 'viewability':
            // Record viewability metrics
            $success = $adService->recordMetric(
                $data['ad_id'],
                'viewability',
                $data['visible_percentage'] ?? 0,
                $sessionId
            );
            break;

        case 'engagement':
            // Record engagement metrics (hover time, interaction time, etc.)
            $success = $adService->recordMetric(
                $data['ad_id'],
                'engagement',
                $data['engagement_time'] ?? 0,
                $sessionId
            );
            break;

        case 'performance':
            // Record loading and rendering performance
            $additionalData = [
                'render_time' => $data['render_time'] ?? null,
                'resource_load_time' => $data['resource_load_time'] ?? null,
                'first_paint' => $data['first_paint'] ?? null
            ];
            
            $success = $adService->recordMetric(
                $data['ad_id'],
                'performance',
                $data['load_time'] ?? 0,
                $sessionId,
                $additionalData
            );
            break;

        case 'error':
            // Log client-side errors
            $logger->error('Client-side ad error', [
                'ad_id' => $data['ad_id'],
                'error' => $data['error_message'] ?? 'Unknown error',
                'stack' => $data['error_stack'] ?? null,
                'browser' => $userAgent,
                'ip' => $ipAddress
            ]);
            $success = true;
            break;

        default:
            throw new Exception('Unknown event type', 400);
    }

    echo json_encode([
        'success' => $success
    ]);

} catch (Exception $e) {
    // Log error
    $logger->error('Tracking error', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'data' => $data ?? null
    ]);

    // Return error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
