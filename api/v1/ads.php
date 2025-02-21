<?php

require_once __DIR__ . '/../../src/Models/Advertisement.php';
require_once __DIR__ . '/../../src/Models/AdPosition.php';
require_once __DIR__ . '/../../src/Services/AdService.php';
require_once __DIR__ . '/../../src/Services/AuthService.php';
require_once __DIR__ . '/../../src/Utils/Validator.php';

header('Content-Type: application/json');

$auth = new AuthService();
$adService = new AdService();

// API endpoint handlers
try {
    // Validate auth token
    $token = $auth->validateRequest();
    if (!$token) {
        throw new Exception('Unauthorized', 401);
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
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle GET request for fetching ads or specific ad details
 */
function handleGetRequest($adService, $token) {
    // Get query parameters
    $id = $_GET['id'] ?? null;
    $advertiser_id = $_GET['advertiser_id'] ?? null;
    $position_id = $_GET['position_id'] ?? null;
    $status = $_GET['status'] ?? null;
    $page = intval($_GET['page'] ?? 1);
    $limit = min(intval($_GET['limit'] ?? 20), 100);

    // Get ad details if ID is provided
    if ($id) {
        $ad = $adService->getById($id);
        if (!$ad) {
            throw new Exception('Advertisement not found', 404);
        }
        // Check permission
        if ($ad['advertiser_id'] != $token['user_id'] && $token['role'] != 'admin') {
            throw new Exception('Unauthorized', 403);
        }
        echo json_encode($ad);
        return;
    }

    // Build filter conditions
    $filters = [];
    if ($advertiser_id) {
        // Only admin can view other advertisers' ads
        if ($advertiser_id != $token['user_id'] && $token['role'] != 'admin') {
            throw new Exception('Unauthorized', 403);
        }
        $filters['advertiser_id'] = $advertiser_id;
    } elseif ($token['role'] == 'advertiser') {
        // Advertisers can only view their own ads
        $filters['advertiser_id'] = $token['user_id'];
    }

    if ($position_id) {
        $filters['position_id'] = $position_id;
    }
    if ($status) {
        $filters['status'] = $status;
    }

    // Get paginated results
    $result = $adService->search($filters, $page, $limit);
    echo json_encode($result);
}

/**
 * Handle POST request for creating new ad
 */
function handlePostRequest($adService, $token) {
    // Validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid input', 400);
    }

    // Validate advertiser
    if ($token['role'] == 'advertiser') {
        $input['advertiser_id'] = $token['user_id'];
    } elseif (!isset($input['advertiser_id'])) {
        throw new Exception('Advertiser ID required', 400);
    }

    // Required fields
    $required = ['position_id', 'title', 'content', 'start_date', 'end_date', 'total_budget'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: {$field}", 400);
        }
    }

    // Create advertisement
    $ad = $adService->create($input);
    
    http_response_code(201);
    echo json_encode($ad);
}

/**
 * Handle PUT request for updating ad
 */
function handlePutRequest($adService, $token) {
    // Get ad ID from URL
    if (!isset($_GET['id'])) {
        throw new Exception('Advertisement ID required', 400);
    }
    $id = $_GET['id'];

    // Get existing ad
    $ad = $adService->getById($id);
    if (!$ad) {
        throw new Exception('Advertisement not found', 404);
    }

    // Check permission
    if ($ad['advertiser_id'] != $token['user_id'] && $token['role'] != 'admin') {
        throw new Exception('Unauthorized', 403);
    }

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid input', 400);
    }

    // Don't allow changing advertiser
    unset($input['advertiser_id']);

    // Update ad
    $updated = $adService->update($id, $input);
    echo json_encode($updated);
}

/**
 * Handle DELETE request for ad
 */
function handleDeleteRequest($adService, $token) {
    // Get ad ID from URL
    if (!isset($_GET['id'])) {
        throw new Exception('Advertisement ID required', 400);
    }
    $id = $_GET['id'];

    // Get existing ad
    $ad = $adService->getById($id);
    if (!$ad) {
        throw new Exception('Advertisement not found', 404);
    }

    // Check permission
    if ($ad['advertiser_id'] != $token['user_id'] && $token['role'] != 'admin') {
        throw new Exception('Unauthorized', 403);
    }

    // Delete ad
    $adService->delete($id);
    http_response_code(204);
}
