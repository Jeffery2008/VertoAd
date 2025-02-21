<?php

require_once __DIR__ . '/../../src/Services/AuthService.php';
require_once __DIR__ . '/../../src/Models/User.php';
require_once __DIR__ . '/../../src/Services/AccountService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

header('Content-Type: application/json');

$auth = new AuthService();
$accountService = new AccountService();
$logger = new Logger();

try {
    // Require authentication for all account endpoints
    $token = $auth->validateRequest();
    if (!$token) {
        throw new Exception('Unauthorized access', 401);
    }

    // Handle request based on method
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($accountService, $token);
            break;
        case 'POST':
            handlePostRequest($accountService, $token);
            break;
        case 'PUT':
            handlePutRequest($accountService, $token);
            break;
        default:
            throw new Exception('Method not allowed', 405);
    }
} catch (Exception $e) {
    // Log error
    $logger->error('Account API error', [
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
 * Handle GET requests (retrieve account information)
 */
function handleGetRequest($accountService, $token) {
    // Get query parameters
    $action = $_GET['action'] ?? 'profile';
    $userId = $token['user_id'];
    
    // Allow admin to view other accounts
    if (isset($_GET['user_id']) && $token['role'] === 'admin') {
        $userId = $_GET['user_id'];
    }

    // Handle different actions
    switch ($action) {
        case 'profile':
            $profile = $accountService->getUserProfile($userId);
            echo json_encode([
                'success' => true,
                'data' => $profile
            ]);
            break;
            
        case 'balance':
            $balance = $accountService->getAccountBalance($userId);
            echo json_encode([
                'success' => true,
                'data' => $balance
            ]);
            break;
            
        case 'transactions':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            $type = $_GET['type'] ?? null;
            
            $transactions = $accountService->getTransactionHistory(
                $userId, 
                $page, 
                $limit, 
                $startDate, 
                $endDate, 
                $type
            );
            
            echo json_encode([
                'success' => true,
                'data' => $transactions
            ]);
            break;
            
        case 'orders':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
            $status = $_GET['status'] ?? null;
            
            $orders = $accountService->getOrders($userId, $page, $limit, $status);
            
            echo json_encode([
                'success' => true,
                'data' => $orders
            ]);
            break;
            
        case 'invoices':
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
            $status = $_GET['status'] ?? null;
            
            $invoices = $accountService->getInvoices($userId, $page, $limit, $status);
            
            echo json_encode([
                'success' => true,
                'data' => $invoices
            ]);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle POST requests (create new resources)
 */
function handlePostRequest($accountService, $token) {
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data', 400);
    }
    
    // Get query parameters
    $action = $_GET['action'] ?? '';
    $userId = $token['user_id'];
    
    // Handle different actions
    switch ($action) {
        case 'deposit':
            // Validate required fields
            if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
                throw new Exception('Valid amount is required', 400);
            }
            
            // Process deposit
            $paymentMethod = $data['payment_method'] ?? 'credit_card';
            $result = $accountService->processDeposit($userId, $data['amount'], $paymentMethod, $data);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'withdraw':
            // Validate required fields
            if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
                throw new Exception('Valid amount is required', 400);
            }
            
            if (!isset($data['bank_info']) || !is_array($data['bank_info'])) {
                throw new Exception('Bank information is required', 400);
            }
            
            // Process withdrawal
            $result = $accountService->processWithdrawal($userId, $data['amount'], $data['bank_info']);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'order':
            // Validate required fields
            if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
                throw new Exception('Order items are required', 400);
            }
            
            // Create order
            $result = $accountService->createOrder($userId, $data['items'], $data);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'invoice_request':
            // Validate required fields
            if (!isset($data['order_id'])) {
                throw new Exception('Order ID is required', 400);
            }
            
            if (!isset($data['billing_info']) || !is_array($data['billing_info'])) {
                throw new Exception('Billing information is required', 400);
            }
            
            // Request invoice
            $result = $accountService->requestInvoice($userId, $data['order_id'], $data['billing_info']);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}

/**
 * Handle PUT requests (update existing resources)
 */
function handlePutRequest($accountService, $token) {
    // Get request body
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data', 400);
    }
    
    // Get query parameters
    $action = $_GET['action'] ?? '';
    $userId = $token['user_id'];
    
    // Allow admin to update other accounts
    if (isset($data['user_id']) && $token['role'] === 'admin') {
        $userId = $data['user_id'];
    }
    
    // Handle different actions
    switch ($action) {
        case 'profile':
            $result = $accountService->updateUserProfile($userId, $data);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        case 'password':
            // Validate required fields
            if (!isset($data['current_password'])) {
                throw new Exception('Current password is required', 400);
            }
            
            if (!isset($data['new_password'])) {
                throw new Exception('New password is required', 400);
            }
            
            // Update password
            $result = $accountService->updatePassword(
                $userId, 
                $data['current_password'], 
                $data['new_password']
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Password updated successfully'
            ]);
            break;
            
        case 'cancel_order':
            // Validate required fields
            if (!isset($data['order_id'])) {
                throw new Exception('Order ID is required', 400);
            }
            
            // Cancel order
            $result = $accountService->cancelOrder($userId, $data['order_id'], $data['reason'] ?? null);
            
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
}
