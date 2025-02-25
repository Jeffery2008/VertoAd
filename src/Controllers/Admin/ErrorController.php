<?php
namespace HFI\UtilityCenter\Controllers\Admin;

use HFI\UtilityCenter\Controllers\BaseController;
use HFI\UtilityCenter\Models\ErrorLog;
use HFI\UtilityCenter\Utils\ErrorLogger;
use HFI\UtilityCenter\Utils\Validator;
use PDO;

/**
 * ErrorController - Controller for managing error logs in the admin area
 */
class ErrorController extends BaseController {
    /**
     * Display the error dashboard
     */
    public function dashboard() {
        // Get error stats
        $errorLog = new ErrorLog($this->db);
        $errorTypeStats = $errorLog->getErrorTypeStats(30);
        $dailyErrorStats = $errorLog->getDailyErrorStats(30);
        $severityStats = $errorLog->getSeverityStats(30);
        
        // Get latest critical and high severity errors
        $criticalErrors = $errorLog->getLogs(['severity' => 'critical'], 5);
        $highSeverityErrors = $errorLog->getLogs(['severity' => 'high'], 5);
        
        // Get total unresolved errors
        $unresolvedCount = $errorLog->countLogs(['status' => 'open']);
        
        // Render the dashboard template
        $this->render('admin/error_dashboard', [
            'title' => 'Error Dashboard',
            'errorTypeStats' => $errorTypeStats,
            'dailyErrorStats' => $dailyErrorStats,
            'severityStats' => $severityStats,
            'criticalErrors' => $criticalErrors,
            'highSeverityErrors' => $highSeverityErrors,
            'unresolvedCount' => $unresolvedCount
        ]);
    }
    
    /**
     * List all error logs with filtering and pagination
     */
    public function index() {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        // Build filters from query parameters
        $filters = [];
        $validFilters = ['error_type', 'severity', 'status', 'user_id', 'ip_address', 'search'];
        
        foreach ($validFilters as $filter) {
            if (isset($_GET[$filter]) && $_GET[$filter] !== '') {
                $filters[$filter] = $_GET[$filter];
            }
        }
        
        // Date range filters
        if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
            $filters['date_from'] = $_GET['date_from'];
        }
        
