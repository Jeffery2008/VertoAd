<?php

namespace App\Controllers\Api\Advertiser;

use App\Services\ActivationKeyService;
use App\Core\BaseApiController; // Assuming a base controller for API responses
use App\Services\AuthService;  // Assuming an AuthService to get current user ID
use Exception;

class RedemptionController extends BaseApiController // Or your base controller class
{
    protected $keyService;
    protected $authService; // To get the currently authenticated user

    public function __construct(ActivationKeyService $keyService, AuthService $authService)
    {
        $this->keyService = $keyService;
        $this->authService = $authService;
        // parent::__construct(); // If needed
    }

    /**
     * POST /api/advertiser/redeem
     * Redeem an activation key.
     *
     * Expected JSON body:
     * {
     *   "key_string": "ABCDE-FGHIJ-KLMNO-PQRST-UVWXY",
     *   "apply_to_ad_id": 123 // Optional: Required if key type is duration_days
     * }
     */
    public function redeem() // Adjust method signature based on framework
    {
        // --- Get Authenticated User --- 
        // This part is crucial and depends heavily on your auth implementation
        // Assuming authService->getCurrentUserId() returns the ID or throws an exception/returns null if not logged in.
        try {
            $userId = $this->authService->getCurrentUserId();
            if (!$userId) {
                return $this->jsonResponse(['error' => 'Authentication required.'], 401);
            }
            // Optional: Check if user role is 'advertiser'
             if ($this->authService->getCurrentUserRole() !== 'advertiser') {
                 return $this->jsonResponse(['error' => 'Forbidden: Advertiser role required.'], 403);
             }

        } catch (Exception $e) {
            // Log exception
             return $this->jsonResponse(['error' => 'Authentication error.'], 401);
        }

        // --- Input Validation --- 
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
        }

        $keyString = filter_var($input['key_string'] ?? '', FILTER_SANITIZE_STRING);
        $applyToAdId = filter_var($input['apply_to_ad_id'] ?? null, FILTER_VALIDATE_INT);
        if ($applyToAdId === false) { // Ensure null if validation fails, not 0
            $applyToAdId = null;
        }

        if (empty($keyString)) {
            return $this->jsonResponse(['error' => 'Missing key_string.'], 400);
        }
        // Note: Further validation happens within the service (key exists, type matching ad id etc.)

        // --- Service Call --- 
        try {
            $result = $this->keyService->redeemKey($keyString, $userId, $applyToAdId);
            return $this->jsonResponse($result, 200); // 200 OK
        } catch (Exception $e) {
            // Log the exception $e->getMessage()
            // Provide specific error messages based on Exception type/message if needed
            return $this->jsonResponse(['error' => 'Redemption failed: ' . $e->getMessage()], 400); // 400 Bad Request for known errors
        }
    }

    // Placeholder for jsonResponse method (assuming it's in BaseApiController)
    protected function jsonResponse(array $data, int $statusCode = 200) 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit(); 
    }
} 