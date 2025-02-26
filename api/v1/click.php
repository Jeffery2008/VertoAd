<?php
/**
 * Ad Click Tracking API
 * Handles recording of ad clicks and redirecting to destination URL
 */

// Initialize the application
require_once __DIR__ . '/../../config/init.php';

// Initialize database connection and logger
$db = new VertoAD\Core\Utils\Database();
$logger = new VertoAD\Core\Utils\Logger();

try {
    // Validate request parameters
    $adId = filter_input(INPUT_GET, 'ad_id', FILTER_VALIDATE_INT);
    $impressionId = filter_input(INPUT_GET, 'impression_id', FILTER_VALIDATE_INT);
    $redirect = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);
    
    // Fallback for direct impressions without tracking
    if (!$impressionId && $adId) {
        $impressionId = createImpressionRecord($adId);
    }
    
    // Validate required parameters
    if (!$impressionId || !$redirect) {
        http_response_code(400); // Bad Request
        $logger->error('Click tracking missing parameters', [
            'ad_id' => $adId,
            'impression_id' => $impressionId,
            'redirect' => $redirect
        ]);
        echo 'Invalid parameters';
        exit;
    }
    
    // Get visitor information
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // Record the click
    $clickModel = new VertoAD\Core\Models\Click();
    $clickData = [
        'impression_id' => $impressionId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'referer' => $referer
    ];
    
    $clickId = $clickModel->record($clickData);
    
    if (!$clickId) {
        $logger->error('Failed to record click', $clickData);
    } else {
        $logger->info('Click recorded', [
            'click_id' => $clickId,
            'impression_id' => $impressionId,
            'ad_id' => $adId
        ]);
    }
    
    // Redirect to the destination URL
    header("Location: $redirect");
    exit;
    
} catch (Exception $e) {
    // Log the error
    $logger->error('Click tracking error: ' . $e->getMessage());
    
    // Redirect to default URL in case of error
    if (!empty($redirect)) {
        header("Location: $redirect");
    } else {
        http_response_code(500);
        echo 'An error occurred during click tracking';
    }
    exit;
}

/**
 * Create an impression record when a click occurs without prior impression
 * 
 * @param int $adId Advertisement ID
 * @return int|null The impression ID or null on failure
 */
function createImpressionRecord($adId)
{
    global $db, $logger;
    
    try {
        // Get ad information
        $adModel = new VertoAD\Core\Models\Advertisement();
        $ad = $adModel->find($adId);
        
        if (!$ad) {
            $logger->error('Click tracking - ad not found', ['ad_id' => $adId]);
            return null;
        }
        
        // Get visitor information
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Calculate impression cost based on bid amount
        $cost = $ad['bid_amount'] / 1000;
        
        // Create an impression record
        $impressionModel = new VertoAD\Core\Models\Impression();
        $impressionData = [
            'ad_id' => $adId,
            'position_id' => $ad['position_id'],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'cost' => $cost
        ];
        
        return $impressionModel->record($impressionData);
        
    } catch (Exception $e) {
        $logger->error('Failed to create impression for click: ' . $e->getMessage());
        return null;
    }
} 