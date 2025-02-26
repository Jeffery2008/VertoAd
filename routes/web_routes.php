<?php
/**
 * Web Routes
 * Main web routes for the application
 */

// Auth Routes
$router->get('/admin/login', 'AdminController@showLogin');
$router->post('/admin/login', 'AdminController@login');
$router->get('/admin/logout', 'AdminController@logout');

// Home / Dashboard Routes
$router->get('/', 'HomeController@index');
$router->get('/dashboard', 'DashboardController@index');

// Static pages
$router->get('/about', 'HomeController@about');
$router->get('/contact', 'HomeController@contact');
$router->get('/terms', 'HomeController@terms');
$router->get('/privacy', 'HomeController@privacy');

// Catch-all error routes
$router->get('/error', 'ErrorController@show');
$router->get('/error/{code}', 'ErrorController@show'); 