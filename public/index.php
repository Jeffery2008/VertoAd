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

use App\Core\Container;
use App\Core\Router;
use App\Core\Request;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\PoWMiddleware;

// Start Session (Ensure this happens before using $_SESSION)
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
        'lifetime' => 0, // Session cookie
            'path' => '/',
        'domain' => '', // Adjust if needed
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // Use true in production with HTTPS
            'httponly' => true,
        'samesite' => 'Lax'
        ]);
        session_start();
    }

// --- Configuration Loading --- 
$dbConfigPath = ROOT_PATH . '/config/database.php';
if (!file_exists($dbConfigPath)) {
    // Handle missing config file error
    http_response_code(500);
    echo "<h1>Configuration Error</h1><p>Database configuration file not found.</p>";
    error_log("Critical Error: config/database.php not found.");
    exit;
}
$databaseConfig = require $dbConfigPath;

// Combine configurations if more exist
$config = [
    'database' => $databaseConfig
    // 'app' => require ROOT_PATH . '/config/app.php', // Example
];

// --- Dependency Injection Container --- 
try {
    $container = new Container($config); // Pass the loaded config
    
    // --- Temporary DB Connection Check (REMOVE LATER) ---
    try {
        $pdo = $container->get(PDO::class);
        // Optional: Check connection status if PDO doesn't throw exception on creation
        // if ($pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)) { 
        //     error_log("DB Connection Test: Success"); 
        // }
    } catch (\Exception $dbException) {
        error_log("CRITICAL DB CONNECTION FAILED: " . $dbException->getMessage());
        http_response_code(500);
        echo "<h1>Database Connection Error</h1><p>Could not connect to the database. Please check configuration and ensure the database server is running.</p>";
        exit;
    }
    // --- End Temporary Check ---
    
    // Ensure all necessary controllers are bound in Container::registerDefaultBindings()
    // Add bindings for AuthController, AdminController, etc. in Container.php
    // $container->bind(App\Controllers\AuthController::class, ...);
    // $container->bind(App\Controllers\AdminController::class, ...);
    // ... etc ...

} catch (\Exception $e) {
    // Handle container setup error (e.g., missing config)
    error_log("Container Setup Error: " . $e->getMessage());
    http_response_code(500);
    echo "<h1>Internal Server Error</h1><p>Application configuration failed.</p>";
    exit;
}

// --- Request and Router --- 
$request = new Request(); // Assumes Request class correctly parses method, uri, etc.
$router = new Router($container);


// --- Route Definitions --- 

// Public Routes (No Auth)
$router->addRoute('GET', '/', function() { echo "Welcome to VertoAD!"; });
$router->addRoute('GET', '/install.php', function() { require __DIR__ . '/install.php'; }); // Keep install route accessible if needed
$router->addRoute('GET', '/api/serve/ad/{zone_id}', 'Api\ServeController@serveAd'); // Path param handled by router
$router->addRoute('GET', '/register', function() { 
    // Generate PoW challenge when showing the form
     global $container;
     $powData = $container->get(PoWMiddleware::class)->generateChallenge();
     // Pass $powData['challenge'] and $powData['difficulty'] to the view
     require __DIR__ . '/../app/Views/Auth/register.php'; 
});
$router->addRoute('POST', '/api/auth/register', 'Api\AuthController@register', [PoWMiddleware::class]); // Apply PoW check
$router->addRoute('GET', '/login', function() { 
    // Need to make token available to the view
    global $container; // Assuming container is global or accessible here
    $csrfTokenField = $container->get(CsrfMiddleware::class)->getFormField();
    // Pass $csrfTokenField to the login view/HTML file
    // Example: Interpolate into HTML or use a template engine variable
    require __DIR__ . '/../app/Views/Auth/login.php'; 
}); 
$router->addRoute('POST', '/api/auth/login', 'Api\AuthController@login', [CsrfMiddleware::class]); // Apply CSRF check

// Authenticated Routes (Basic - just logged in)
$authMiddleware = AuthMiddleware::class;
// Apply CSRF check also to logout if it's triggered by a POST form
$router->addRoute('POST', '/api/auth/logout', 'Api\AuthController@logout', [CsrfMiddleware::class, $authMiddleware]); 

