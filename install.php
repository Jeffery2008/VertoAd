<?php

// install.php

?>
<!DOCTYPE html>
<html>
<head>
    <title>Ad Server Installation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<?php

// install.php

$install_success = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form values
    $database_host = $_POST['database_host'];
    $database_name = $_POST['database_name'];
    $database_username = $_POST['database_username'];
    $database_password = $_POST['database_password'];
    $base_url = $_POST['base_url'];
    $app_name = $_POST['app_name'];
    $jwt_secret = $_POST['jwt_secret'];
    $password_salt = $_POST['password_salt'];

    // Update database configuration file (config/database.php)
    $database_config_path = __DIR__ . '/config/database.php';
    $database_config_content = file_get_contents($database_config_path);
    $database_config_content = preg_replace("/private \$host = '.*';/", "private \$host = '" . $database_host . "';", $database_config_content);
    $database_config_content = preg_replace("/private \$db_name = '.*';/", "private \$db_name = '" . $database_name . "';", $database_config_content);
    $database_config_content = preg_replace("/private \$username = '.*';/", "private \$username = '" . $database_username . "';", $database_config_content);
    $database_config_content = preg_replace("/private \$password = '.*';/", "private \$password = '" . $database_password . "';", $database_config_content);

    if (file_put_contents($database_config_path, $database_config_content) === false) {
        $error_message = 'Failed to update database configuration file.';
    } else {
        // Update general configuration file (config/config.php)
        $config_path = __DIR__ . '/config/config.php';
        $config_content = file_get_contents($config_path);
        $config_content = preg_replace("/define\('BASE_URL', '.*'\);/", "define('BASE_URL', '" . $base_url . "');", $config_content);
        $config_content = preg_replace("/define\('APP_NAME', '.*'\);/", "define('APP_NAME', '" . $app_name . "');", $config_content);
        $config_content = preg_replace("/define\('JWT_SECRET', '.*'\);/", "define('JWT_SECRET', '" . $jwt_secret . "');", $config_content);
        $config_content = preg_replace("/define\('PASSWORD_SALT', '.*'\);/", "define('PASSWORD_SALT', '" . $password_salt . "');", $config_content);

        if (file_put_contents($config_path, $config_content) === false) {
            $error_message = 'Failed to update general configuration file.';
        } else {
            // Database initialization
            $sql_path = __DIR__ . '/setup/init_database.sql';
            $sql_content = file_get_contents($sql_path);

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
                $install_success = false;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Ad Server Installation</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container">
        <h1>Ad Server Installation</h1>
        <?php if ($install_success): ?>
        <div class="alert alert-success" role="alert">
            Installation successful! Please proceed to the application.
        </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            Error: <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="install.php">
            <h2>Database Configuration</h2>
            <div class="form-group">
                <label for="database_host">Database Host</label>
                <input type="text" class="form-control" id="database_host" name="database_host" value="localhost" required>
            </div>
            <div class="form-group">
                <label for="database_name">Database Name</label>
                <input type="text" class="form-control" id="database_name" name="database_name" value="ad_system" required>
            </div>
            <div class="form-group">
                <label for="database_username">Database Username</label>
                <input type="text" class="form-control" id="database_username" name="database_username" value="root" required>
            </div>
            <div class="form-group">
                <label for="database_password">Database Password</label>
                <input type="password" class="form-control" id="database_password" name="database_password">
            </div>

            <h2>General Configuration</h2>
            <div class="form-group">
                <label for="base_url">Base URL</label>
                <input type="url" class="form-control" id="base_url" name="base_url" value="http://localhost/ad-system" required>
            </div>
            <div class="form-group">
                <label for="app_name">Application Name</label>
                <input type="text" class="form-control" id="app_name" name="app_name" value="Ad System" required>
            </div>
            <div class="form-group">
                <label for="jwt_secret">JWT Secret Key</label>
                <input type="text" class="form-control" id="jwt_secret" name="jwt_secret" required>
                <small class="form-text text-muted">Generate a strong, random secret key.</small>
            </div>
            <div class="form-group">
                <label for="password_salt">Password Salt</label>
                <input type="text" class="form-control" id="password_salt" name="password_salt" required>
                <small class="form-text text-muted">Generate a strong, random salt value.</small>
            </div>

            <button type="submit" class="btn btn-primary">Install</button>
        </form>
    </div>
</body>
</html>
