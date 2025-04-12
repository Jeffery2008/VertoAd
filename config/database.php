<?php

// config/database.php

// Use environment variables for sensitive data if possible, otherwise define directly.
// Ensure this file is NOT publicly accessible via the web server.

return [
    'host' => getenv('DB_HOST') ?: '127.0.0.1', 
    'port' => getenv('DB_PORT') ?: '3306',
    'dbname' => getenv('DB_NAME') ?: 'vertoad_db', // Replace with your database name
    'username' => getenv('DB_USER') ?: 'root', // Replace with your database username
    'password' => getenv('DB_PASS') ?: '', // Replace with your database password
    'charset' => 'utf8mb4'
];