// Advertiser Routes
$advertiserMiddleware = [$authMiddleware, ['advertiser']]; // Middleware class + required role
// Add route to serve the redeem form view
$router->addRoute('GET', '/advertiser/redeem', function() { 
    global $container;
    $powData = $container->get(PoWMiddleware::class)->generateChallenge();
    require ROOT_PATH . '/app/Views/Advertiser/redeem-key.php'; 
}, $advertiserMiddleware); 
// Apply PoW middleware to the redeem API endpoint
$router->addRoute('POST', '/api/advertiser/redeem', 'Api\Advertiser\RedemptionController@redeem', [$advertiserMiddleware, PoWMiddleware::class]);
// Advertiser Ad CRUD routes:
$router->addRoute('POST', '/api/advertiser/ads', 'Api\Advertiser\AdController@create', $advertiserMiddleware);
$router->addRoute('PUT', '/api/advertiser/ads/{id}', 'Api\Advertiser\AdController@update', $advertiserMiddleware);
$router->addRoute('GET', '/api/advertiser/ads/{id}', 'Api\Advertiser\AdController@get', $advertiserMiddleware);
// Add routes for list and delete
$router->addRoute('GET', '/api/advertiser/ads', 'Api\Advertiser\AdController@list', $advertiserMiddleware);
$router->addRoute('DELETE', '/api/advertiser/ads/{id}', 'Api\Advertiser\AdController@delete', $advertiserMiddleware);

// Publisher Routes
$publisherMiddleware = [$authMiddleware, ['publisher']];
$router->addRoute('GET', '/api/publisher/zones', 'Api\Publisher\ZoneController@list', $publisherMiddleware);
$router->addRoute('POST', '/api/publisher/zones', 'Api\Publisher\ZoneController@create', $publisherMiddleware);
// TODO: Add routes for Publisher dashboard view, stats etc.
// $router->addRoute('GET', '/publisher/dashboard', function() { /* Serve HTML */ }, $publisherMiddleware);

// Admin Routes
$adminMiddleware = [$authMiddleware, ['admin']]; // Middleware class + required role
$router->addRoute('POST', '/api/admin/activation-keys', 'Api\Admin\ActivationKeyController@generate', $adminMiddleware);
$router->addRoute('GET', '/api/admin/activation-keys', 'Api\Admin\ActivationKeyController@listKeys', $adminMiddleware);
// Admin User Management Routes
$router->addRoute('GET', '/api/admin/users', 'Api\Admin\UserController@list', $adminMiddleware);
$router->addRoute('POST', '/api/admin/users', 'Api\Admin\UserController@create', $adminMiddleware);
$router->addRoute('PUT', '/api/admin/users/{id}', 'Api\Admin\UserController@update', $adminMiddleware);
$router->addRoute('DELETE', '/api/admin/users/{id}', 'Api\Admin\UserController@delete', $adminMiddleware);
// Admin Ad Approval Routes
$router->addRoute('GET', '/api/admin/ads', 'Api\Admin\AdController@list', $adminMiddleware);
$router->addRoute('POST', '/api/admin/ads/{id}/approve', 'Api\Admin\AdController@approve', $adminMiddleware);
$router->addRoute('POST', '/api/admin/ads/{id}/reject', 'Api\Admin\AdController@reject', $adminMiddleware);
// TODO: Add route to list pending ads for review: GET /api/admin/ads?status=pending
// $router->addRoute('GET', '/api/admin/ads', 'Api\Admin\AdController@listPendingAds', $adminMiddleware);
// ... etc ...

// --- Static File Serving (Simple check - Needs improvement for security/efficiency) ---
// This should ideally be handled by the web server (Nginx/Apache) config
$filePath = __DIR__ . parse_url($requestUri, PHP_URL_PATH);
if (preg_match('~^/(assets|static)/~', $requestUri) && is_file($filePath)) {
    // Determine content type (very basic)
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
    ];
    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    readfile($filePath);
        exit;
    }


// --- Dispatch Request --- 
$router->handleRequest($request); 

// Optionally handle output buffering end
ob_end_flush(); 