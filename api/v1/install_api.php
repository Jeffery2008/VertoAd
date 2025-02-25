<?php
header('Content-Type: application/json');

// Prevent access if already installed
if (file_exists(__DIR__ . '/../../config/installed.php')) {
    die(json_encode([
        'success' => false,
        'message' => 'Application is already installed.'
    ]));
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = [
    'dbHost', 'dbName', 'dbUser', 'dbPass',
    'adminEmail', 'adminUsername', 'adminPassword',
    'siteUrl', 'siteName'
];

foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        die(json_encode([
            'success' => false,
            'message' => "Missing required field: $field"
        ]));
    }
}

try {
    // Test database connection
    $pdo = new PDO(
        "mysql:host={$data['dbHost']};charset=utf8mb4",
        $data['dbUser'],
        $data['dbPass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$data['dbName']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$data['dbName']}`");
    
    // Create tables
    $tables = [
        // Users table
        "CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `username` varchar(50) NOT NULL,
            `email` varchar(100) NOT NULL,
            `password` varchar(255) NOT NULL,
            `role` enum('admin','advertiser','publisher') NOT NULL,
            `status` enum('active','inactive','pending') NOT NULL DEFAULT 'pending',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `username` (`username`),
            UNIQUE KEY `email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Advertisements table
        "CREATE TABLE IF NOT EXISTS `advertisements` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `advertiser_id` int(11) NOT NULL,
            `title` varchar(100) NOT NULL,
            `description` text,
            `image_url` varchar(255) NOT NULL,
            `target_url` varchar(255) NOT NULL,
            `status` enum('active','paused','pending','rejected') NOT NULL DEFAULT 'pending',
            `budget` decimal(10,2) NOT NULL DEFAULT '0.00',
            `daily_budget` decimal(10,2) NOT NULL DEFAULT '0.00',
            `bid_amount` decimal(10,4) NOT NULL DEFAULT '0.0000',
            `start_date` date DEFAULT NULL,
            `end_date` date DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `advertiser_id` (`advertiser_id`),
            CONSTRAINT `fk_advertisements_advertiser` FOREIGN KEY (`advertiser_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Ad Positions table
        "CREATE TABLE IF NOT EXISTS `ad_positions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(50) NOT NULL,
            `description` text,
            `width` int(11) NOT NULL,
            `height` int(11) NOT NULL,
            `status` enum('active','inactive') NOT NULL DEFAULT 'active',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Impressions table
        "CREATE TABLE IF NOT EXISTS `impressions` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ad_id` int(11) NOT NULL,
            `viewer_id` varchar(50) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` varchar(255) NOT NULL,
            `referer` varchar(255) DEFAULT NULL,
            `location_country` varchar(2) DEFAULT NULL,
            `location_region` varchar(50) DEFAULT NULL,
            `location_city` varchar(50) DEFAULT NULL,
            `device_type` varchar(20) DEFAULT NULL,
            `cost` decimal(10,4) NOT NULL DEFAULT '0.0000',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ad_id` (`ad_id`),
            KEY `viewer_id` (`viewer_id`),
            CONSTRAINT `fk_impressions_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Clicks table
        "CREATE TABLE IF NOT EXISTS `clicks` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `impression_id` int(11) NOT NULL,
            `viewer_id` varchar(50) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `user_agent` varchar(255) NOT NULL,
            `referer` varchar(255) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `impression_id` (`impression_id`),
            KEY `viewer_id` (`viewer_id`),
            CONSTRAINT `fk_clicks_impression` FOREIGN KEY (`impression_id`) REFERENCES `impressions` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Ad Targeting table
        "CREATE TABLE IF NOT EXISTS `ad_targeting` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `ad_id` int(11) NOT NULL,
            `target_type` varchar(50) NOT NULL,
            `target_value` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ad_id` (`ad_id`),
            CONSTRAINT `fk_targeting_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];

    // Execute table creation queries
    foreach ($tables as $query) {
        $pdo->exec($query);
    }

    // Create admin user
    $hashedPassword = password_hash($data['adminPassword'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')");
    $stmt->execute([$data['adminUsername'], $data['adminEmail'], $hashedPassword]);

    // Create configuration file
    $config = [
        'db' => [
            'host' => $data['dbHost'],
            'name' => $data['dbName'],
            'user' => $data['dbUser'],
            'pass' => $data['dbPass']
        ],
        'site' => [
            'url' => rtrim($data['siteUrl'], '/'),
            'name' => $data['siteName']
        ],
        'jwt_secret' => bin2hex(random_bytes(32)),
        'installed' => true
    ];

    // Create config directory if it doesn't exist
    if (!is_dir(__DIR__ . '/../../config')) {
        mkdir(__DIR__ . '/../../config', 0755, true);
    }

    // Write configuration
    file_put_contents(
        __DIR__ . '/../../config/config.php',
        '<?php return ' . var_export($config, true) . ';'
    );

    // Create installed flag file
    file_put_contents(
        __DIR__ . '/../../config/installed.php',
        '<?php return true;'
    );

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Installation completed successfully. You can now log in with your admin account.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Installation failed: ' . $e->getMessage()
    ]);
}
?>
