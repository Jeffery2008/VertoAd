<?php

/**
 * PHPUnit Test Bootstrap File
 * 
 * This file sets up the environment for unit tests.
 */

// Define constants needed for testing
define('PHPUNIT_RUNNING', true);
define('ROOT_PATH', dirname(__DIR__));
define('ENVIRONMENT', 'testing');

// Autoloader setup
require_once ROOT_PATH . '/vendor/autoload.php';

// Mock PDOStatement class if it doesn't exist
if (!class_exists('\PDOStatement')) {
    class PDOStatement {}
}

// Setup test environment
$_SESSION = [];

// 设置测试环境中的服务器变量
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

// Set error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Clean up any test data from previous runs
register_shutdown_function(function() {
    // Reset global arrays that might be modified during tests
    $_GET = [];
    $_POST = [];
    $_COOKIE = [];
    $_SESSION = [];
    $_REQUEST = [];
    $_FILES = [];
});

echo "Test bootstrap complete.\n";

// 检查是否应该使用模拟数据库
$mockDatabase = getenv('MOCK_DATABASE') === 'true';

// 如果不使用模拟数据库，则设置测试数据库
if (!$mockDatabase) {
    setupTestDatabase();
}

// 加载必要的核心文件
$coreFiles = [
    '/app/Core/Database.php',
    '/app/Core/Router.php',
    '/app/Core/Request.php',
    '/app/Core/Response.php',
    '/app/Core/Controller.php',
    '/app/Core/ErrorHandler.php'
];

foreach ($coreFiles as $file) {
    if (file_exists(ROOT_PATH . $file)) {
        require_once ROOT_PATH . $file;
    }
}

// 加载控制器文件
$controllerFiles = [
    '/app/Controllers/ErrorReportController.php',
    '/app/Controllers/AuthController.php',
    '/app/Controllers/AdminController.php',
    '/app/Controllers/PublisherController.php',
    '/app/Controllers/AdController.php',
    '/app/Controllers/BillingController.php'
];

foreach ($controllerFiles as $file) {
    if (file_exists(ROOT_PATH . $file)) {
        require_once ROOT_PATH . $file;
    }
}

// 加载模型文件
$modelFiles = [
    '/app/Models/User.php',
    '/app/Models/Ad.php',
    '/app/Models/AdView.php',
    '/app/Models/ActivationKey.php'
];

foreach ($modelFiles as $file) {
    if (file_exists(ROOT_PATH . $file)) {
        require_once ROOT_PATH . $file;
    }
}

/**
 * 设置测试数据库
 */
function setupTestDatabase() {
    try {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $username = getenv('DB_USERNAME') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: '';
        $database = getenv('DB_DATABASE') ?: 'verto_ad_test';
        
        $dsn = "mysql:host={$host};port={$port}";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建测试数据库
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}`");
        $pdo->exec("USE `{$database}`");
        
        // 创建错误表
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS errors (
                id INT PRIMARY KEY AUTO_INCREMENT,
                type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                file VARCHAR(255) NOT NULL,
                line INT NOT NULL,
                trace TEXT,
                request_data TEXT,
                user_id INT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                status ENUM("new", "in_progress", "resolved", "ignored") DEFAULT "new",
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ');
        
        echo "Test database setup complete.\n";
    } catch (PDOException $e) {
        echo "Database setup failed: " . $e->getMessage() . "\n";
        
        // 在测试环境中，我们不希望数据库错误导致测试失败
        if (getenv('TESTING') !== 'true') {
            die();
        }
    }
} 