<?php
// 设置安全头
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// 强制显示错误
@error_reporting(-1);
@ini_set('display_errors', '1');

// 确保显示所有错误
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 设置错误日志
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// 检查是否已安装
$installLockFile = dirname(__DIR__) . '/install.lock';
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$requestUri = $_SERVER['REQUEST_URI'];

// 如果未安装且不是访问安装相关页面，则重定向到安装页面
if (!file_exists($installLockFile) 
    && $currentScript !== 'install.php'
    && !preg_match('~^/(assets|static)/~', $requestUri)) {
    header('Location: /install.php');
    exit;
}

// 如果已安装且访问安装页面，则阻止访问
if (file_exists($installLockFile) && $currentScript === 'install.php') {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    echo '<p>Installation has already been completed. Please remove install.php for security.</p>';
    exit;
}

// 自定义错误处理器
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $message = date('[Y-m-d H:i:s]') . " Error: [$errno] $errstr in $errfile on line $errline\n";
    error_log($message);
    
    if (ini_get('display_errors')) {
        echo "<h1>PHP Error</h1>";
        echo "<p>Type: " . $errno . "</p>";
        echo "<p>Message: " . htmlspecialchars($errstr) . "</p>";
        echo "<p>File: " . htmlspecialchars($errfile) . "</p>";
        echo "<p>Line: " . $errline . "</p>";
    }
    
    return true;
}

// 设置错误处理器
set_error_handler("customErrorHandler");

// 自定义异常处理器
function customExceptionHandler($exception) {
    $message = date('[Y-m-d H:i:s]') . " Exception: " . $exception->getMessage() . 
               " in " . $exception->getFile() . " on line " . $exception->getLine() . "\n" .
               "Stack trace: " . $exception->getTraceAsString() . "\n";
    error_log($message);
    
    if (ini_get('display_errors')) {
        echo "<h1>PHP Exception</h1>";
        echo "<p>Message: " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p>File: " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p>Line: " . $exception->getLine() . "</p>";
        echo "<pre>Stack trace:\n" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
    }
}

// 设置异常处理器
set_exception_handler("customExceptionHandler");

define('ROOT_PATH', dirname(__DIR__));

// 创建日志目录
$logDir = ROOT_PATH . '/logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

// 添加输出缓冲
ob_start();

// 直接引入基础控制器
require_once ROOT_PATH . '/app/Core/Controller.php';

// 自动加载器
spl_autoload_register(function ($class) {
    try {
        // 将命名空间转换为文件路径
        $file = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $path = ROOT_PATH . DIRECTORY_SEPARATOR . $file . '.php';
        
        // 详细的调试信息
        error_log("\n=== Autoloader Debug ===");
        error_log("Time: " . date('Y-m-d H:i:s'));
        error_log("Class requested: " . $class);
        error_log("ROOT_PATH: " . ROOT_PATH);
        error_log("Full path: " . $path);
        error_log("File exists: " . (file_exists($path) ? 'Yes' : 'No'));
        error_log("Is readable: " . (is_readable($path) ? 'Yes' : 'No'));
        
        if (file_exists($path)) {
            error_log("Loading file: " . $path);
            require_once $path;
            error_log("File loaded successfully: " . $path);
            return true;
        }
        
        // 尝试直接从app目录加载
        $appPath = ROOT_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $file . '.php';
        error_log("Trying alternative path: " . $appPath);
        error_log("Alternative path exists: " . (file_exists($appPath) ? 'Yes' : 'No'));
        error_log("Is readable: " . (is_readable($appPath) ? 'Yes' : 'No'));
        
        if (file_exists($appPath)) {
            error_log("Loading from alternative path: " . $appPath);
            require_once $appPath;
            error_log("File loaded successfully from alternative path");
            return true;
        }
        
        error_log("Failed to load class: " . $class);
        error_log("=== End Autoloader Debug ===\n");
        
        // 如果类是必需的，抛出异常
        throw new Exception("Unable to load class: {$class}");
    } catch (Exception $e) {
        error_log("Autoloader Exception: " . $e->getMessage());
        throw $e;
    }
});

use App\Core\Router;
use App\Core\Request;

$router = new Router();
$request = new Request();

// 注册路由
$router->addRoute('GET', '/', function() {
    echo "Welcome to the VertoAD!";
});

// Auth routes
$router->addRoute('GET', '/admin/login', function() {
    require __DIR__ . '/admin/login.html';
});
$router->addRoute('POST', '/api/auth/login', 'AuthController@login');
$router->addRoute('GET', '/admin/logout', 'AuthController@logout');
$router->addRoute('GET', '/admin/dashboard', 'AdminController@dashboard');
$router->addRoute('GET', '/admin/generate-keys', 'AdminController@generateKeys');
$router->addRoute('POST', '/admin/generate-keys', 'AdminController@generateKeys');
$router->addRoute('GET', '/register', 'AuthController@register');
$router->addRoute('POST', '/register', 'AuthController@register');

// Ad Editor route
$router->addRoute('GET', '/ad-editor', function() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login');
        exit;
    }
    require __DIR__ . '/ad-editor.html';
});

// Ad management routes
$router->addRoute('GET', '/api/ads', 'AdController@list');
$router->addRoute('POST', '/api/ads', 'AdController@create');
$router->addRoute('GET', '/api/ads/{id}', 'AdController@get');
$router->addRoute('PUT', '/api/ads/{id}', 'AdController@update');
$router->addRoute('DELETE', '/api/ads/{id}', 'AdController@delete');
$router->addRoute('POST', '/api/ads/{id}/submit', 'AdController@submit');
$router->addRoute('POST', '/api/ads/{id}/approve', 'AdController@approve');
$router->addRoute('POST', '/api/ads/{id}/reject', 'AdController@reject');

// Ad serving routes
$router->addRoute('GET', '/api/serve', 'AdController@serve');
$router->addRoute('POST', '/api/track', 'AdController@track');

// Billing routes
$router->addRoute('GET', '/api/credits', 'BillingController@getCredits');
$router->addRoute('GET', '/api/ads/{id}/stats', 'BillingController@getAdStats');
$router->addRoute('POST', '/api/credits/add', 'BillingController@addCredits');

// Publisher routes
$router->addRoute('GET', '/publisher/dashboard', 'PublisherController@dashboard');
$router->addRoute('GET', '/publisher/stats', 'PublisherController@stats');

// Error Report routes
$router->addRoute('GET', '/admin/errors/dashboard', 'ErrorReportController@dashboard');
$router->addRoute('GET', '/admin/errors', 'ErrorReportController@list');
$router->addRoute('GET', '/admin/errors/view/{id}', 'ErrorReportController@viewError');
$router->addRoute('POST', '/admin/errors/update-status/{id}', 'ErrorReportController@updateStatus');
$router->addRoute('GET', '/admin/errors/stats', 'ErrorReportController@getStats');
$router->addRoute('POST', '/admin/errors/bulk-update', 'ErrorReportController@bulkUpdate');

// 处理请求
$router->handleRequest($request); 