<?php
namespace VertoAD\Core\Utils;

use VertoAD\Core\Models\ErrorLog;
use VertoAD\Core\Config\Config;
use Throwable;

/**
 * ErrorLogger - Utility class for centralized error logging across the application
 */
class ErrorLogger {
    // Error types
    public const TYPE_PHP = 'php';
    public const TYPE_DATABASE = 'database';
    public const TYPE_APPLICATION = 'application';
    public const TYPE_VALIDATION = 'validation';
    public const TYPE_API = 'api';
    public const TYPE_JAVASCRIPT = 'javascript';
    public const TYPE_SECURITY = 'security';
    
    // Severity levels
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';
    
    private static $initialized = false;
    private static $config;
    
    /**
     * Initialize the error logger
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        self::$config = Config::get('error_logging');
        
        // Set up PHP error handlers
        set_error_handler([self::class, 'handlePhpError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
        
        self::$initialized = true;
    }
    
    /**
     * Handler for PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile File where error occurred
     * @param int $errline Line number where error occurred
     * @return bool
     */
    public static function handlePhpError($errno, $errstr, $errfile, $errline) {
        // Don't log errors if they're suppressed with @
        if (error_reporting() === 0) {
            return false;
        }
        
        $severity = self::SEVERITY_MEDIUM;
        
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $severity = self::SEVERITY_CRITICAL;
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $severity = self::SEVERITY_HIGH;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
            case E_STRICT:
                $severity = self::SEVERITY_LOW;
                break;
        }
        
        self::logPhpError($errstr, $severity, [
            'error_code' => $errno,
            'file' => $errfile,
            'line' => $errline
        ]);
        
