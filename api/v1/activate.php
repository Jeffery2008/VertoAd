<?php

require_once __DIR__ . '/../../config/bootstrap.php';

use App\Services\KeyRedemptionService;
use App\Services\KeyGenerationService;
use App\Services\AccountService;
use App\Utils\Validator;

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON request body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate request data
$validator = new Validator();
$validation = $validator->validate($data, [
    'key' => 'required|string|min:29|max:29', // XXXXX-XXXXX-XXXXX-XXXXX-XXXXX format
    'csrf_token' => 'required|string'
]);

if (!$validation->isValid()) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request data', 'details' => $validation->getErrors()]);
    exit;
}

// Verify CSRF token
if (!$auth->verifyCsrfToken($data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Check if user is authenticated
if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get required services
    $keyRedemptionService = new KeyRedemptionService(
        $db,
        $logger,
        new KeyGenerationService($db, $logger),
        new AccountService($db, $logger)
    );
    
    // Clean and normalize the key
    $key = trim(strtoupper($data['key']));
    
    // Get client info
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Attempt to redeem the key
    $result = $keyRedemptionService->redeemKey(
        $key,
        $auth->getUserId(),
        $ipAddress,
        $userAgent
    );
    
    // Format success response
    $response = [
        'success' => true,
        'message' => 'Key activated successfully',
        'data' => [
            'amount_added' => $result['amount'],
            'new_balance' => $result['new_balance'],
            'currency' => 'USD' // Or get from config
        ]
    ];
    
    // Send success response
    http_response_code(200);
    echo json_encode($response);
    
} catch (\Exception $e) {
    // Log the error
    $logger->error('Key activation failed', [
        'user_id' => $auth->getUserId(),
        'error' => $e->getMessage(),
        'key' => substr($key ?? '', 0, 5) . '...' // Log only first segment for security
    ]);
    
    // Map common errors to user-friendly messages
    $errorMessages = [
        'Invalid key format' => 'The key format is invalid. Please check and try again.',
        'Invalid or unknown key' => 'This key is not valid. Please check and try again.',
        'Key has already been used or is revoked' => 'This key has already been used or is no longer valid.'
    ];
    
    $userMessage = $errorMessages[$e->getMessage()] ?? 'An error occurred while activating your key. Please try again later.';
    
    // Send error response
    http_response_code(400);
    echo json_encode([
        'error' => 'Activation failed',
        'message' => $userMessage
    ]);
}
