<?php
/**
 * HFI Utility Center - Main Entry Point
 */

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';

// Initialize the router
$router = new HFI\UtilityCenter\Routing\Router();

// Load routes
require_once __DIR__ . '/../routes/web_routes.php';
require_once __DIR__ . '/../routes/admin_routes.php';
require_once __DIR__ . '/../routes/api_routes.php';
require_once __DIR__ . '/../routes/security_routes.php';

// Dispatch the request
$router->dispatch(); 