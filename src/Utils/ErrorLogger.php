<?php

namespace App\Utils;

use PDO;
use PDOException;

/**
 * ErrorLogger class for capturing and storing PHP errors in the database
 * for monitoring and debugging purposes
 */
class ErrorLogger {
    private static $instance = null;
    private $db;
    
    /**
     * Private constructor to enforce singleton pattern
     */
    private function __construct() {
        $this->db = Database::getConnection();
        $this->ensureErrorTableExists();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance(): ErrorLogger {
        if (self::$instance === null) {
            self::$instance = new ErrorLogger();
        }
        return self::$instance;
    }
    
    /**
     * Make sure error_logs table exists, create if not
     */
    private function ensureErrorTableExists() {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS error_logs (
                    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
                    error_type VARCHAR(50) NOT NULL,
                    error_message TEXT NOT NULL,
                    error_file VARCHAR(255) NOT NULL,
                    error_line INT NOT NULL,
                    error_trace TEXT,
                    request_uri VARCHAR(255),
                    request_method VARCHAR(10),
                    client_ip VARCHAR(45),
                    user_agent TEXT,
                    user_id BIGINT UNSIGNED NULL,
                    session_id VARCHAR(64),
                    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
                    status ENUM('new', 'in_progress', 'resolved', 'ignored') NOT NULL DEFAULT 'new',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_error_status (status),
                    INDEX idx_severity (severity),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
        } catch (PDOException $e) {
            // Avoid infinite loop by not logging errors from this method
            error_log("Failed to create error_logs table: " . $e->getMessage());
        }
    }
    
    /**
     * Log an error to the database
     */
    public function logError($type, $message, $file, $line, $trace = null, $severity = 'medium') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO error_logs (
                    error_type, error_message, error_file, error_line, error_trace,
                    request_uri, request_method, client_ip, user_agent,
                    user_id, session_id, severity
                ) VALUES (
                    :type, :message, :file, :line, :trace,
                    :request_uri, :request_method, :client_ip, :user_agent,
                    :user_id, :session_id, :severity
                )
            ");
            
            // Get current session ID if available
            $sessionId = session_id() ?: null;
            
            // Get current user ID if available (assuming user info is stored in session)
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':message', $message);
            $stmt->bindParam(':file', $file);
            $stmt->bindParam(':line', $line);
            $stmt->bindParam(':trace', $trace);
            $stmt->bindParam(':request_uri', $_SERVER['REQUEST_URI'] ?? null);
            $stmt->bindParam(':request_method', $_SERVER['REQUEST_METHOD'] ?? null);
            $stmt->bindParam(':client_ip', $_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':session_id', $sessionId);
            $stmt->bindParam(':severity', $severity);
            
            $stmt->execute();
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            // Use PHP's native error logging as fallback
            error_log("Failed to log error to database: " . $e->getMessage());
            error_log("Original error: " . $message);
            return false;
        }
    }
    
    /**
     * Custom error handler to capture PHP errors
     */
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $errorTypes = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        ];
        
        $type = isset($errorTypes[$errno]) ? $errorTypes[$errno] : 'Unknown Error';
        
        // Determine severity based on error type
        $severity = 'medium';
        if ($errno == E_ERROR || $errno == E_CORE_ERROR || $errno == E_COMPILE_ERROR || $errno == E_USER_ERROR) {
            $severity = 'critical';
        } elseif ($errno == E_WARNING || $errno == E_CORE_WARNING || $errno == E_COMPILE_WARNING || $errno == E_USER_WARNING) {
            $severity = 'high';
        } elseif ($errno == E_NOTICE || $errno == E_USER_NOTICE || $errno == E_STRICT || $errno == E_DEPRECATED || $errno == E_USER_DEPRECATED) {
            $severity = 'low';
        }
        
        // Get stack trace
        $trace = debug_backtrace();
        $traceString = '';
        foreach ($trace as $i => $t) {
            if ($i == 0) continue; // Skip the current function
            $traceString .= "#$i " . (isset($t['file']) ? $t['file'] : '<unknown file>');
            $traceString .= "(" . (isset($t['line']) ? $t['line'] : '<unknown line>') . "): ";
            $traceString .= (isset($t['class']) ? $t['class'] . $t['type'] : '');
            $traceString .= $t['function'] . "()\n";
        }
        
        // Log the error
        self::getInstance()->logError($type, $errstr, $errfile, $errline, $traceString, $severity);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Custom exception handler
     */
    public static function handleException($exception) {
        $severity = 'high';
        if ($exception instanceof \ErrorException) {
            $severity = 'critical';
        }
        
        // Log the exception
        self::getInstance()->logError(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
            $severity
        );
    }
    
    /**
     * Register error handlers
     */
    public static function register() {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        
        // Register shutdown function to catch fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                self::handleError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            }
        });
    }
    
    /**
     * Get error logs with pagination and filtering options
     */
    public function getErrorLogs($filters = [], $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        // Base query
        $sql = "SELECT * FROM error_logs WHERE 1=1";
        $countSql = "SELECT COUNT(*) FROM error_logs WHERE 1=1";
        
        $params = [];
        
        // Apply filters
        if (!empty($filters['severity'])) {
            $sql .= " AND severity = :severity";
            $countSql .= " AND severity = :severity";
            $params[':severity'] = $filters['severity'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $countSql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['from_date'])) {
            $sql .= " AND created_at >= :from_date";
            $countSql .= " AND created_at >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        
        if (!empty($filters['to_date'])) {
            $sql .= " AND created_at <= :to_date";
            $countSql .= " AND created_at <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }
        
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $sql .= " AND (error_message LIKE :search OR error_file LIKE :search)";
            $countSql .= " AND (error_message LIKE :search OR error_file LIKE :search)";
            $params[':search'] = $searchTerm;
        }
        
        // Add order and limit
        $sql .= " ORDER BY created_at DESC LIMIT :offset, :limit";
        
        // Execute count query
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalCount = $countStmt->fetchColumn();
        
        // Execute main query
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'data' => $errors,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * Update error status
     */
    public function updateErrorStatus($errorId, $status) {
        $validStatuses = ['new', 'in_progress', 'resolved', 'ignored'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                UPDATE error_logs
                SET status = :status
                WHERE id = :id
            ");
            
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $errorId);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Get error statistics for dashboard
     */
    public function getErrorStats() {
        $stats = [];
        
        // Count by severity
        $severityStmt = $this->db->query("
            SELECT severity, COUNT(*) as count
            FROM error_logs
            GROUP BY severity
        ");
        $stats['by_severity'] = $severityStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Count by status
        $statusStmt = $this->db->query("
            SELECT status, COUNT(*) as count
            FROM error_logs
            GROUP BY status
        ");
        $stats['by_status'] = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Count by date (last 30 days)
        $dateStmt = $this->db->query("
            SELECT DATE(created_at) as date, COUNT(*) as count
            FROM error_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stats['by_date'] = $dateStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Most common errors
        $commonStmt = $this->db->query("
            SELECT error_message, COUNT(*) as count
            FROM error_logs
            GROUP BY error_message
            ORDER BY count DESC
            LIMIT 10
        ");
        $stats['most_common'] = $commonStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return $stats;
    }
} 