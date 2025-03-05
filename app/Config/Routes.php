// 添加API路由组
$routes->group('api', function ($routes) {
    // Auth API
    $routes->get('auth/check-status', 'Api\AuthController::checkStatus');
    $routes->get('auth/logout', 'Api\AuthController::logout');
    $routes->post('auth/login', 'Api\AuthController::login');
    
    // Admin API - 激活码管理
    $routes->group('admin/keys', ['namespace' => 'App\Controllers\Api'], function($routes) {
        $routes->post('generate', 'KeyController::generate');
        $routes->get('recent', 'KeyController::recent');
        $routes->get('stats', 'KeyController::stats');
        $routes->get('export', 'KeyController::export');
    });
    
    // Admin API - 其他
    $routes->get('admin/stats', 'Api\AdminController::getStats');
    $routes->get('admin/users', 'Api\AdminController::getUsers');
    $routes->get('admin/users/all', 'Api\AdminController::getAllUsers');
    $routes->get('admin/users/(:num)', 'Api\AdminController::getUser/$1');
    
    // Error Report API
    $routes->get('admin/error-stats', 'Api\ErrorReportController::getErrorStats');
    $routes->get('admin/error-types', 'Api\ErrorReportController::getErrorTypes');
    $routes->get('admin/errors', 'Api\ErrorReportController::getErrors');
    $routes->get('admin/errors/(:num)', 'Api\ErrorReportController::getError/$1');
    $routes->post('admin/errors/update-status/(:num)', 'Api\ErrorReportController::updateStatus/$1');
    $routes->post('admin/errors/bulk-update', 'Api\ErrorReportController::bulkUpdateStatus');
    $routes->get('admin/errors/recent', 'Api\ErrorReportController::getRecentErrors');
    $routes->get('admin/errors/daily', 'Api\ErrorReportController::getDailyErrors');
    $routes->get('admin/errors/by-type', 'Api\ErrorReportController::getErrorsByType');
    $routes->get('admin/errors/hourly', 'Api\ErrorReportController::getHourlyErrors');
    $routes->get('admin/errors/common-messages', 'Api\ErrorReportController::getCommonMessages');
    $routes->get('admin/errors/similar', 'Api\ErrorReportController::getSimilarErrors');
});

// 删除旧的激活码路由组
// $routes->group('api/admin/keys', ['namespace' => 'App\Controllers\Api'], function($routes) {
//     $routes->post('generate', 'KeyController::generate');
//     $routes->get('recent', 'KeyController::recent');
//     $routes->get('stats', 'KeyController::stats');
//     $routes->get('export', 'KeyController::export');
// }); 