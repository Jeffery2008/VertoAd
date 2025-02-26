<?php
/**
 * VertoAD - Main Entry Point
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set error log file
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';

// Initialize the router with debug mode enabled
$router = new VertoAD\Core\Routing\Router(true);

// Load routes
require_once __DIR__ . '/../routes/web_routes.php';
require_once __DIR__ . '/../routes/admin_routes.php';
require_once __DIR__ . '/../routes/api_routes.php';
require_once __DIR__ . '/../routes/security_routes.php';

// Dispatch the request
$router->dispatch(); 