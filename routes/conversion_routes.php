<?php

/**
 * Conversion Routes
 */

// API Endpoints
$router->get('/api/v1/track/conversion', 'ConversionController@recordConversion');
$router->post('/api/v1/track/conversion', 'ConversionController@recordConversion');
$router->options('/api/v1/track/conversion', 'ConversionController@recordConversion');

// Admin Routes
$router->get('/admin/conversion-types', 'ConversionController@manageConversionTypes');
$router->post('/admin/conversion-types', 'ConversionController@manageConversionTypes');

// Advertiser Routes
$router->get('/advertiser/conversion-pixels', 'ConversionController@manageConversionPixels');
$router->post('/advertiser/conversion-pixels', 'ConversionController@manageConversionPixels'); 