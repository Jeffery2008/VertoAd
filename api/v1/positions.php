<?php

require_once __DIR__ . '/../../src/Services/AuthService.php';
require_once __DIR__ . '/../../src/Models/AdPosition.php';
require_once __DIR__ . '/../../src/Services/AdService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

header('Content-Type: application/json');

$auth = new AuthService();
$adService = new AdService();
$logger = new Logger();

try {
    // Require authentication for all ad position endpoints
    $token = $auth->validateRequest();
    if (!$token) {
        throw new Exception('Unauthorized access', 401);
    }

    // Handle request based on method
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($adService, $token);
            break;
        case 'POST':
            handlePostRequest($adService, $token);
            break;
        case 'PUT':
            handlePutRequest($adService, $token);
            break;
        case 'DELETE':
            handleDeleteRequest($adService, $token);
            break;
        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    // Log error
    $logger->error('Ad Position API error', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);

    // Return error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle GET requests (retrieve ad positions)
 */
function handleGetRequest($adService, $token) {
    // Get query parameters
    $action = $_GET['action'] ?? 'list';
    
    // Only admin can access all positions
    $userId = $token['role'] === 'admin' ? ($_GET['user_id'] ?? null) : $token['user_id'];

    // Handle different actions
    switch ($action) {
        case 'list':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
            $status = $_GET['status'] ?? null;
            
            $positions = $adService->getAdPositions($userId, $page, $limit, $status);
            
            echo json_encode([
                'success' => true,
                'data' => $positions
            ]);
            break;
            
        case 'details':
            if (!isset($_GET['id'])) {
                throw new Exception('Position ID is required', 400);
            }
            
            $position = $adService->getAdPosition($_GET['id']);
            
            echo json_encode([
                'success' => true,
                'data' => $position
            ]);
            break;
            
        case 'stats':
            if (!isset($_GET['id'])) {
                throw new Exception('Position ID is required', 400);
            }
            
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            $stats = $adService->getAdPositionStats(
                $_GET['id'],
                $startDate,
                $endDate
            );
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;
            
        case 'available':
            $date = $_GET['date'] ?? date('Y-m-d');
            $size = $_GET['size'] ?? null;
            $type = $_GET['type'] ?? null;
            
            $positions = $adService->getAvailablePositions(
                $date,
                $size,
                $type
            );
            
            echo json_encode([
                'success' => true,
                'data' => $positions
            ]);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle POST requests (create new ad positions)
 */
function handlePostRequest($adService, $token) {
    // Only admin can create positions
    if ($token['role'] !== 'admin') {
        throw new Exception('Permission denied', 403);
    }

    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data', 400);
    }

    // Validate required fields
    if (!isset($data['name'], $data['size'], $data['type'])) {
        throw new Exception('Required fields missing', 400);
    }

    // Create position
    $result = $adService->createAdPosition($data);

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}

/**
 * Handle PUT requests (update existing ad positions)
 */
function handlePutRequest($adService, $token) {
    // Only admin can update positions
    if ($token['role'] !== 'admin') {
        throw new Exception('Permission denied', 403);
    }

    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data', 400);
    }

    // Validate position ID
    if (!isset($data['id'])) {
        throw new Exception('Position ID is required', 400);
    }

    // Update position
    $result = $adService->updateAdPosition($data['id'], $data);

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}

/**
 * Handle DELETE requests (delete ad positions)
 */
function handleDeleteRequest($adService, $token) {
    // Only admin can delete positions
    if ($token['role'] !== 'admin') {
        throw new Exception('Permission denied', 403);
    }

    // Validate position ID
    if (!isset($_GET['id'])) {
        throw new Exception('Position ID is required', 400);
    }

    // Delete position
    $result = $adService->deleteAdPosition($_GET['id']);

    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}
