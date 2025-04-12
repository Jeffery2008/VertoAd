<?php

namespace App\Controllers\Api;

use App\Services\AdService;
use App\Services\TrackingService;
use App\Core\BaseApiController; // Assuming a base controller 
use Exception;

class ServeController extends BaseApiController // Or your base controller class
{
    protected $adService;
    protected $trackingService;

    public function __construct(AdService $adService, TrackingService $trackingService)
    {
        $this->adService = $adService;
        $this->trackingService = $trackingService;
        // parent::__construct(); // If needed
    }

    /**
     * GET /api/serve/ad/{zone_id}
     * Serves the HTML content for an eligible ad for the given zone.
     *
     * @param int $zoneId The ID of the zone requesting an ad.
     */
    public function serveAd(int $zoneId) // Adjust signature based on framework/routing for path param
    {
        if (empty($zoneId) || !filter_var($zoneId, FILTER_VALIDATE_INT) || $zoneId <= 0) {
            return $this->htmlResponse('<!-- Invalid Zone ID -->', 400);
        }

        try {
            // 1. Get eligible ad data (ID and Delta JSON)
            $adData = $this->adService->getEligibleAdForZone($zoneId);

            if (!$adData) {
                // No ad available or eligible for this zone
                return $this->htmlResponse('<!-- No ad available -->', 204); // 204 No Content is suitable
            }

            // 2. Render the Quill Delta to HTML
            $adHtml = $this->adService->renderAdHtml($adData['delta']);

            // 3. Record the impression (view)
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            // Run tracking in a way that doesn't block the response if possible,
            // but for simplicity, call it directly. Log errors within service.
            $this->trackingService->recordImpression($adData['id'], $zoneId, $ipAddress, $userAgent);

            // 4. Return the HTML content
            // We should also set CORS headers if this API is called cross-domain
            // header("Access-Control-Allow-Origin: *"); // Be more specific in production
            return $this->htmlResponse($adHtml, 200);

        } catch (Exception $e) {
            // Log the exception $e->getMessage()
            error_log("Error serving ad for zone {$zoneId}: " . $e->getMessage());
            return $this->htmlResponse('<!-- Ad server error -->', 500);
        }
    }

    // Helper for HTML response (could be in BaseApiController)
    protected function htmlResponse(string $html, int $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');
        // Add basic CORS header for ad serving
        header('Access-Control-Allow-Origin: *'); // Consider making this more restrictive if possible
        // Add other headers like Cache-Control if needed
        // header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $html;
        exit();
    }
    
    // Placeholder for jsonResponse method (assuming it's in BaseApiController)
    // Needed if BaseApiController doesn't exist or is different
    protected function jsonResponse(array $data, int $statusCode = 200) 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit(); 
    }
} 