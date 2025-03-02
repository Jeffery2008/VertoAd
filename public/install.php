<?php

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义常量
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');
define('DATA_PATH', ROOT_PATH . '/data');
define('MIN_PHP_VERSION', '7.4.0');
define('MIN_MYSQL_VERSION', '5.7.0');

// 检查安装锁
function checkInstallLock() {
    $lockFile = ROOT_PATH . '/install.lock';
    if (file_exists($lockFile)) {
        die('
            <div class="container">
                <h1>Installation Locked</h1>
                <p>The system has already been installed. If you need to reinstall:</p>
                <ol>
                    <li>Backup your database first</li>
                    <li>Delete the install.lock file</li>
                    <li>Ensure you have proper database credentials</li>
                </ol>
                <p class="warning">Warning: Reinstalling will erase all existing data!</p>
            </div>
        ');
    }
}

// 创建安装锁
function createInstallLock() {
    $lockFile = ROOT_PATH . '/install.lock';
    $content = json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => '1.0.0',
        'php_version' => PHP_VERSION,
        'mysql_version' => getMySQLVersion(),
        'server_info' => $_SERVER['SERVER_SOFTWARE'],
        'install_path' => ROOT_PATH
    ], JSON_PRETTY_PRINT);
    
    if (file_put_contents($lockFile, $content) === false) {
        throw new Exception('Unable to create install lock file');
    }
    chmod($lockFile, 0444); // 设置为只读
}

// 获取MySQL版本
function getMySQLVersion() {
    try {
        $pdo = getPDOConnection();
        return $pdo->query('SELECT VERSION()')->fetchColumn();
    } catch (Exception $e) {
        return 'Unknown';
    }
}

// 检查目录权限并创建必要的目录
function checkDirectoryPermissions() {
    $errors = [];
    $directories = [
        CONFIG_PATH => ['desc' => 'Configuration directory', 'perms' => 0755],
        UPLOAD_PATH => ['desc' => 'Upload directory', 'perms' => 0755],
        LOG_PATH => ['desc' => 'Log directory', 'perms' => 0755],
        DATA_PATH => ['desc' => 'Data directory', 'perms' => 0755],
        ROOT_PATH => ['desc' => 'Root directory', 'perms' => 0755]
    ];

    foreach ($directories as $path => $info) {
        if (!file_exists($path)) {
            try {
                if (!mkdir($path, $info['perms'], true)) {
                    $errors[] = "Failed to create {$info['desc']}: $path";
                    continue;
                }
            } catch (Exception $e) {
                $errors[] = "Error creating {$info['desc']}: " . $e->getMessage();
                continue;
            }
        }

        if (!is_writable($path)) {
            $errors[] = "{$info['desc']} is not writable: $path";
        }

        // 设置正确的权限
        chmod($path, $info['perms']);
    }

    return $errors;
}

// 检查系统要求
function checkSystemRequirements() {
    $errors = [];

    // 检查PHP版本
    if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
        $errors[] = sprintf(
            'PHP version %s or higher is required. Your version: %s',
            MIN_PHP_VERSION,
            PHP_VERSION
        );
    }

    // 检查必要的PHP扩展
    $requiredExtensions = [
        'pdo' => 'PDO',
        'pdo_mysql' => 'PDO MySQL',
        'mbstring' => 'Multibyte String',
        'openssl' => 'OpenSSL',
        'json' => 'JSON',
        'curl' => 'cURL',
        'gd' => 'GD Library'
    ];

    foreach ($requiredExtensions as $extension => $name) {
        if (!extension_loaded($extension)) {
            $errors[] = "PHP extension '$name' ($extension) is required.";
        }
    }

    // 检查PHP配置
    $requiredSettings = [
        'file_uploads' => true,
        'allow_url_fopen' => true,
        'memory_limit' => '128M',
        'post_max_size' => '8M',
        'upload_max_filesize' => '8M',
        'max_execution_time' => '30'
    ];

    foreach ($requiredSettings as $setting => $required) {
        $current = ini_get($setting);
        if (is_bool($required)) {
            if ($current != $required) {
                $errors[] = "PHP setting '$setting' must be " . ($required ? 'enabled' : 'disabled');
            }
        } else {
            if (!$current || return_bytes($current) < return_bytes($required)) {
                $errors[] = "PHP setting '$setting' must be at least $required";
            }
        }
    }

    return $errors;
}

// 转换内存限制字符串为字节数
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// 获取PDO连接
function getPDOConnection($dbName = null) {
    global $dbHost, $dbUser, $dbPass;
    
    $dsn = "mysql:host=$dbHost" . ($dbName ? ";dbname=$dbName" : "");
    return new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
}

