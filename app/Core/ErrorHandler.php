<?php

namespace App\Core;

use Exception;
use ErrorException;
use Throwable;

class ErrorHandler
{
    private $db;
    private static $instance;
    
    /**
     * 错误类型映射
     */
    private $errorTypes = [
        E_ERROR             => 'Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Strict Standards',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated',
        E_USER_DEPRECATED   => 'User Deprecated',
        E_ALL               => 'All Errors'
    ];
    
    /**
     * 构造函数 - 单例模式
     */
    private function __construct()
    {
        $this->db = new Database();
        $this->ensureErrorTable();
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 确保错误表存在
     */
    private function ensureErrorTable()
    {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS errors (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    type VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    file VARCHAR(255) NOT NULL,
                    line INT NOT NULL,
                    trace TEXT,
                    request_data TEXT,
                    user_id INT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('new', 'in_progress', 'resolved', 'ignored') DEFAULT 'new',
                    notes TEXT,
                    INDEX (type),
                    INDEX (status),
                    INDEX (created_at)
                )
            ");
        } catch (Exception $e) {
            // 如果创建错误表时发生错误，记录到系统日志
            error_log('Failed to create errors table: ' . $e->getMessage());
        }
    }
    
    /**
     * 注册错误处理器
     */
    public function register()
    {
        // 设置错误处理函数
        set_error_handler([$this, 'handleError']);
        
        // 设置异常处理函数
        set_exception_handler([$this, 'handleException']);
        
        // 注册shutdown函数捕获致命错误
        register_shutdown_function([$this, 'handleShutdown']);
        
        return $this;
    }
    
    /**
     * 处理PHP错误
     */
    public function handleError($level, $message, $file, $line)
    {
        // 检查错误是否被当前错误报告级别抑制
        if (!(error_reporting() & $level)) {
            return false;
        }
        
        // 将错误转换为异常
        throw new ErrorException($message, 0, $level, $file, $line);
    }
    
    /**
     * 处理异常
     */
    public function handleException(Throwable $exception)
    {
        $this->logError(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // 渲染错误页面或返回适当的错误响应
        $this->renderErrorPage($exception);
    }
    
    /**
     * 处理致命错误
     */
    public function handleShutdown()
    {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $this->logError(
                $this->getErrorType($error['type']),
                $error['message'],
                $error['file'],
                $error['line']
            );
            
            // 渲染致命错误页面
            $this->renderFatalErrorPage($error);
        }
    }
    
    /**
     * 获取错误类型名称
     */
    private function getErrorType($type)
    {
        return isset($this->errorTypes[$type]) ? $this->errorTypes[$type] : 'Unknown Error';
    }
    
    /**
     * 记录错误到数据库
     */
    public function logError($type, $message, $file, $line, $trace = null)
    {
        try {
            // 收集请求数据
            $requestData = [
                'GET' => $_GET,
                'POST' => $_POST,
                'COOKIE' => $_COOKIE,
                'SESSION' => isset($_SESSION) ? $_SESSION : [],
                'SERVER' => array_intersect_key($_SERVER, array_flip([
                    'REQUEST_URI', 'HTTP_REFERER', 'REQUEST_METHOD'
                ]))
            ];
            
            // 获取当前用户ID
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            // 获取IP地址
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            
            // 获取用户代理
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // 将错误记录到数据库
            $this->db->query(
                "INSERT INTO errors (type, message, file, line, trace, request_data, user_id, ip_address, user_agent) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $type,
                    $message,
                    $file,
                    $line,
                    $trace,
                    json_encode($requestData),
                    $userId,
                    $ipAddress,
                    $userAgent
                ]
            );
            
            // 也记录到系统日志
            error_log("[$type] $message in $file on line $line");
            
        } catch (Exception $e) {
            // 记录到系统日志
            error_log('Failed to log error to database: ' . $e->getMessage());
            error_log("Original error: [$type] $message in $file on line $line");
        }
    }
    
    /**
     * 手动记录异常
     */
    public static function logException(Throwable $exception, $additionalData = [])
    {
        $instance = self::getInstance();
        
        $trace = $exception->getTraceAsString();
        if (!empty($additionalData)) {
            $trace .= "\n\nAdditional Data: " . json_encode($additionalData);
        }
        
        $instance->logError(
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $trace
        );
    }
    
    /**
     * 渲染错误页面
     */
    private function renderErrorPage(Throwable $exception)
    {
        // 开发环境显示详细错误信息
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            http_response_code(500);
            include ROOT_PATH . '/app/Views/errors/development.php';
        } else {
            // 生产环境显示友好错误页面
            http_response_code(500);
            include ROOT_PATH . '/app/Views/errors/production.php';
        }
        
        exit(1);
    }
    
    /**
     * 渲染致命错误页面
     */
    private function renderFatalErrorPage($error)
    {
        http_response_code(500);
        
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            echo '<h1>Fatal Error</h1>';
            echo '<p>Type: ' . $this->getErrorType($error['type']) . '</p>';
            echo '<p>Message: ' . $error['message'] . '</p>';
            echo '<p>File: ' . $error['file'] . '</p>';
            echo '<p>Line: ' . $error['line'] . '</p>';
        } else {
            // 生产环境显示友好错误页面
            include ROOT_PATH . '/app/Views/errors/production.php';
        }
    }
} 