<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Simple autoloader for App namespace
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    
    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators
    // Add .php at the end
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/config/routes.php';

$uri = $_SERVER['REQUEST_URI'];

if (!dispatchRoute($uri)) {
    // Route not found, display 404 error
    header('HTTP/1.0 404 Not Found');
    echo '<h1>404 Not Found</h1>';
    echo 'The requested URL was not found on this server.';
}