// 验证数据库连接和设置
function validateDatabase($host, $name, $user, $pass) {
    try {
        $dbHost = $host;
        $dbUser = $user;
        $dbPass = $pass;
        
        // 测试连接
        $pdo = getPDOConnection();
        
        // 检查MySQL版本
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        if (version_compare($version, MIN_MYSQL_VERSION, '<')) {
            throw new Exception("MySQL version " . MIN_MYSQL_VERSION . " or higher is required. Your version: $version");
        }

        // 检查字符集和排序规则支持
        $charsets = $pdo->query("SHOW CHARACTER SET LIKE 'utf8mb4'")->fetch();
        if (!$charsets) {
            throw new Exception("MySQL utf8mb4 character set is not supported");
        }

        // 创建数据库
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        return ['success' => true, 'version' => $version];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => 'Database Error: ' . $e->getMessage()
        ];
    }
}

// 验证管理员输入
function validateAdminInput($username, $email, $password, $confirmPassword) {
    $errors = [];

    // 用户名验证
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Admin username must be at least 3 characters';
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, underscores, and hyphens';
    }

    // 邮箱验证
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid admin email format';
    }

    // 密码验证
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    // if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
    //     $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
    // }

    return $errors;
}

// 创建配置文件
function createConfigFile($host, $name, $user, $pass) {
    $template = <<<PHP
<?php
return [
    'host' => '%s',
    'dbname' => '%s',
    'username' => '%s',
    'password' => '%s',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
];
PHP;

    $config = sprintf($template, $host, $name, $user, $pass);
    $configFile = CONFIG_PATH . '/database.php';
    
    if (!is_dir(CONFIG_PATH)) {
        mkdir(CONFIG_PATH, 0755, true);
    }
    
    if (file_put_contents($configFile, $config) === false) {
        throw new Exception('Unable to write configuration file');
    }

    chmod($configFile, 0640);
}

// 初始化数据库表
function initializeDatabase($pdo) {
    try {
        // 读取SQL文件
        $sql = file_get_contents(ROOT_PATH . '/data/ad_system.sql');
        if ($sql === false) {
            throw new Exception('Could not read database schema file');
        }

        // 先尝试删除现有表，防止"表已存在"错误
        $tables = [
            'clicks',
            'impressions',
            'ad_placements',
            'activation_keys',
            'ad_views',
            'ads',
            'users'
        ];
        
        // 临时禁用外键约束检查
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        
        // 依次删除表（如果存在）
        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        
        // 重新启用外键约束检查
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // 分割SQL语句
        $statements = array_filter(
            array_map('trim', 
                explode(';', preg_replace('/\/\*.*\*\//s', '', $sql))
            )
        );

        // 执行每个语句
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }

        return true;
    } catch (Exception $e) {
        throw new Exception('Database initialization failed: ' . $e->getMessage());
    }
}

// 检查安装锁
checkInstallLock();

