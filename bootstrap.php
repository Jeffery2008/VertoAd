<?php
/**
 * VertoAD - Application Bootstrap
 * 
 * This file initializes the core components of the application.
 */

// 自定义自动加载器
spl_autoload_register(function ($class) {
    // 将命名空间转换为文件路径
    $prefix = 'VertoAD\\';
    $base_dir = __DIR__ . '/src/';

    // 如果类不在我们的命名空间中，跳过
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // 获取相对类名
    $relative_class = substr($class, $len);

    // 将命名空间分隔符替换为目录分隔符，添加.php后缀
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // 如果文件存在，加载它
    if (file_exists($file)) {
        require $file;
    }
});

// 检查应用是否已安装
if (!file_exists(__DIR__ . '/config/installed.php')) {
    // 应用未安装，不需要初始化数据库连接和其他组件
    return;
}

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    // 注释掉 Dotenv 相关代码，因为没有 Composer 依赖
    // $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    // $dotenv->load();
    
    // 手动加载环境变量
    $envFile = file_get_contents(__DIR__ . '/.env');
    $lines = explode("\n", $envFile);
    foreach ($lines as $line) {
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(sprintf('%s=%s', trim($name), trim($value)));
    }
}

// Set error reporting based on environment
if (getenv('APP_ENV') === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Initialize session
session_start();

// Database connection
try {
    $db = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST'),
            getenv('DB_DATABASE')
        ),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Initialize ErrorLogger
    VertoAD\Utils\ErrorLogger::init();

    // Initialize ErrorNotifier with database connection
    VertoAD\Core\Utils\ErrorNotifier::init($db);

    // Initialize Cache
    $cache = new VertoAD\Core\Utils\Cache($db);

    // Initialize Security Middleware
    $securityMiddleware = new VertoAD\Core\Middleware\SecurityMiddleware();

    // Make core utilities available globally
    $GLOBALS['db'] = $db;
    $GLOBALS['cache'] = $cache;
    $GLOBALS['securityMiddleware'] = $securityMiddleware;

    // Register global middleware
    if (class_exists('VertoAD\Core\Routing\Router')) {
        VertoAD\Core\Routing\Router::registerMiddleware($securityMiddleware);
    }
} catch (PDOException $e) {
    // 数据库连接失败
    // 如果不是在安装页面，显示错误信息
    if (!in_array($_SERVER['REQUEST_URI'], ['/install.html', '/api/v1/install_api.php'])) {
        die('数据库连接失败: ' . $e->getMessage());
    }
}

// Set timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

// Load helpers
if (file_exists(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
} 