<?php

namespace App\Controllers\Api\Admin;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\AdService;
use Exception;

class AdController
{
    private AdService $adService;

    public function __construct(AdService $adService)
    {
        $this->adService = $adService;
    }

    /**
     * GET /api/admin/ads
     * List ads with filtering (e.g., status=pending for review).
     *
     * @param Request $request
     * @return Response
     */
    public function list(Request $request): Response
    {
        try {
            $page = filter_var($request->getQueryParam('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
            $limit = filter_var($request->getQueryParam('limit', 20), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100, 'default' => 20]]);
            $status = filter_var($request->getQueryParam('status'), FILTER_SANITIZE_STRING);
            $userId = filter_var($request->getQueryParam('user_id'), FILTER_VALIDATE_INT);

            // Validate status if provided (or let service handle it)
            // ...

            $result = $this->adService->getAds($page, $limit, $status ?: null, $userId ?: null);
            return new Response($result, 200);

        } catch (Exception $e) {
             error_log("Admin List Ads Exception: " . $e->getMessage());
             return new Response(['error' => 'An internal server error occurred while fetching ads.'], 500);
        }
    }

    /**
     * Approve an ad.
     *
     * @param Request $request
     * @param int $id Ad ID from URL parameter.
     * @return Response
     */
    public function approve(Request $request, int $id): Response
    {
        try {
            $success = $this->adService->approveAd($id);

            if ($success) {
                return new Response(['message' => 'Ad approved successfully.'], 200);
            } else {
                // AdService logs errors, so just return a generic failure message
                return new Response(['error' => 'Failed to approve ad.'], 400); // 400 or 404 if not found? Service handles not found.
            }
        } catch (Exception $e) {
            error_log("Admin AdController Approve Exception: " . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    /**
     * Reject an ad.
     *
     * @param Request $request
     * @param int $id Ad ID from URL parameter.
     * @return Response
     */
    public function reject(Request $request, int $id): Response
    {
         try {
            $success = $this->adService->rejectAd($id);

            if ($success) {
                return new Response(['message' => 'Ad rejected successfully.'], 200);
            } else {
                 // AdService logs errors
                return new Response(['error' => 'Failed to reject ad.'], 400);
            }
        } catch (Exception $e) {
            error_log("Admin AdController Reject Exception: " . $e->getMessage());
            return new Response(['error' => 'An internal server error occurred.'], 500);
        }
    }

    // TODO: Add method to list pending ads for admin review?
    // public function listPendingAds(Request $request): Response { ... }
} 