$errors = [];
$success = false;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 收集表单数据
    $dbHost = $_POST['db_host'] ?? 'localhost';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = $_POST['admin_user'] ?? '';
    $adminEmail = $_POST['admin_email'] ?? '';
    $adminPass = $_POST['admin_pass'] ?? '';
    $adminPassConfirm = $_POST['admin_pass_confirm'] ?? '';

    try {
        // 系统要求检查
        $errors = array_merge($errors, checkSystemRequirements());
        
        // 目录权限检查
        $errors = array_merge($errors, checkDirectoryPermissions());
        
        // 验证管理员输入
        $errors = array_merge($errors, validateAdminInput($adminUser, $adminEmail, $adminPass, $adminPassConfirm));

        if (empty($errors)) {
            // 验证数据库连接
            $dbCheck = validateDatabase($dbHost, $dbName, $dbUser, $dbPass);
            if (!$dbCheck['success']) {
                $errors[] = $dbCheck['error'];
            } else {
                $pdo = getPDOConnection($dbName);

                // 开始事务
                $pdo->beginTransaction();

                try {
                    // 初始化数据库
                    initializeDatabase($pdo);

                    // 创建管理员账户 - 先检查是否已存在
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$adminUser]);
                    $userExists = (int)$stmt->fetchColumn() > 0;
                    
                    if ($userExists) {
                        // 如果用户已存在则更新密码
                        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET email = ?, password_hash = ? WHERE username = ?");
                        $stmt->execute([$adminEmail, $passwordHash, $adminUser]);
                    } else {
                        // 如果用户不存在则创建
                        $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'admin')");
                        $stmt->execute([$adminUser, $adminEmail, $passwordHash]);
                    }

                    // 创建配置文件
                    createConfigFile($dbHost, $dbName, $dbUser, $dbPass);

                    // 创建安装锁
                    createInstallLock();

                    // 提交事务
                    $pdo->commit();
                    $success = true;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Installation Error: ' . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Ad System Installation</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --primary-color: #4CAF50;
            --error-color: #f44336;
            --success-color: #4CAF50;
            --warning-color: #ff9800;
            --border-color: #ddd;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        h2 {
            color: #666;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #666;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.2);
        }

        .button {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #45a049;
        }

        .error-list {
            list-style: none;
            padding: 1rem;
            background-color: #ffebee;
            border-left: 4px solid var(--error-color);
            margin-bottom: 1rem;
        }

        .error-list li {
            color: var(--error-color);
            margin-bottom: 0.5rem;
        }

        .warning {
            color: var(--warning-color);
            font-weight: bold;
            margin: 1rem 0;
        }

        .success-message {
            text-align: center;
            color: var(--success-color);
            margin-bottom: 2rem;
        }

        .next-steps {
            margin: 2rem 0;
            text-align: left;
        }

        .next-steps ol {
            padding-left: 1.5rem;
        }

        .next-steps li {
            margin-bottom: 0.5rem;
        }

        .security-notice {
            background-color: #fff3e0;
            padding: 1rem;
            border-left: 4px solid var(--warning-color);
            margin: 1rem 0;
        }

        .security-notice h3 {
            color: var(--warning-color);
            margin-bottom: 0.5rem;
        }

        .security-notice ul {
            padding-left: 1.5rem;
        }

        .login-link {
            margin-top: 2rem;
            text-align: center;
        }

        small {
            display: block;
            color: #666;
            margin-top: 0.25rem;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .container {
                margin: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="success-message">
                <h1>Installation Successful!</h1>
                <p>The system has been installed successfully.</p>
                
                <div class="next-steps">
                    <h2>Next Steps:</h2>
                    <ol>
                        <li>Delete the install.php file for security reasons</li>
                        <li>Set proper file permissions:
                            <ul>
                                <li>Config files (640)</li>
                                <li>Directories (755)</li>
                                <li>Upload directory (755)</li>
                            </ul>
                        </li>
                        <li>Configure your web server (if not already done)</li>
                        <li>Set up SSL certificate for secure connections</li>
                        <li>Configure backup system</li>
                    </ol>
                </div>
                
                <div class="security-notice">
                    <h3>Security Notice:</h3>
                    <p>For security reasons, please make sure to:</p>
                    <ul>
                        <li>Change default database credentials in production</li>
                        <li>Set up proper firewall rules</li>
                        <li>Enable error logging but disable error display in production</li>
                        <li>Regularly update your system and dependencies</li>
                        <li>Configure automated backups</li>
                        <li>Set up monitoring and alerts</li>
                    </ul>
                </div>
                
                <div class="login-link">
                    <a href="/admin/login" class="button">Go to Admin Login</a>
                </div>
            </div>
        <?php else: ?>
            <h1>Ad System Installation</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="error-list">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" onsubmit="return validateForm()">
                <div class="form-section">
                    <h2>Database Configuration</h2>
                    <div class="form-group">
                        <label for="db_host">Database Host:</label>
                        <input type="text" name="db_host" id="db_host" value="localhost" required>
                        <small>Usually 'localhost' or '127.0.0.1'</small>
                    </div>
                    <div class="form-group">
                        <label for="db_name">Database Name:</label>
                        <input type="text" name="db_name" id="db_name" required pattern="[a-zA-Z0-9_-]+">
                        <small>Only letters, numbers, underscores, and hyphens allowed</small>
                    </div>
                    <div class="form-group">
                        <label for="db_user">Database User:</label>
                        <input type="text" name="db_user" id="db_user" required>
                    </div>
                    <div class="form-group">
                        <label for="db_pass">Database Password:</label>
                        <input type="password" name="db_pass" id="db_pass" required>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Admin Account</h2>
                    <div class="form-group">
                        <label for="admin_user">Admin Username:</label>
                        <input type="text" name="admin_user" id="admin_user" required minlength="3" pattern="[a-zA-Z0-9_-]+">
                        <small>At least 3 characters, only letters, numbers, underscores, and hyphens</small>
                    </div>
                    <div class="form-group">
                        <label for="admin_email">Admin Email:</label>
                        <input type="email" name="admin_email" id="admin_email" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_pass">Admin Password:</label>
                        <input type="password" name="admin_pass" id="admin_pass" required minlength="8">
                        <small>At least 8 characters, must include uppercase, lowercase, number, and special character</small>
                    </div>
                    <div class="form-group">
                        <label for="admin_pass_confirm">Confirm Password:</label>
                        <input type="password" name="admin_pass_confirm" id="admin_pass_confirm" required>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="button">Install System</button>
                </div>
            </form>

            <script>
            function validateForm() {
                var password = document.getElementById('admin_pass').value;
                var confirm = document.getElementById('admin_pass_confirm').value;
                
                if (password !== confirm) {
                    alert('Passwords do not match!');
                    return false;
                }
                
                // if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/.test(password)) {
                //     alert('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character');
                //     return false;
                // }
                
                return true;
            }
            </script>
        <?php endif; ?>
    </div>
</body>
</html> 