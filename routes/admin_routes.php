// Error Tracking Routes
$router->get('/admin/errors/dashboard', 'Admin\ErrorController@dashboard');
$router->get('/admin/errors', 'Admin\ErrorController@index');
$router->get('/admin/errors/view/{id}', 'Admin\ErrorController@view');
$router->post('/admin/errors/resolve/{id}', 'Admin\ErrorController@resolve');
$router->post('/admin/errors/ignore/{id}', 'Admin\ErrorController@ignore');
$router->get('/admin/errors/categories', 'Admin\ErrorController@categories');
$router->post('/admin/errors/categories', 'Admin\ErrorController@categories');
$router->get('/admin/errors/subscriptions', 'Admin\ErrorController@subscriptions');
$router->post('/admin/errors/subscriptions', 'Admin\ErrorController@subscriptions');
$router->post('/admin/errors/generate-test-error', 'Admin\ErrorController@generateTestError'); 