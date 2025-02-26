<?php
/**
 * Error Logging Configuration
 */
return [
    // General settings
    'enabled' => true,
    
    // Log file settings
    'log_path' => dirname(__DIR__) . '/logs',
    'file_prefix' => 'error_',
    
    // Database logging settings
    'use_database' => true,
    'auto_categorize' => true,
    
    // Notification settings
    'notifications' => [
        'enabled' => true,
        'throttle_limit' => 10,    // Maximum number of notifications per period
        'throttle_period' => 15,   // Period in minutes
        'group_similar' => true,   // Group similar errors in notifications
    ],
    
    // Email notification settings
    'email' => [
        'enabled' => true,
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'errors@example.com',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Error Monitoring System',
        'subject_prefix' => '[ERROR] ',
        'include_stacktrace' => true,
        
        // SMTP settings (used if mail() function doesn't work)
        'smtp' => [
            'host' => getenv('MAIL_HOST') ?: 'smtp.example.com',
            'port' => getenv('MAIL_PORT') ?: 587,
            'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
            'username' => getenv('MAIL_USERNAME') ?: '',
            'password' => getenv('MAIL_PASSWORD') ?: '',
            'auth' => !empty(getenv('MAIL_USERNAME'))
        ]
    ],
    
    // Severity levels and their thresholds
    'severity' => [
        'default' => 'medium',
        'levels' => [
            'low' => 0,
            'medium' => 1,
            'high' => 2,
            'critical' => 3
        ],
        'notification_threshold' => 'high' // Only send notifications for errors at or above this severity
    ],
    
    // Error types to ignore (won't be logged)
    'ignore_types' => [
        E_DEPRECATED,
        E_USER_DEPRECATED,
    ],
    
    // Patterns to ignore (regex)
    'ignore_patterns' => [
        '/^file_get_contents\(.*?\): failed to open stream: HTTP request failed/i',
        '/^Undefined index: /'
    ],
    
    // Client-side error logging
    'client' => [
        'enabled' => true,
        'throttle_limit' => 5,      // Maximum errors per minute from a client
        'include_stacktrace' => true,
        'ignore_patterns' => [
            // Ignore errors from browser extensions
            '/^chrome-extension:\/\//',
            '/^moz-extension:\/\//',
            '/^safari-extension:\/\//',
            // Ignore common third-party script errors
            '/^Script error\./'
        ]
    ]
]; 