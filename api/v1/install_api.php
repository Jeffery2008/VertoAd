<?php
header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'error' => ''];

// Check if config/database.php exists and contains database credentials (basic installation check)
$database_config_path = __DIR__ . '/../../config/database.php'; // Corrected path
if (file_exists($database_config_path)) {
    $database_config_content = file_get_contents($database_config_path);
    if (
        strpos($database_config_content, "private \$host = '") !== false &&
        strpos($database_config_content, "private \$db_name = '") !== false &&
        strpos($database_config_content, "private \$username = '") !== false
    ) {
        $response['success'] = false;
        $response['error'] = 'System is already installed. Re-installation is not allowed.';
        echo json_encode($response);
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['success'] = false;
    $response['error'] = 'Invalid request method. Use POST.';
    echo json_encode($response);
    exit;
}

// Retrieve form values
$database_host = $_POST['database_host'];
$database_name = $_POST['database_name'];
$database_username = $_POST['database_username'];
$database_password = $_POST['database_password'];
$base_url = $_POST['base_url'];
$app_name = $_POST['app_name'];
$jwt_secret = $_POST['jwt_secret'];
$password_salt = $_POST['password_salt'];

$install_success = false;
$error_message = '';

// Update database configuration file (config/database.php)
$database_config_content = file_get_contents($database_config_path);
$database_config_content = preg_replace("/private \$host = '.*';/", "private \$host = '" . $database_host . "';", $database_config_content);
$database_config_content = preg_replace("/private \$db_name = '.*';/", "private \$db_name = '" . $database_name . "';", $database_config_content);
$database_config_content = preg_replace("/private \$username = '.*';/", "private \$username = '" . $database_username . "';", $database_config_content);
$database_config_content = preg_replace("/private \$password = '.*';/", "private \$password = '" . $database_password . "';", $database_config_content);

// Database config file write attempt
error_log('Attempting to write database config file: ' . $database_config_path);
if (file_put_contents($database_config_path, $database_config_content) === false) {
    $error_message = 'Failed to update database configuration file.';
    error_log('Error writing database config file: ' . $error_message); // Log error
} else {
    error_log('Database config file updated successfully: ' . $database_config_path);
    // Update general configuration file (config/config.php)
    $config_path = __DIR__ . '/../../config/config.php'; // Corrected path
    error_log('Attempting to read general config file: ' . $config_path);
    $config_content = file_get_contents($config_path);
    if ($config_content === false) {
        $error_message = 'Failed to read general configuration file.';
        error_log('Error reading general config file: ' . $error_message); // Log error
    } else {
        $config_content = preg_replace("/define\('BASE_URL', '.*'\);/", "define('BASE_URL', '" . $base_url . "');", $config_content);
        $config_content = preg_replace("/define\('APP_NAME', '.*'\);/", "define('APP_NAME', '" . $app_name . "');", $config_content);
        $config_content = preg_replace("/define\('JWT_SECRET', '.*'\);/", "define('JWT_SECRET', '" . $jwt_secret . "');", $config_content);
        $config_content = preg_replace("/define\('PASSWORD_SALT', '.*'\);/", "define('PASSWORD_SALT', '" . $password_salt . "');", $config_content);

        // General config file write attempt
        error_log('Attempting to write general config file: ' . $config_path);
        if (file_put_contents($config_path, $config_content) === false) {
            $error_message = 'Failed to update general configuration file.';
            error_log('Error writing general config file: ' . $error_message); // Log error
        } else {
            error_log('General config file updated successfully: ' . $config_path);
            // Database initialization
            $sql_path = __DIR__ . '/../../setup/init_database.sql'; // Corrected path
            $sql_content = file_get_contents($sql_path);
            if ($sql_content === false) {
                $error_message = 'Failed to read SQL file.';
                error_log('Error reading SQL file: ' . $error_message); // Log error
            } else {
                error_log('SQL file read successfully: ' . $sql_path);
            }


            try {
                $db = new PDO(
                    "mysql:host=" . $database_host . ";dbname=" . $database_name . ";charset=utf8mb4",
                    $database_username,
                    $database_password
                );
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $db->exec($sql_content);
                $install_success = true;
            } catch (PDOException $e) {
                $error_message = 'Database initialization failed: ' . $e->getMessage();
                $error_message .= ' PDOException: ' . $e->getMessage(); // Append PDOException message to error
                $install_success = false;
            }
        }
    }
}

if ($install_success) {
    $response['success'] = true;
    $response['message'] = 'Installation successful! Please proceed to the application.';
} else {
    $response['success'] = false;
    $response['error'] = $error_message;
}

echo json_encode($response);
?>