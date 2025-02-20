<?php
require_once '../../src/Services/AdService.php';
require_once '../../src/Utils/Validator.php';
require_once '../../src/Utils/Logger.php';
require_once '../../src/Services/AuthService.php';
require_once '../../src/Models/Advertisement.php';

// Initialize services
$adService = new AdService();
$validator = new Validator();
$logger = new Logger();
$authService = new AuthService();

try {
    // Validate authentication
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authService->validateToken($authHeader)) {
        throw new Exception('Unauthorized access', 401);
    }
    
    // Get request method and data
    $method = $_SERVER['REQUEST_METHOD'];
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    
    switch ($method) {
        case 'GET':
            // Handle GET requests
            $id = $_GET['id'] ?? null;
            if ($id) {
                // Get specific ad
                $ad = $adService->getAd($id);
                if (!$ad) {
                    throw new Exception('Advertisement not found', 404);
                }
                $response = $ad;
            } else {
                // List ads with filters
                $filters = [
                    'status' => $_GET['status'] ?? null,
                    'type' => $_GET['type'] ?? null,
                    'advertiser_id' => $_GET['advertiser_id'] ?? null,
                    'position_id' => $_GET['position_id'] ?? null,
                    'page' => $_GET['page'] ?? 1,
                    'per_page' => $_GET['per_page'] ?? 20
                ];
                $response = $adService->listAds($filters);
            }
            break;
            
        case 'POST':
            // Validate required fields
            $requiredFields = ['type', 'content', 'position_id', 'start_date', 'end_date'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Missing required field: {$field}", 400);
                }
            }
            
            // Validate dates
            if (!$validator->validateDateRange($data['start_date'], $data['end_date'])) {
                throw new Exception('Invalid date range', 400);
            }
            
            // Create new ad
            $response = $adService->createAd($data);
            break;
            
        case 'PUT':
            // Validate ad ID
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Advertisement ID is required', 400);
            }
            
            // Update ad
            $response = $adService->updateAd($id, $data);
            if (!$response) {
                throw new Exception('Advertisement not found', 404);
            }
            break;
            
        case 'DELETE':
            // Validate ad ID
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Advertisement ID is required', 400);
            }
            
            // Delete ad
            $success = $adService->deleteAd($id);
            if (!$success) {
                throw new Exception('Advertisement not found', 404);
            }
            
            $response = ['message' => 'Advertisement deleted successfully'];
            break;
            
        case 'PATCH':
            // Validate ad ID
            $id = $_GET['id'] ?? null;
            if (!$id) {
                throw new Exception('Advertisement ID is required', 400);
            }
            
            // Handle specific actions
            $action = $data['action'] ?? '';
            switch ($action) {
                case 'activate':
                    $response = $adService->activateAd($id);
                    break;
                    
                case 'deactivate':
                    $response = $adService->deactivateAd($id);
                    break;
                    
                case 'update_budget':
                    if (!isset($data['budget'])) {
                        throw new Exception('Budget amount is required', 400);
                    }
                    $response = $adService->updateAdBudget($id, $data['budget']);
                    break;
                    
                default:
                    throw new Exception('Invalid action specified', 400);
            }
            break;
            
        default:
            throw new Exception('Method not allowed', 405);
    }
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'data' => $response
    ]);
    
} catch (Exception $e) {
    $statusCode = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
    
    $logger->error('Error in ads API: ' . $e->getMessage(), [
        'method' => $method ?? null,
        'data' => $data ?? null,
        'user_id' => $authService->getCurrentUserId() ?? null
    ]);
    
    http_response_code($statusCode);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
