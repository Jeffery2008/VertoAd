<?php
/**
 * Conversion Tracking API Endpoint
 * 
 * This endpoint is used by the conversion tracking pixel to record conversions
 */

require_once __DIR__ . '/../../../bootstrap.php';

// Create controller instance
$controller = new \App\Controllers\ConversionController();

// Call the record method to handle the request
$controller->recordPixelConversion(); 