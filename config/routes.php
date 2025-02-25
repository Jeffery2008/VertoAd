<?php
/**
 * Route definitions for the ad server
 * Maps URLs to controller methods
 */

// Define routes
$routes = [
    // Public routes
    '/' => ['controller' => 'HomeController', 'method' => 'index'],
    '/login' => ['controller' => 'AuthController', 'method' => 'showLogin'],
    '/register' => ['controller' => 'AuthController', 'method' => 'showRegister'],
    
    // Advertiser routes
    '/advertiser/dashboard' => ['controller' => 'AdvertiserController', 'method' => 'dashboard'],
    '/advertiser/ads' => ['controller' => 'AdvertiserController', 'method' => 'listAds'],
    '/advertiser/activate' => ['controller' => 'AdvertiserController', 'method' => 'showActivationPage'],
    '/advertiser/activation-success' => ['controller' => 'AdvertiserController', 'method' => 'showActivationSuccess'],
    '/advertiser/create-ad' => ['controller' => 'AdvertiserController', 'method' => 'createAd'],
    '/advertiser/edit-ad' => ['controller' => 'AdvertiserController', 'method' => 'editAd'],
    '/advertiser/canvas' => ['controller' => 'AdvertiserController', 'method' => 'adCanvas'],
    '/advertiser/account' => ['controller' => 'AdvertiserController', 'method' => 'accountSettings'],
    
    // Admin routes
    '/admin/login' => ['controller' => 'AdminController', 'method' => 'login'], // Changed to login action
    '/admin/dashboard' => ['controller' => 'AdminController', 'method' => 'dashboard'],
    '/admin/positions' => ['controller' => 'AdminController', 'method' => 'listAdPositions'],
    '/admin/positions/create' => ['controller' => 'AdminController', 'method' => 'showCreateAdPositionForm'],
    '/admin/positions/edit' => ['controller' => 'AdminController', 'method' => 'showEditAdPositionForm'],
    '/admin/positions/update' => ['controller' => 'AdminController', 'method' => 'updateAdPosition'], // POST
    '/admin/positions/delete' => ['controller' => 'AdminController', 'method' => 'deleteAdPosition'], // POST
    '/admin/advertisements' => ['controller' => 'AdminController', 'method' => 'listAdvertisements'],
    '/admin/advertisements/create' => [
        ['controller' => 'AdminController', 'method' => 'showCreateAdForm'], // GET - display form
        ['controller' => 'AdminController', 'method' => 'createAd', 'method_type' => 'POST'] // POST - handle form submission
    ],
    '/admin/advertisements/edit' => ['controller' => 'AdminController', 'method' => 'showEditAdForm'], // GET - display edit form
    '/admin/advertisements/update' => ['controller' => 'AdminController', 'method' => 'updateAd', 'method_type' => 'POST'], // POST - handle edit form submission
    '/admin/advertisements/delete' => ['controller' => 'AdminController', 'method' => 'deleteAd', 'method_type' => 'POST'], // POST - handle delete action
    '/admin/keys' => ['controller' => 'KeyManagementController', 'method' => 'listKeys'],
    '/admin/generate-keys' => ['controller' => 'KeyManagementController', 'method' => 'generateKeys'],
    '/admin/key-batch' => ['controller' => 'KeyManagementController', 'method' => 'viewBatch'],
    '/admin/users' => ['controller' => 'AdminController', 'method' => 'listUsers'],
    '/admin/balances' => ['controller' => 'AdminController', 'method' => 'manageBalances'],
    '/admin/transactions' => ['controller' => 'AdminController', 'method' => 'userTransactions'],
    '/admin/keys/batch' => ['controller' => 'AdminController', 'method' => 'showBatchKeyGenerationForm'], // For displaying the form
    '/admin/keys/batch' => ['controller' => 'AdminController', 'method' => 'generateBatchKeys'], // For form submission (POST)
    '/admin/keys/single' => ['controller' => 'AdminController', 'method' => 'showSingleKeyGenerationForm'], // For displaying single key form
    '/admin/keys/single' => ['controller' => 'AdminController', 'method' => 'generateSingleKey'], // For single key generation (POST)
    '/admin/logout' => ['controller' => 'AdminController', 'method' => 'logout'], // Logout action

    // API routes (handled by API files directly)
    '/api/v1/activate' => null,
    '/api/v1/ads' => null,
    '/api/v1/positions' => null,
    '/api/v1/competition' => null,
    '/api/v1/serve' => null,
    '/api/v1/track' => null,
    '/api/v1/accounts' => null,
    '/api/v1/auth/login' => null,
    '/api/v1/auth/challenge' => null,
];

/**
 * Route dispatcher
 * Routes the request to the appropriate controller method
 */
function dispatchRoute($uri)
{
    global $routes;
    
    // Extract query string
    $uriParts = explode('?', $uri);
    $path = $uriParts[0];
    
    // Remove trailing slash
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = substr($path, 0, -1);
    }
    
    // Check if route exists
    if (isset($routes[$path])) {
        $route = $routes[$path];
        
        // API routes are handled directly by their files
        if ($route === null) {
            return false;
        }
        
        // Extract controller and method
        $controllerName = "App\\Controllers\\" . $route['controller'];
        $methodName = $route['method'];
        
        // Instantiate controller and call method
        $controller = new $controllerName();
        $controller->$methodName();
        
        return true;
    }
    
    // Check for parameterized routes
    if (preg_match('/^\/advertiser\/edit-ad\/(\d+)$/', $path, $matches)) {
        $controller = new \App\Controllers\AdvertiserController();
        $controller->editAd($matches[1]);
        return true;
    }
    
    if (preg_match('/^\/advertiser\/canvas\/(\d+)$/', $path, $matches)) {
        $controller = new \App\Controllers\AdvertiserController();
        $controller->adCanvas($matches[1]);
        return true;
    }
    
    if (preg_match('/^\/admin\/key-batch\/(\d+)$/', $path, $matches)) {
        $controller = new \App\Controllers\KeyManagementController();
        $controller->viewBatch($matches[1]);
        return true;
    }

    if (preg_match('/^\/admin\/keys\/batch\/(\d+)\/revoke$/', $path, $matches)) {
        $controller = new \App\Controllers\KeyManagementController();
        $controller->revokeBatch($matches[1]); // Pass batchId to revokeBatch method
        return true;
    }
    
    // Route not found
    return false;
}
