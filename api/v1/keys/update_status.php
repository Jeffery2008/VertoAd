<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../verify_admin.php'; // Admin authentication

// Get request parameters
$batchId = $_POST['batchId'] ?? ''; // Using camelCase for parameter name
$newStatus = $_POST['status'] ?? ''; // Using camelCase for parameter name

$response = ['success' => false, 'message' => '', 'error' => ''];

// Validate request parameters
if (empty($batchId) || empty($newStatus)) {
    $response['error'] = 'Missing batchId or status parameters.'; // Using camelCase
    echo json_encode($response);
    exit;
}

$allowedStatuses = ['unused', 'used', 'revoked']; // Define allowed statuses
if (!in_array($newStatus, $allowedStatuses)) {
    $response['error'] = 'Invalid status value.';
    echo json_encode($response);
    exit;
}

// Instantiate KeyGenerationService
require_once __DIR__ . '/../../src/Services/KeyGenerationService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php'; // Assuming Logger is needed
$logger = new \VertoAD\Core\Utils\Logger(); // Instantiate Logger
$keyGenerationService = new \VertoAD\Core\Services\KeyGenerationService($logger, $db); // Assuming $db is available from verify_admin.php

// Get admin user ID (assuming verify_admin.php sets it in session)
$adminUserId = $_SESSION['admin_user_id'] ?? 0; 

// Call KeyGenerationService::updateKeyBatchStatus
$success = $keyGenerationService->updateKeyBatchStatus(
    intval($batchId), 
    $newStatus, 
    $adminUserId
);

if ($success) {
    $response['success'] = true;
    $response['message'] = 'Key batch status updated successfully.'; // Using camelCase
} else {
    $response['error'] = 'Failed to update key batch status.'; // Using camelCase
}

echo json_encode($response);
