<?php
require_once '../../../src/Services/AuthService.php';
require_once '../../../src/Utils/Logger.php';
require_once '../../../src/Models/User.php';

header('Content-Type: application/json');

try {
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['username', 'password', 'nonce', 'solution'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: {$field}", 400);
        }
    }

    $authService = AuthService::getInstance();
    $logger = new Logger();

    // Validate PoW solution
    if (!$authService->validatePoW($data['username'], $data['nonce'], $data['solution'])) {
        $logger->warning('Invalid PoW solution attempt', [
            'username' => $data['username'],
            'nonce' => $data['nonce'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        throw new Exception('Invalid proof of work solution', 403);
    }

    // Validate CSRF token
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$authService->validateCsrfToken($csrfToken)) {
        $logger->warning('Invalid CSRF token', [
            'username' => $data['username'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        throw new Exception('Invalid request token', 403);
    }

    // Find and validate user
    $user = User::findByUsername($data['username']);
    if (!$user || !$authService->verifyPassword($data['password'], $user->password)) {
        $logger->warning('Failed login attempt', [
            'username' => $data['username'],
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        throw new Exception('Invalid username or password', 401);
    }

    // Generate session token
    $token = $authService->generateToken($user->id, $user->type);
    
    // Log successful login
    $logger->info('Successful login', [
        'user_id' => $user->id,
        'username' => $user->username,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);

    // Set token in cookie with secure flags
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('auth_token', $token, [
        'expires' => time() + (defined('TOKEN_EXPIRY') ? TOKEN_EXPIRY : 86400),
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    echo json_encode([
        'status' => 'success',
        'data' => [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'type' => $user->type
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