        // Let PHP handle the error as well
        return false;
    }
    
    /**
     * Handler for exceptions
     * 
     * @param Throwable $exception The exception to handle
     */
    public static function handleException(Throwable $exception) {
        self::logException($exception);
    }
    
    /**
     * Handler for fatal errors
     */
    public static function handleFatalError() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::logPhpError($error['message'], self::SEVERITY_CRITICAL, [
                'error_code' => $error['type'],
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }
    
    /**
     * Log a PHP error
     * 
     * @param string $message Error message
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logPhpError($message, $severity = self::SEVERITY_MEDIUM, array $context = []) {
        $data = [
            'file' => $context['file'] ?? null,
            'line' => $context['line'] ?? null,
            'error_code' => $context['error_code'] ?? null,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        self::log(self::TYPE_PHP, $message, $severity, $data, $context);
    }
    
    /**
     * Log an exception
     * 
     * @param Throwable $exception The exception to log
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logException(Throwable $exception, $severity = self::SEVERITY_HIGH, array $context = []) {
        $data = [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'error_code' => $exception->getCode(),
            'stack_trace' => $exception->getTraceAsString()
        ];
        
        self::log(self::TYPE_PHP, $exception->getMessage(), $severity, $data, $context);
    }
    
    /**
     * Log a database error
     * 
     * @param string $message Error message
     * @param string $query SQL query that caused the error
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logDatabaseError($message, $query = null, $severity = self::SEVERITY_HIGH, array $context = []) {
        $data = [
            'query' => $query,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        self::log(self::TYPE_DATABASE, $message, $severity, $data, $context);
    }
    
    /**
     * Log an application error
     * 
     * @param string $message Error message
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logAppError($message, $severity = self::SEVERITY_MEDIUM, array $context = []) {
        $data = [
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        self::log(self::TYPE_APPLICATION, $message, $severity, $data, $context);
    }
    
    /**
     * Log a validation error
     * 
     * @param string $message Error message
     * @param array $validationErrors Validation errors array
     * @param array $context Additional context
     */
    public static function logValidationError($message, array $validationErrors = [], array $context = []) {
        $data = [
            'validation_errors' => $validationErrors
        ];
        
        self::log(self::TYPE_VALIDATION, $message, self::SEVERITY_LOW, $data, $context);
    }
    
    /**
     * Log an API error
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logApiError($message, $statusCode = 500, $endpoint = null, $method = null, $severity = self::SEVERITY_HIGH, array $context = []) {
        $data = [
            'status_code' => $statusCode,
            'endpoint' => $endpoint ?? $_SERVER['REQUEST_URI'] ?? null,
            'method' => $method ?? $_SERVER['REQUEST_METHOD'] ?? null,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        self::log(self::TYPE_API, $message, $severity, $data, $context);
    }
    
    /**
     * Log a JavaScript error
     * 
     * @param string $message Error message
     * @param string $url URL where the error occurred
     * @param int $line Line number
     * @param string $browser Browser information
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logJsError($message, $url = null, $line = null, $browser = null, $severity = self::SEVERITY_MEDIUM, array $context = []) {
        $data = [
            'url' => $url,
            'line' => $line,
            'browser' => $browser ?? $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        self::log(self::TYPE_JAVASCRIPT, $message, $severity, $data, $context);
    }
    
    /**
     * Log a security issue
     * 
     * @param string $message Error message
     * @param string $issueType Type of security issue (e.g., 'csrf', 'xss', 'sql_injection')
     * @param string $severity Error severity
     * @param array $context Additional context
     */
    public static function logSecurityIssue($message, $issueType = null, $severity = self::SEVERITY_CRITICAL, array $context = []) {
        $data = [
            'issue_type' => $issueType,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
        
        self::log(self::TYPE_SECURITY, $message, $severity, $data, $context);
    }
    
    /**
     * Main logging method
     * 
     * @param string $errorType Type of error
     * @param string $message Error message
     * @param string $severity Error severity
     * @param array $data Technical data about the error
     * @param array $context Additional context information
     */
    private static function log($errorType, $message, $severity, array $data = [], array $context = []) {
        try {
            // Get current user ID if available
            $userId = null;
            if (isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            
            // Get request data
            $request = [
                'uri' => $_SERVER['REQUEST_URI'] ?? null,
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'params' => $_REQUEST ?? [],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? null
            ];
            
            // Sanitize request data to remove sensitive information
            if (isset($request['params']['password'])) {
                $request['params']['password'] = '********';
            }
            
            // Add default context
            $fullContext = array_merge($context, [
                'request' => $request,
                'session_id' => session_id(),
                'environment' => getenv('APP_ENV') ?? 'production'
            ]);
            
            // Record the error in the database
            ErrorLog::createLog($errorType, $message, [
                'severity' => $severity,
                'error_code' => $data['error_code'] ?? null,
                'file' => $data['file'] ?? null,
                'line' => $data['line'] ?? null,
                'stack_trace' => is_array($data['stack_trace'] ?? null) ? json_encode($data['stack_trace']) : ($data['stack_trace'] ?? null),
                'user_id' => $userId,
                'session_id' => session_id(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'url' => $_SERVER['REQUEST_URI'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'additional_data' => json_encode(array_merge($data, $fullContext))
            ]);
        } catch (\Exception $e) {
            // If database logging fails, log to file as fallback
            self::logToFile($errorType, $message, $severity, $data, $context, $e->getMessage());
        }
    }
    
    /**
     * Fallback logging to file if database logging fails
     * 
     * @param string $errorType Type of error
     * @param string $message Error message
     * @param string $severity Error severity
     * @param array $data Technical data about the error
     * @param array $context Additional context information
     * @param string $loggingError Error that occurred during database logging
     */
    private static function logToFile($errorType, $message, $severity, array $data, array $context, $loggingError) {
        $logDir = dirname(__DIR__, 2) . '/logs';
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $errorType,
            'message' => $message,
            'severity' => $severity,
            'data' => $data,
            'context' => $context,
            'logging_error' => $loggingError
        ];
        
        file_put_contents(
            $logFile,
            json_encode($logEntry) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
} 