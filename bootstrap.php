<?php
/**
 * VertoAD - Application Bootstrap
 * 
 * This file initializes the core components of the application.
 */

// Include autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
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
VertoAD\Core\Utils\ErrorLogger::init();

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

// Set timezone
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

// Load helpers
require_once __DIR__ . '/helpers.php'; 