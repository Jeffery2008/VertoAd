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

// 检查是否为API请求
if (strpos($requestUri, '/api/') === 0) {
    // API请求处理
    handleApiRequest($requestUri);
    exit;
}

// 正常MVC流程处理
// 直接引入基础控制器
require_once ROOT_PATH . '/app/Core/Controller.php';

// 自动加载器
spl_autoload_register(function ($class) {
    try {
        // 创建日志目录
        $logDir = ROOT_PATH . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }

        // 详细的调试信息
        $debug = "\n=== Autoloader Debug ===\n";
        $debug .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $debug .= "Class requested: " . $class . "\n";
        $debug .= "ROOT_PATH: " . ROOT_PATH . "\n";

        // 尝试直接从app目录加载
        $appPath = ROOT_PATH . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $class) . '.php';
        $debug .= "Trying path: " . $appPath . "\n";
        $debug .= "File exists: " . (file_exists($appPath) ? 'Yes' : 'No') . "\n";
        
        if (file_exists($appPath)) {
            $debug .= "Loading file: " . $appPath . "\n";
            require_once $appPath;
            $debug .= "File loaded successfully\n";
            error_log($debug, 3, ROOT_PATH . '/logs/autoload.log');
            return true;
        }

        // 如果类是必需的，记录错误并抛出异常
        $debug .= "Failed to load class\n";
        $debug .= "=== End Autoloader Debug ===\n";
        error_log($debug, 3, ROOT_PATH . '/logs/autoload.log');
        
        throw new \Exception("Unable to load class: {$class}");
    } catch (\Exception $e) {
        error_log("Autoloader Exception: " . $e->getMessage() . "\n", 3, ROOT_PATH . '/logs/error.log');
        throw $e;
    }
});

use App\Core\Router;
use App\Core\Request;

$router = new Router();
$request = new Request();

// 处理API请求的函数
function handleApiRequest($requestUri) {
    // 在启动会话前设置cookie参数
    if (session_status() === PHP_SESSION_NONE) {
        // 先设置会话cookie参数
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => false,  // 开发环境暂时设为false
            'httponly' => true,
            'samesite' => 'Lax'  // 开发环境改用Lax
        ]);
        // 然后启动会话
        session_start();
    }

    // 允许跨域请求（开发环境使用）
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // 如果是OPTIONS请求，直接返回200状态码
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // 设置内容类型为JSON
    header('Content-Type: application/json');

    // 首先加载基础控制器
    require_once ROOT_PATH . '/app/Controllers/BaseController.php';

    // 提取API路径
    $basePath = '/api/';
    $path = substr($requestUri, strlen($basePath));
    
    // 分离查询字符串
    $pathParts = explode('?', $path);
    $path = $pathParts[0];
    
    // 分割路径部分
    $pathParts = explode('/', $path);

    // 解析控制器和方法
    $controllerName = !empty($pathParts[0]) ? $pathParts[0] : 'default';
    $methodName = !empty($pathParts[1]) ? $pathParts[1] : 'index';
    
    // 特殊处理 admin/keys 路由
    if ($controllerName === 'admin' && !empty($pathParts[1]) && $pathParts[1] === 'keys') {
        require_once ROOT_PATH . '/app/Core/Database.php';
        require_once ROOT_PATH . '/app/Models/KeyModel.php';
        require_once ROOT_PATH . '/app/Controllers/Api/KeyController.php';
        $controller = new \App\Controllers\Api\KeyController();
        $methodName = !empty($pathParts[2]) ? $pathParts[2] : 'index';
        $params = array_slice($pathParts, 3);
    } else {
        // 方法名映射
        $methodMap = [
            'stats' => 'getStats',
            'users' => 'getUsers',
            'all-users' => 'getAllUsers',
            'errors' => 'errors'
        ];
        
        // 如果存在映射，使用映射的方法名
        if (isset($methodMap[$methodName])) {
            $methodName = $methodMap[$methodName];
        } else {
            // 如果没有映射，才将短横线命名转换为驼峰命名
            $methodName = preg_replace_callback('/-([a-z])/', function($matches) {
                return strtoupper($matches[1]);
            }, $methodName);
        }
        
        $params = array_slice($pathParts, 2);

        // 根据控制器名称分发请求
        $controller = null;
        switch ($controllerName) {
            case 'auth':
                require_once ROOT_PATH . '/app/Controllers/Api/AuthController.php';
                $controller = new \App\Controllers\Api\AuthController();
                break;
                
            case 'admin':
                require_once ROOT_PATH . '/app/Controllers/Api/AdminController.php';
                $controller = new \App\Controllers\Api\AdminController();
                break;
                
            case 'error':
            case 'errors':
                require_once ROOT_PATH . '/app/Controllers/Api/ErrorReportController.php';
                $controller = new \App\Controllers\Api\ErrorReportController();
                break;
                
            default:
                throw new \Exception('Unknown controller: ' . $controllerName);
        }
    }

    try {
        // 检查方法是否存在
        if (!method_exists($controller, $methodName)) {
            throw new \Exception('Unknown API method: ' . $methodName);
        }
        
        // 调用方法并获取结果
        $result = $controller->$methodName(...$params);
        
        // 如果结果不是字符串（可能是数组），将其转换为JSON
        if (!is_string($result)) {
            echo json_encode($result);
        }
        
    } catch (\Exception $e) {
        http_response_code(400);
        echo json_encode([
            'error' => $e->getMessage(),
            'method' => $methodName,
            'controller' => get_class($controller ?? null)
        ]);
    }
}

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
$router->addRoute('GET', '/admin/users', 'AdminController@users');
$router->addRoute('GET', '/admin/settings', 'AdminController@settings');
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

