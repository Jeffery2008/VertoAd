<?php
define('BASE_URL', 'test');
define('APP_NAME', 'TestApp');

// API configuration
define('API_VERSION', 'v1');
define('API_KEY_HEADER', 'X-API-Key');

// File upload configuration
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'svg']);

// Ad configuration
define('DEFAULT_AD_PRIORITY', 0);
define('MAX_AD_PRIORITY', 10);
define('CACHE_DURATION', 3600); // 1 hour

// Security configuration
define('JWT_SECRET', ''); 
define('PASSWORD_SALT', ''); 
define('TOKEN_EXPIRY', 86400); // 24 hours

// Database tables
define('TABLE_ADVERTISERS', 'advertisers');
define('TABLE_ADVERTISEMENTS', 'advertisements');
define('TABLE_AD_POSITIONS', 'ad_positions');
define('TABLE_AD_STATISTICS', 'ad_statistics');
define('TABLE_GEOGRAPHIC_STATS', 'geographic_stats');
define('TABLE_DEVICE_STATS', 'device_stats');
define('TABLE_AUDIT_LOGS', 'audit_logs');
