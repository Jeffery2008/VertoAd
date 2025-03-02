<?php
/**
 * VertoAD API 入口点
 * 处理所有API请求并路由到适当的控制器
 */

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

// 解析请求URL
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/';

// 确保请求URL以/api/开头
if (strpos($requestUri, $basePath) !== 0) {
    echo json_encode(['error' => 'Invalid API endpoint']);
    exit;
}

// 提取API路径
$path = substr($requestUri, strlen($basePath));
$pathParts = explode('/', $path);

// 解析控制器和方法
$controllerName = !empty($pathParts[0]) ? $pathParts[0] : 'default';
$methodName = !empty($pathParts[1]) ? $pathParts[1] : 'index';
$params = array_slice($pathParts, 2);

// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // 根据控制器名称分发请求
    switch ($controllerName) {
        case 'auth':
            require_once __DIR__ . '/../app/Controllers/Api/AuthController.php';
            $controller = new \App\Controllers\Api\AuthController();
            break;
            
        case 'admin':
            require_once __DIR__ . '/../app/Controllers/BaseController.php';
            require_once __DIR__ . '/../app/Controllers/Api/AdminController.php';
            $controller = new \App\Controllers\Api\AdminController();
            break;
            
        case 'error':
        case 'errors':
            require_once __DIR__ . '/../app/Controllers/BaseController.php';
            require_once __DIR__ . '/../app/Controllers/Api/ErrorReportController.php';
            $controller = new \App\Controllers\Api\ErrorReportController();
            
            // 特殊处理 errors 控制器的方法路由
            if ($controllerName === 'errors') {
                // 重新映射一些方法名
                if ($methodName === 'dashboard') {
                    $methodName = 'getDailyErrors';
                } elseif ($methodName === 'stats') {
                    $methodName = 'getErrorStats';
                } elseif ($methodName === 'types') {
                    $methodName = 'getErrorTypes';
                } elseif ($methodName === 'list') {
                    $methodName = 'getErrors';
                }
            }
            break;
            
        default:
            echo json_encode(['error' => 'Unknown API controller']);
            exit;
    }
    
    // 调用控制器方法
    if (method_exists($controller, $methodName)) {
        // 执行方法
        call_user_func_array([$controller, $methodName], $params);
    } else {
        echo json_encode(['error' => 'Unknown API method']);
    }
} catch (Exception $e) {
    // 错误处理
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
} 