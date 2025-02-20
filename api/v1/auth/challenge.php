<?php
require_once '../../../src/Services/AuthService.php';
require_once '../../../src/Utils/Logger.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['username'])) {
        throw new Exception('Username is required', 400);
    }

    $authService = AuthService::getInstance();
    $challenge = $authService->generateLoginChallenge($data['username']);

    echo json_encode([
        'status' => 'success',
        'data' => $challenge
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