// Admin routes
$router->addRoute('GET', '/admin/zones', function() {
    require __DIR__ . '/admin/zones.html';
});
$router->addRoute('GET', '/admin/zone-targeting', function() {
    require __DIR__ . '/admin/zone-targeting.html';
});
$router->addRoute('GET', '/admin/zone-targeting-stats', function() {
    require __DIR__ . '/admin/zone-targeting-stats.html';
});

// Admin API routes
$router->addRoute('GET', '/api/admin/zones', 'AdminController@getZones');
$router->addRoute('GET', '/api/admin/zones/{id}', 'AdminController@getZone');
$router->addRoute('GET', '/api/admin/publishers', 'AdminController@getPublishers');
$router->addRoute('GET', '/api/admin/zone-targeting', 'AdminController@getZoneTargeting');
$router->addRoute('GET', '/api/admin/zone-targeting/{id}', 'AdminController@getZoneTargetingById');
$router->addRoute('POST', '/api/admin/update-zone-status/{id}', 'AdminController@updateZoneStatus');
$router->addRoute('POST', '/api/admin/update-zone-targeting/{id}', 'AdminController@updateZoneTargeting');
$router->addRoute('GET', '/api/admin/zone-targeting-stats', 'AdminController@getZoneTargetingStats');
$router->addRoute('GET', '/api/admin/export-zones', 'AdminController@exportZones');
$router->addRoute('GET', '/api/admin/export-zone-targeting-stats', 'AdminController@exportZoneTargetingStats');

// Error Report routes
$router->addRoute('GET', '/admin/errors/dashboard', 'ErrorReportController@dashboard');
$router->addRoute('GET', '/admin/errors', 'ErrorReportController@list');
$router->addRoute('GET', '/admin/errors/view/{id}', 'ErrorReportController@viewError');
$router->addRoute('POST', '/admin/errors/update-status/{id}', 'ErrorReportController@updateStatus');
$router->addRoute('GET', '/admin/errors/stats', 'ErrorReportController@getStats');
$router->addRoute('POST', '/admin/errors/bulk-update', 'ErrorReportController@bulkUpdate');

// 处理请求
$router->handleRequest($request); 