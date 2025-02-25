<?php
// Check if application is installed
if (!file_exists(__DIR__ . '/config/installed.php')) {
    header('Location: /install.html');
    exit;
}

// Load configuration
$config = require __DIR__ . '/config/config.php';

// Set error reporting based on environment
if (isset($config['environment']) && $config['environment'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// Autoloader
spl_autoload_register(function ($class) {
    // Convert namespace separators to directory separators
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    
    // Remove "App\" from the beginning of the class name
    $class = str_replace('App' . DIRECTORY_SEPARATOR, '', $class);
    
    // Build the full path to the class file
    $file = __DIR__ . '/src/' . $class . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Start session
session_start();

// Load and dispatch routes
require __DIR__ . '/config/routes.php';

// Get the current URI
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Dispatch the route
if (!dispatchRoute($uri)) {
    // Route not found - show 404 page
    header("HTTP/1.0 404 Not Found");
    require __DIR__ . '/templates/404.php';
}
