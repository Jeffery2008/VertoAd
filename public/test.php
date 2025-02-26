<?php
/**
 * Test script to debug routing issues
 */

echo '<pre>';
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n\n";

echo "Current file: " . __FILE__ . "\n";
echo "Current directory: " . __DIR__ . "\n\n";

echo "Configured routes:\n";
$routes = [
    'web_routes.php' => file_exists(__DIR__ . '/../routes/web_routes.php'),
    'admin_routes.php' => file_exists(__DIR__ . '/../routes/admin_routes.php'),
    'api_routes.php' => file_exists(__DIR__ . '/../routes/api_routes.php'),
    'security_routes.php' => file_exists(__DIR__ . '/../routes/security_routes.php')
];

foreach ($routes as $route => $exists) {
    echo "$route: " . ($exists ? "Exists" : "Not found") . "\n";
}

echo "\nRouter class: " . (class_exists('VertoAD\\Core\\Routing\\Router') ? "Found" : "Not found") . "\n";
echo "AdminController: " . (class_exists('VertoAD\\Core\\Controllers\\AdminController') ? "Found" : "Not found") . "\n";
echo '</pre>'; 