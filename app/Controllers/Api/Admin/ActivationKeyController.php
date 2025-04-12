<?php

namespace App\Controllers\Api\Admin;

use App\Services\ActivationKeyService;
use App\Core\BaseApiController; // Assuming a base controller for API responses
use Exception;
use Psr\Http\Message\ServerRequestInterface as Request;
// Depending on framework/routing, Request/Response objects might be injected or accessed globally
// use Psr\Http\Message\ResponseInterface as Response;

class ActivationKeyController extends BaseApiController // Or your base controller class
{
    protected $keyService;

    public function __construct(ActivationKeyService $keyService)
    {
        $this->keyService = $keyService;
        // Potentially call parent constructor if BaseApiController requires it
        // parent::__construct(); 
    }

    /**
     * POST /api/admin/cdkeys
     * Generate activation keys.
     *
     * Expected JSON body:
     * {
     *   "count": 10,
     *   "value_type": "duration_days", // or 'credit'
     *   "value": 30, // 30 days or 30 credits
     *   "expires_at": "2024-12-31T23:59:59Z" // Optional ISO 8601 format
     * }
     */
    public function generate(Request $request) // Adjust method signature based on framework (e.g., Request $request, Response $response)
    {
        // --- Input Validation --- 
        // This assumes you get JSON body parsed into an array/object.
        // Adapt based on your framework/request handling.
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->jsonResponse(['error' => 'Invalid JSON input.'], 400);
        }

        $count = filter_var($input['count'] ?? 0, FILTER_VALIDATE_INT);
        $valueType = filter_var($input['value_type'] ?? '', FILTER_SANITIZE_STRING);
        $value = filter_var($input['value'] ?? 0, FILTER_VALIDATE_FLOAT);
        $expiresAtInput = filter_var($input['expires_at'] ?? null, FILTER_SANITIZE_STRING);
        $expiresAt = null;

        if ($count === false || $count <= 0) {
            return $this->jsonResponse(['error' => 'Invalid or missing count (must be positive integer).'], 400);
        }
        if (empty($valueType) || !in_array($valueType, ['duration_days', 'credit'])) {
            return $this->jsonResponse(['error' => 'Invalid or missing value_type (must be duration_days or credit).'], 400);
        }
         if ($value === false || $value <= 0) {
            return $this->jsonResponse(['error' => 'Invalid or missing value (must be positive number).'], 400);
        }
        if ($expiresAtInput) {
            try {
                // Convert ISO 8601 or similar to MySQL DATETIME format
                $expiresAt = (new \DateTime($expiresAtInput))->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                return $this->jsonResponse(['error' => 'Invalid expires_at date format.'], 400);
            }
        }

        // --- Service Call --- 
        try {
            $generatedKeys = $this->keyService->generateKeys($count, $valueType, $value, $expiresAt);
            return $this->jsonResponse(['generated_keys' => $generatedKeys], 201); // 201 Created
        } catch (Exception $e) {
            // Log the exception $e->getMessage()
            return $this->jsonResponse(['error' => 'Failed to generate keys: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/admin/cdkeys
     * List/search activation keys.
     */
    public function listKeys(Request $request) // Assuming Request object passed by Router
    {
        try {
            // Get pagination and filter parameters from query string
            // Use the getQueryParam method assumed to exist on Request object
            $page = filter_var($request->getQueryParam('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 1]]);
            $limit = filter_var($request->getQueryParam('limit', 20), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100, 'default' => 20]]);
            $status = filter_var($request->getQueryParam('status'), FILTER_SANITIZE_STRING);
             // Basic validation for status
            if ($status !== null && !in_array($status, ['unused', 'used', ''])) {
                return $this->jsonResponse(['error' => 'Invalid status filter.'], 400);
            }
            
            $result = $this->keyService->getKeys($page, $limit, $status ?: null);
            return $this->jsonResponse($result, 200);

        } catch (Exception $e) {
             error_log("List Keys Exception: " . $e->getMessage());
             return $this->jsonResponse(['error' => 'An internal error occurred while fetching keys.'], 500);
        }
    }

    // Placeholder for jsonResponse method (assuming it's in BaseApiController)
    // Adjust based on your actual base controller/framework response handling
    protected function jsonResponse(array $data, int $statusCode = 200) 
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        // Depending on framework, you might return a Response object instead
        // e.g., return $response->withJson($data, $statusCode);
        exit(); // Or let framework handle exiting
    }
} 