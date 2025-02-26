<?php
/**
 * Main entry point - redirect to public directory
 */

// Simply redirect all requests to the public directory
$public_path = 'public' . $_SERVER['REQUEST_URI'];
include __DIR__ . '/public/index.php';
