<?php

namespace App\Controllers\Api\Advertiser;

use App\Services\AdService;
use App\Services\AuthService;
use App\Core\BaseApiController; // Assuming base controller
use App\Core\Request; // Assuming Request object is passed by router
use Exception;

class AdController extends BaseApiController
{
    protected $adService;
    protected $authService;

    public function __construct(AdService $adService, AuthService $authService)
    {
        $this->adService = $adService;
        $this->authService = $authService;
    }

    /**
     * POST /api/advertiser/ads
     * Create a new ad.
     */
    public function create(Request $request)
    {
        try {
            $userId = $this->authService->getCurrentUserId(); // Assumes middleware already validated auth
            $input = $request->getJsonBody(); // Assuming method exists to get parsed JSON body

            if (!$input || empty($input['name']) || empty($input['target_url']) || empty($input['content_quill_delta'])) {
                return $this->jsonResponse(['error' => 'Missing required fields: name, target_url, content_quill_delta'], 400);
            }

            $adId = $this->adService->createAd($userId, [
                'name' => filter_var($input['name'], FILTER_SANITIZE_STRING),
                'target_url' => filter_var($input['target_url'], FILTER_VALIDATE_URL),
                'content_quill_delta' => $input['content_quill_delta'] // Assume it's already an object/array from JSON decode
            ]);

            if ($adId) {
                return $this->jsonResponse(['message' => 'Ad created successfully', 'ad_id' => $adId], 201);
            } else {
                return $this->jsonResponse(['error' => 'Failed to create ad.'], 500);
            }
        } catch (Exception $e) {
            error_log("Ad Creation Exception: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred during ad creation.'], 500);
        }
    }

    /**
     * PUT /api/advertiser/ads/{id}
     * Update an existing ad.
     */
    public function update(Request $request, int $id) // $id comes from route parameter
    {
         try {
            $userId = $this->authService->getCurrentUserId();
            $input = $request->getJsonBody();

            if (!$input || empty($input['name']) || empty($input['target_url']) || empty($input['content_quill_delta'])) {
                return $this->jsonResponse(['error' => 'Missing required fields: name, target_url, content_quill_delta'], 400);
            }
            
            // Ensure target_url is valid
            $targetUrl = filter_var($input['target_url'], FILTER_VALIDATE_URL);
            if ($targetUrl === false) {
                 return $this->jsonResponse(['error' => 'Invalid target_url format.'], 400);
            }

            $success = $this->adService->updateAd($id, $userId, [
                'name' => filter_var($input['name'], FILTER_SANITIZE_STRING),
                'target_url' => $targetUrl,
                'content_quill_delta' => $input['content_quill_delta']
            ]);

            if ($success) {
                return $this->jsonResponse(['message' => 'Ad updated successfully'], 200);
            } else {
                // AdService logs specific errors (not found, ownership, db error)
                return $this->jsonResponse(['error' => 'Failed to update ad. Check logs for details.'], 404); // 404 or 500 depending on cause
            }
        } catch (Exception $e) {
            error_log("Ad Update Exception (ID: {$id}): " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred during ad update.'], 500);
        }
    }

    /**
     * GET /api/advertiser/ads/{id}
     * Get ad details for editing.
     */
    public function get(Request $request, int $id)
    {
        try {
            $userId = $this->authService->getCurrentUserId();
            $ad = $this->adService->getAdByIdForUser($id, $userId);

            if ($ad) {
                return $this->jsonResponse($ad, 200);
            } else {
                return $this->jsonResponse(['error' => 'Ad not found or access denied.'], 404);
            }
        } catch (Exception $e) {
            error_log("Get Ad Exception (ID: {$id}): " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred while fetching the ad.'], 500);
        }
    }

    /**
     * GET /api/advertiser/ads
     * List ads for the authenticated advertiser.
     */
    public function list(Request $request)
    {
        try {
            $userId = $this->authService->getCurrentUserId();
            
            // Get pagination and filter parameters from query string
            $page = filter_var($request->getQueryParam('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
            $limit = filter_var($request->getQueryParam('limit', 20), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100, 'default' => 20]]);
            $status = filter_var($request->getQueryParam('status'), FILTER_SANITIZE_STRING); // Allow filtering by status
            // TODO: Validate status against allowed ENUM values if necessary

            $result = $this->adService->getAdsForUser($userId, $page, $limit, $status);

            return $this->jsonResponse($result, 200);

        } catch (Exception $e) {
            error_log("List Ads Exception: " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred while fetching ads.'], 500);
        }
    }

    /**
     * DELETE /api/advertiser/ads/{id}
     * Delete an ad.
     */
    public function delete(Request $request, int $id)
    {
        try {
            $userId = $this->authService->getCurrentUserId();
            $success = $this->adService->deleteAd($id, $userId);

            if ($success) {
                return $this->jsonResponse(['message' => 'Ad deleted successfully'], 200); // Or 204 No Content
            } else {
                // Service logs reason (not found/owner)
                return $this->jsonResponse(['error' => 'Failed to delete ad. It might not exist or you might not own it.'], 404);
            }
        } catch (Exception $e) {
            error_log("Delete Ad Exception (ID: {$id}): " . $e->getMessage());
            return $this->jsonResponse(['error' => 'An internal error occurred during ad deletion.'], 500);
        }
    }

    // TODO: Implement list() and delete() methods
    // GET /api/advertiser/ads
    // DELETE /api/advertiser/ads/{id}
    
    // Placeholder for jsonResponse method (assuming it's in BaseApiController)
    protected function jsonResponse(array $data, int $statusCode = 200) 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit(); 
    }
} 