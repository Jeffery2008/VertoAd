<?php

/**
 * Analytics Routes
 */

// Dashboard
$router->get('/analytics/dashboard', 'AnalyticsController@dashboard');

// Export CSV
$router->get('/analytics/export-csv', 'AnalyticsController@exportCsv');

// Conversion Analytics
$router->get('/analytics/conversions', 'AnalyticsController@conversionAnalytics');

// ROI Analytics
$router->get('/analytics/roi', 'AnalyticsController@roiAnalytics'); 