<?php
/**
 * Error Management API
 * Handles error log retrieval and management
 */

// Include necessary files
require_once __DIR__ . '/../../src/bootstrap.php';

use VertoAD\Core\Controllers\ErrorController;

// Create error controller instance
$errorController = new ErrorController();

// Check authentication and permissions
// Implement proper authentication checks here
// This endpoint should only be accessible to admin users

// Determine the requested action
$action = $_GET['action'] ?? 'logs';

// Handle different actions
switch ($action) {
    case 'logs':
        // Get error logs
        $errorController->apiGetErrorLogs();
        break;
        
    case 'stats':
        // Get error statistics
        $errorController->apiGetErrorStats();
        break;
        
    case 'update_status':
        // Update error status
        $errorController->handleStatusUpdate();
        break;
        
    default:
        // Invalid action
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
} 