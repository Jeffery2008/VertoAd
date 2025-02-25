<?php

namespace App\Controllers;

use App\Utils\ErrorLogger;

/**
 * Controller for handling error management
 */
class ErrorController {
    private $errorLogger;
    
    public function __construct() {
        $this->errorLogger = ErrorLogger::getInstance();
    }
    
    /**
     * Get error logs with pagination and filtering
     */
    public function getErrorLogs($filters = [], $page = 1, $limit = 20) {
        return $this->errorLogger->getErrorLogs($filters, $page, $limit);
    }
    
    /**
     * Get error statistics for dashboard
     */
    public function getErrorStats() {
        return $this->errorLogger->getErrorStats();
    }
    
    /**
     * Update error status
     */
    public function updateErrorStatus($errorId, $status) {
        return $this->errorLogger->updateErrorStatus($errorId, $status);
    }
    
    /**
     * Display error dashboard page
     */
    public function showDashboard() {
        // Get filter values from request
        $filters = [
            'severity' => $_GET['severity'] ?? null,
            'status' => $_GET['status'] ?? null,
            'from_date' => $_GET['from_date'] ?? null,
            'to_date' => $_GET['to_date'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters);
        
        // Get page number
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        
        // Get error logs with pagination
        $errorLogs = $this->getErrorLogs($filters, $page);
        
        // Get error statistics
        $errorStats = $this->getErrorStats();
        
        // Load view with data
        include __DIR__ . '/../../templates/admin/error_dashboard.php';
    }
    
    /**
     * Handle error status updates via AJAX
     */
    public function handleStatusUpdate() {
        // Check if request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        // Check if required parameters are provided
        if (!isset($_POST['error_id']) || !isset($_POST['status'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            return;
        }
        
        $errorId = $_POST['error_id'];
        $status = $_POST['status'];
        
        // Update error status
        $result = $this->updateErrorStatus($errorId, $status);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update error status']);
        }
    }
    
    /**
     * API endpoint for error logs (JSON)
     */
    public function apiGetErrorLogs() {
        // Get filter values from request
        $filters = [
            'severity' => $_GET['severity'] ?? null,
            'status' => $_GET['status'] ?? null,
            'from_date' => $_GET['from_date'] ?? null,
            'to_date' => $_GET['to_date'] ?? null,
            'search' => $_GET['search'] ?? null
        ];
        
        // Remove empty filters
        $filters = array_filter($filters);
        
        // Get page number
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        
        // Get limit
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
        
        // Get error logs with pagination
        $errorLogs = $this->getErrorLogs($filters, $page, $limit);
        
        // Set content type to JSON
        header('Content-Type: application/json');
        
        // Output JSON response
        echo json_encode($errorLogs);
    }
    
    /**
     * API endpoint for error statistics (JSON)
     */
    public function apiGetErrorStats() {
        // Get error statistics
        $errorStats = $this->getErrorStats();
        
        // Set content type to JSON
        header('Content-Type: application/json');
        
        // Output JSON response
        echo json_encode($errorStats);
    }
} 