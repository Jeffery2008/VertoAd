<?php

require_once __DIR__ . '/../../src/Services/AccountService.php';
require_once __DIR__ . '/../../src/Services/AuthService.php';
require_once __DIR__ . '/../../src/Utils/Validator.php';

// Initialize services
$accountService = new AccountService();
$authService = new AuthService();
$validator = new Validator();

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));
$endpoint = end($pathParts);

// Authenticate request
try {
    $user = $authService->authenticate();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

// Route request to appropriate handler
try {
    switch ($method) {
        case 'GET':
            switch ($endpoint) {
                case 'balance':
                    // Get user balance
                    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $user['id'];
                    
                    // Check permissions for viewing other users' balances
                    if ($userId !== $user['id'] && !$authService->hasPermission($user['id'], 'view_user_balance')) {
                        throw new Exception('Permission denied');
                    }
                    
                    $balance = $accountService->getBalance($userId);
                    echo json_encode(['balance' => $balance]);
                    break;

                case 'transactions':
                    // Get transaction history
                    $userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : $user['id'];
                    
                    // Check permissions for viewing other users' transactions
                    if ($userId !== $user['id'] && !$authService->hasPermission($user['id'], 'view_user_transactions')) {
                        throw new Exception('Permission denied');
                    }
                    
                    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
                    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
                    
                    $transactions = $accountService->getTransactionHistory($userId, $limit, $offset);
                    echo json_encode(['transactions' => $transactions]);
                    break;

                default:
                    throw new Exception('Invalid endpoint');
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            
            switch ($endpoint) {
                case 'adjust':
                    // Admin balance adjustment
                    if (!$authService->hasPermission($user['id'], 'adjust_user_balance')) {
                        throw new Exception('Permission denied');
                    }
                    
                    // Validate request data
                    $validator->validate($data, [
                        'user_id' => 'required|integer',
                        'amount' => 'required|numeric',
                        'description' => 'required|string|max:500'
                    ]);
                    
                    $newBalance = $accountService->adjustBalance(
                        $data['user_id'],
                        floatval($data['amount']),
                        $data['description'],
                        $user['id']
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'new_balance' => $newBalance
                    ]);
                    break;

                case 'deposit':
                    // Process deposit
                    $validator->validate($data, [
                        'amount' => 'required|numeric|min:0.01',
                        'reference_id' => 'required|string|max:100'
                    ]);
                    
                    $newBalance = $accountService->processDeposit(
                        $user['id'],
                        floatval($data['amount']),
                        $data['reference_id']
                    );
                    
                    echo json_encode([
                        'success' => true,
                        'new_balance' => $newBalance
                    ]);
                    break;

                default:
                    throw new Exception('Invalid endpoint');
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }

} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Validation failed',
        'details' => $e->getErrors()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