        if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
            $filters['date_to'] = $_GET['date_to'];
        }
        
        // Get error logs with pagination
        $errorLog = new ErrorLog($this->db);
        $logs = $errorLog->getLogs($filters, $limit, $offset);
        $totalLogs = $errorLog->countLogs($filters);
        $totalPages = ceil($totalLogs / $limit);
        
        // Get available error types and other filter options for the form
        $errorTypes = $this->getErrorTypes();
        $severities = $this->getSeverityOptions();
        $statuses = $this->getStatusOptions();
        
        // Render the error logs template
        $this->render('admin/error_logs', [
            'title' => 'Error Logs',
            'logs' => $logs,
            'filters' => $filters,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
            'errorTypes' => $errorTypes,
            'severities' => $severities,
            'statuses' => $statuses,
            'urlParams' => $_GET
        ]);
    }
    
    /**
     * View details of a specific error log
     * 
     * @param int $id Error log ID
     */
    public function view($id) {
        $errorLog = new ErrorLog($this->db);
        $log = $errorLog->getLogById($id);
        
        if (!$log) {
            $_SESSION['error'] = 'Error log not found';
            $this->redirect('/admin/errors');
        }
        
        // Parse additional data if it's JSON
        if (!empty($log['additional_data'])) {
            try {
                $log['additional_data'] = json_decode($log['additional_data'], true);
            } catch (\Exception $e) {
                // If can't parse as JSON, keep as is
            }
        }
        
        // Parse stack trace if it's JSON
        if (!empty($log['stack_trace'])) {
            try {
                $log['stack_trace'] = json_decode($log['stack_trace'], true);
            } catch (\Exception $e) {
                // If can't parse as JSON, keep as is
            }
        }
        
        // Get related notifications
        $notifications = $this->getErrorNotifications($id);
        
        // Render the error detail template
        $this->render('admin/error_detail', [
            'title' => 'Error Log Detail',
            'log' => $log,
            'notifications' => $notifications
        ]);
    }
    
    /**
     * Mark an error log as resolved
     * 
     * @param int $id Error log ID
     */
    public function resolve($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            exit;
        }
        
        // Get notes from request
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        
        $errorLog = new ErrorLog($this->db);
        $userId = $_SESSION['user_id'] ?? null;
        
        try {
            $result = $errorLog->resolveLog($id, $userId, $notes);
            
            if ($result) {
                if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                    echo json_encode(['status' => 'success', 'message' => 'Error log marked as resolved']);
                    exit;
                }
                
                $_SESSION['success'] = 'Error log marked as resolved';
                $this->redirect('/admin/errors/view/' . $id);
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to resolve error log']);
                    exit;
                }
                
                $_SESSION['error'] = 'Failed to resolve error log';
                $this->redirect('/admin/errors/view/' . $id);
            }
        } catch (\Exception $e) {
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/admin/errors/view/' . $id);
        }
    }
    
    /**
     * Mark an error log as ignored
     * 
     * @param int $id Error log ID
     */
    public function ignore($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            exit;
        }
        
        // Get notes from request
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        
        $errorLog = new ErrorLog($this->db);
        $userId = $_SESSION['user_id'] ?? null;
        
        try {
            $result = $errorLog->ignoreLog($id, $userId, $notes);
            
            if ($result) {
                if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                    echo json_encode(['status' => 'success', 'message' => 'Error log marked as ignored']);
                    exit;
                }
                
                $_SESSION['success'] = 'Error log marked as ignored';
                $this->redirect('/admin/errors/view/' . $id);
            } else {
                if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to ignore error log']);
                    exit;
                }
                
                $_SESSION['error'] = 'Failed to ignore error log';
                $this->redirect('/admin/errors/view/' . $id);
            }
        } catch (\Exception $e) {
            if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                exit;
            }
            
            $_SESSION['error'] = $e->getMessage();
            $this->redirect('/admin/errors/view/' . $id);
        }
    }
    
    /**
     * Manage error categories
     */
    public function categories() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleCategoryAction();
        }
        
        // Get all categories
        $categories = $this->getErrorCategories();
        
        // Render the categories template
        $this->render('admin/error_categories', [
            'title' => 'Error Categories',
            'categories' => $categories
        ]);
    }
    
    /**
     * Manage notification subscriptions
     */
    public function subscriptions() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->handleSubscriptionAction();
        }
        
        // Get all subscriptions
        $subscriptions = $this->getErrorSubscriptions();
        
        // Get available users, error types, etc. for the form
        $errorTypes = $this->getErrorTypes();
        $users = $this->getActiveUsers();
        $severities = $this->getSeverityOptions();
        
        // Render the subscriptions template
        $this->render('admin/error_subscriptions', [
            'title' => 'Error Notification Subscriptions',
            'subscriptions' => $subscriptions,
            'errorTypes' => $errorTypes,
            'users' => $users,
            'severities' => $severities
        ]);
    }
    
    /**
     * Generate a test error (for testing the error tracking system)
     */
    public function generateTestError() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            exit;
        }
        
        // Validate input
        $errorType = isset($_POST['error_type']) ? $_POST['error_type'] : 'application';
        $severity = isset($_POST['severity']) ? $_POST['severity'] : 'medium';
        $message = isset($_POST['message']) ? $_POST['message'] : 'Test error message';
        
        $validErrorTypes = [
            ErrorLogger::TYPE_PHP,
            ErrorLogger::TYPE_DATABASE,
            ErrorLogger::TYPE_APPLICATION,
            ErrorLogger::TYPE_VALIDATION,
            ErrorLogger::TYPE_API,
            ErrorLogger::TYPE_JAVASCRIPT,
            ErrorLogger::TYPE_SECURITY
        ];
        
        $validSeverities = [
            ErrorLogger::SEVERITY_LOW,
            ErrorLogger::SEVERITY_MEDIUM,
            ErrorLogger::SEVERITY_HIGH,
            ErrorLogger::SEVERITY_CRITICAL
        ];
        
        if (!in_array($errorType, $validErrorTypes)) {
            $errorType = ErrorLogger::TYPE_APPLICATION;
        }
        
        if (!in_array($severity, $validSeverities)) {
            $severity = ErrorLogger::SEVERITY_MEDIUM;
        }
        
        // Generate the test error
        try {
            switch ($errorType) {
                case ErrorLogger::TYPE_PHP:
                    ErrorLogger::logPhpError($message, $severity, [
                        'file' => __FILE__,
                        'line' => __LINE__,
                        'error_code' => 0
                    ]);
                    break;
                case ErrorLogger::TYPE_DATABASE:
                    ErrorLogger::logDatabaseError($message, 'SELECT * FROM test_table', $severity);
                    break;
                case ErrorLogger::TYPE_APPLICATION:
                    ErrorLogger::logAppError($message, $severity);
                    break;
                case ErrorLogger::TYPE_VALIDATION:
                    ErrorLogger::logValidationError($message, ['field' => 'Test validation error']);
                    break;
                case ErrorLogger::TYPE_API:
                    ErrorLogger::logApiError($message, 400, '/api/test', 'GET', $severity);
                    break;
                case ErrorLogger::TYPE_JAVASCRIPT:
                    ErrorLogger::logJsError($message, 'http://example.com/test.js', 42, 'Test Browser', $severity);
                    break;
                case ErrorLogger::TYPE_SECURITY:
                    ErrorLogger::logSecurityIssue($message, 'test_issue', $severity);
                    break;
            }
            
            $_SESSION['success'] = 'Test error generated successfully';
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to generate test error: ' . $e->getMessage();
        }
        
        $this->redirect('/admin/errors');
    }
    
    /**
     * Handle actions for categories (add, edit, delete)
     */
    private function handleCategoryAction() {
        $action = isset($_POST['action']) ? $_POST['action'] : null;
        
        switch ($action) {
            case 'add':
                return $this->addCategory();
            case 'edit':
                return $this->editCategory();
            case 'delete':
                return $this->deleteCategory();
            default:
                $_SESSION['error'] = 'Invalid action';
                $this->redirect('/admin/errors/categories');
        }
    }
    
    /**
     * Add a new error category
     */
    private function addCategory() {
        // Validate input
        $validator = new Validator($_POST);
        $validator->required(['name', 'description', 'default_severity']);
        
        if (!$validator->validate()) {
            $_SESSION['error'] = 'Please fill in all required fields';
            $_SESSION['form_data'] = $_POST;
            $_SESSION['validation_errors'] = $validator->getErrors();
            $this->redirect('/admin/errors/categories');
        }
        
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $defaultSeverity = trim($_POST['default_severity']);
        $autoAssignPattern = isset($_POST['auto_assign_pattern']) ? trim($_POST['auto_assign_pattern']) : null;
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO error_categories (name, description, default_severity, auto_assign_pattern, created_at)
                VALUES (:name, :description, :default_severity, :auto_assign_pattern, NOW())
            ");
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':default_severity', $defaultSeverity);
            $stmt->bindParam(':auto_assign_pattern', $autoAssignPattern);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success'] = 'Category added successfully';
            } else {
                $_SESSION['error'] = 'Failed to add category';
                $_SESSION['form_data'] = $_POST;
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        
        $this->redirect('/admin/errors/categories');
    }
    
    /**
     * Edit an existing error category
     */
    private function editCategory() {
        // Validate input
        $validator = new Validator($_POST);
        $validator->required(['id', 'name', 'description', 'default_severity']);
        
        if (!$validator->validate()) {
            $_SESSION['error'] = 'Please fill in all required fields';
            $_SESSION['form_data'] = $_POST;
            $_SESSION['validation_errors'] = $validator->getErrors();
            $this->redirect('/admin/errors/categories');
        }
        
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $defaultSeverity = trim($_POST['default_severity']);
        $autoAssignPattern = isset($_POST['auto_assign_pattern']) ? trim($_POST['auto_assign_pattern']) : null;
        
        try {
            $stmt = $this->db->prepare("
                UPDATE error_categories
                SET name = :name, description = :description, default_severity = :default_severity, 
                    auto_assign_pattern = :auto_assign_pattern, updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':default_severity', $defaultSeverity);
            $stmt->bindParam(':auto_assign_pattern', $autoAssignPattern);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success'] = 'Category updated successfully';
            } else {
                $_SESSION['error'] = 'Failed to update category';
                $_SESSION['form_data'] = $_POST;
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        
        $this->redirect('/admin/errors/categories');
    }
    
    /**
     * Delete an error category
     */
    private function deleteCategory() {
        // Validate input
        $validator = new Validator($_POST);
        $validator->required(['id']);
        
        if (!$validator->validate()) {
            $_SESSION['error'] = 'Invalid category ID';
            $this->redirect('/admin/errors/categories');
        }
        
        $id = intval($_POST['id']);
        
        try {
            $stmt = $this->db->prepare("DELETE FROM error_categories WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success'] = 'Category deleted successfully';
            } else {
                $_SESSION['error'] = 'Failed to delete category';
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        
        $this->redirect('/admin/errors/categories');
    }
    
    /**
     * Handle actions for subscriptions (add, edit, delete)
     */
    private function handleSubscriptionAction() {
        $action = isset($_POST['action']) ? $_POST['action'] : null;
        
        switch ($action) {
            case 'add':
                return $this->addSubscription();
            case 'edit':
                return $this->editSubscription();
            case 'delete':
                return $this->deleteSubscription();
            default:
                $_SESSION['error'] = 'Invalid action';
                $this->redirect('/admin/errors/subscriptions');
        }
    }
    
    /**
     * Add a new notification subscription
     */
    private function addSubscription() {
        // Validate input
        $validator = new Validator($_POST);
        $validator->required(['user_id', 'method', 'min_severity_level']);
        
        if (!$validator->validate()) {
            $_SESSION['error'] = 'Please fill in all required fields';
            $_SESSION['form_data'] = $_POST;
            $_SESSION['validation_errors'] = $validator->getErrors();
            $this->redirect('/admin/errors/subscriptions');
        }
        
        $userId = intval($_POST['user_id']);
        $method = trim($_POST['method']);
        $minSeverityLevel = intval($_POST['min_severity_level']);
        $errorTypes = isset($_POST['error_types']) && is_array($_POST['error_types']) 
            ? implode(',', $_POST['error_types']) 
            : null;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO error_notification_subscriptions 
                (user_id, method, min_severity_level, error_types, is_active, created_at)
                VALUES (:user_id, :method, :min_severity_level, :error_types, :is_active, NOW())
            ");
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':method', $method);
            $stmt->bindParam(':min_severity_level', $minSeverityLevel, PDO::PARAM_INT);
            $stmt->bindParam(':error_types', $errorTypes);
            $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success'] = 'Subscription added successfully';
            } else {
                $_SESSION['error'] = 'Failed to add subscription';
                $_SESSION['form_data'] = $_POST;
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        
        $this->redirect('/admin/errors/subscriptions');
    }
    
    /**
     * Edit an existing notification subscription
     */
    private function editSubscription() {
        // Validate input
        $validator = new Validator($_POST);
        $validator->required(['id', 'user_id', 'method', 'min_severity_level']);
        
        if (!$validator->validate()) {
            $_SESSION['error'] = 'Please fill in all required fields';
            $_SESSION['form_data'] = $_POST;
            $_SESSION['validation_errors'] = $validator->getErrors();
            $this->redirect('/admin/errors/subscriptions');
        }
        
        $id = intval($_POST['id']);
        $userId = intval($_POST['user_id']);
        $method = trim($_POST['method']);
        $minSeverityLevel = intval($_POST['min_severity_level']);
        $errorTypes = isset($_POST['error_types']) && is_array($_POST['error_types']) 
            ? implode(',', $_POST['error_types']) 
            : null;
        $isActive = isset($_POST['is_active']) && $_POST['is_active'] === '1' ? 1 : 0;
        
        try {
            $stmt = $this->db->prepare("
                UPDATE error_notification_subscriptions
                SET user_id = :user_id, method = :method, min_severity_level = :min_severity_level,
                    error_types = :error_types, is_active = :is_active, updated_at = NOW()
                WHERE id = :id
            ");
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':method', $method);
            $stmt->bindParam(':min_severity_level', $minSeverityLevel, PDO::PARAM_INT);
            $stmt->bindParam(':error_types', $errorTypes);
            $stmt->bindParam(':is_active', $isActive, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success'] = 'Subscription updated successfully';
            } else {
                $_SESSION['error'] = 'Failed to update subscription';
                $_SESSION['form_data'] = $_POST;
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
        
        $this->redirect('/admin/errors/subscriptions');
    }
    
    /**
     * Delete a notification subscription
     */
    private function deleteSubscription() {
        // Validate input
        $validator = new Validator($_POST);
        $validator->required(['id']);
        
        if (!$validator->validate()) {
            $_SESSION['error'] = 'Invalid subscription ID';
            $this->redirect('/admin/errors/subscriptions');
        }
        
        $id = intval($_POST['id']);
        
        try {
            $stmt = $this->db->prepare("DELETE FROM error_notification_subscriptions WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            if ($result) {
                $_SESSION['success'] = 'Subscription deleted successfully';
            } else {
                $_SESSION['error'] = 'Failed to delete subscription';
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Database error: ' . $e->getMessage();
        }
        
        $this->redirect('/admin/errors/subscriptions');
    }
    
    /**
     * Get error notifications for a specific log
     * 
     * @param int $errorLogId Error log ID
     * @return array Notifications
     */
    private function getErrorNotifications($errorLogId) {
        $stmt = $this->db->prepare("
            SELECT n.*, u.name as user_name, u.email
            FROM error_notifications n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE n.error_log_id = :error_log_id
            ORDER BY n.created_at DESC
        ");
        
        $stmt->bindParam(':error_log_id', $errorLogId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get error categories
     * 
     * @return array Categories
     */
    private function getErrorCategories() {
        $stmt = $this->db->query("
            SELECT *
            FROM error_categories
            ORDER BY name ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get error subscriptions
     * 
     * @return array Subscriptions
     */
    private function getErrorSubscriptions() {
        $stmt = $this->db->query("
            SELECT s.*, u.name as user_name, u.email
            FROM error_notification_subscriptions s
            JOIN users u ON s.user_id = u.id
            ORDER BY u.name ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get active users
     * 
     * @return array Users
     */
    private function getActiveUsers() {
        $stmt = $this->db->query("
            SELECT id, name, email
            FROM users
            WHERE status = 'active'
            ORDER BY name ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get error types
     * 
     * @return array Error types
     */
    private function getErrorTypes() {
        return [
            ErrorLogger::TYPE_PHP => 'PHP Errors',
            ErrorLogger::TYPE_DATABASE => 'Database Errors',
            ErrorLogger::TYPE_APPLICATION => 'Application Errors',
            ErrorLogger::TYPE_VALIDATION => 'Validation Errors',
            ErrorLogger::TYPE_API => 'API Errors',
            ErrorLogger::TYPE_JAVASCRIPT => 'JavaScript Errors',
            ErrorLogger::TYPE_SECURITY => 'Security Issues'
        ];
    }
    
    /**
     * Get severity options
     * 
     * @return array Severity options
     */
    private function getSeverityOptions() {
        return [
            ErrorLogger::SEVERITY_LOW => 'Low',
            ErrorLogger::SEVERITY_MEDIUM => 'Medium',
            ErrorLogger::SEVERITY_HIGH => 'High',
            ErrorLogger::SEVERITY_CRITICAL => 'Critical'
        ];
    }
    
    /**
     * Get status options
     * 
     * @return array Status options
     */
    private function getStatusOptions() {
        return [
            'open' => 'Open',
            'resolved' => 'Resolved',
            'ignored' => 'Ignored'
        ];
    }
} 