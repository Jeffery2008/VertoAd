<?php

namespace App\Controllers\Api\Publisher;

use App\Services\PublisherService;
use App\Services\AuthService;
use App\Core\BaseApiController;
use App\Core\Request;
use Exception;

class ZoneController extends BaseApiController
{
    protected $publisherService;
    protected $authService;

    public function __construct(PublisherService $publisherService, AuthService $authService)
    {
        $this->publisherService = $publisherService;
        $this->authService = $authService;
    }

    /**
     * GET /api/publisher/zones
     * List zones for the authenticated publisher.
     */
    public function list(Request $request)
    {
        try {
            $publisherId = $this->authService->getCurrentUserId();
            if (!$publisherId) {
                return $this->jsonResponse(['error' => 'Authentication required.'], 401);
            }
            // Ensure role is publisher (Middleware should handle this, but double-check)
             if ($this->authService->getCurrentUserRole() !== 'publisher') {
                 return $this->jsonResponse(['error' => 'Forbidden: Publisher role required.'], 403);
             }

            $zones = $this->publisherService->getZonesForPublisher($publisherId);
            return $this->jsonResponse(['zones' => $zones], 200);

        } catch (Exception $e) {
            error_log("List Publisher Zones Exception: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred while fetching zones.'], 500);
        }
    }
    
     /**
     * POST /api/publisher/zones
     * Create a new zone for the authenticated publisher.
     */
    public function create(Request $request)
    {
        try {
            $publisherId = $this->authService->getCurrentUserId();
             if (!$publisherId) {
                 return $this->jsonResponse(['error' => 'Authentication required.'], 401);
             }
             if ($this->authService->getCurrentUserRole() !== 'publisher') {
                  return $this->jsonResponse(['error' => 'Forbidden: Publisher role required.'], 403);
             }

            $input = $request->getJsonBody();
            
            $name = filter_var($input['name'] ?? '', FILTER_SANITIZE_STRING);
            $description = filter_var($input['description'] ?? null, FILTER_SANITIZE_STRING);
            $width = filter_var($input['width'] ?? 0, FILTER_VALIDATE_INT);
            $height = filter_var($input['height'] ?? 0, FILTER_VALIDATE_INT);

            if (empty($name) || $width === false || $width <= 0 || $height === false || $height <= 0) {
                return $this->jsonResponse(['error' => 'Missing or invalid required fields: name, width, height'], 400);
            }

            $zoneId = $this->publisherService->createZone($publisherId, $name, $description, $width, $height);

            if ($zoneId) {
                return $this->jsonResponse(['message' => 'Zone created successfully', 'zone_id' => $zoneId], 201);
            } else {
                return $this->jsonResponse(['error' => 'Failed to create zone.'], 500);
            }
        } catch (Exception $e) {
            error_log("Create Zone Exception: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred during zone creation.'], 500);
        }
    }

    // Inherit or define jsonResponse
    protected function jsonResponse(array $data, int $statusCode = 200) 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit(); 
    }
} 