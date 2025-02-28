<?php
namespace VertoAD\Core\Controllers;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Config;

class InstallController extends BaseController {
    private $basePath;
    private $configPath;
    private $lockFile;
    
    private $requiredExtensions = [
        'pdo',
        'pdo_mysql',
        'json',
        'mbstring',
        'curl',
        'gd'
    ];
    
    private $requiredWritableDirs = [
        'config',
        'logs',
        'public/uploads',
        'cache'
    ];
    
    public function __construct() {
        parent::__construct();
        $this->basePath = $this->getBasePath();
        $this->configPath = $this->basePath . '/config';
        $this->lockFile = $this->basePath . '/install.lock';
    }
    
    /**
     * 显示安装页面
     */
    public function index() {
        // 检查是否已安装
        if (file_exists($this->lockFile)) {
            $this->redirect('/admin');
            return;
        }
        
        // 执行系统检查
        $systemChecks = $this->performSystemChecks();
        
        // 渲染安装页面
        $this->render('install/index', [
            'systemChecks' => $systemChecks
        ]);
    }
    
    /**
     * 处理安装请求
     */
    public function install() {
        // 检查是否已安装
        if (file_exists($this->lockFile)) {
            $this->jsonResponse([
                'success' => false,
                'message' => '系统已安装，如需重新安装请删除 install.lock 文件'
            ]);
            return;
        }
        
        // 验证输入数据
        $input = $this->validateInput($_POST);
        if (!$input) {
            $this->jsonResponse([
                'success' => false,
                'message' => '输入数据验证失败'
            ]);
            return;
        }
        
        try {
            // 测试数据库连接
            if (!$this->testDatabaseConnection($input)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => '数据库连接失败，请检查配置'
                ]);
                return;
            }
            
            // 保存配置文件
            if (!$this->saveConfig($input)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => '保存配置文件失败'
                ]);
                return;
            }
            
            // 创建数据库表
            if (!$this->createDatabaseTables()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => '创建数据库表失败'
                ]);
                return;
            }
            
            // 初始化基础数据
            if (!$this->initializeBaseData()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => '初始化基础数据失败'
                ]);
                return;
            }
            
            // 创建管理员账号
            if (!$this->createAdminAccount($input['admin_user'], $input['admin_pass'], $input['admin_email'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => '创建管理员账号失败'
                ]);
                return;
            }
            
            // 创建安装锁定文件
            $this->createInstallLock();
            
            $this->jsonResponse([
                'success' => true,
                'message' => '安装成功'
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '安装过程出错：' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * 执行系统检查
     */
    private function performSystemChecks(): array {
        $checks = [
            'extensions' => [
                'title' => 'PHP扩展检查',
                'items' => []
            ],
            'directories' => [
                'title' => '目录权限检查',
                'items' => []
            ],
            'requirements' => [
                'title' => '系统要求检查',
                'items' => [
                    [
                        'name' => 'PHP版本 >= 7.4',
                        'status' => version_compare(PHP_VERSION, '7.4.0', '>='),
                        'current' => PHP_VERSION
                    ],
                    [
                        'name' => '内存限制 >= 128M',
                        'status' => $this->checkMemoryLimit(),
                        'current' => ini_get('memory_limit')
                    ]
                ]
            ]
        ];
        
        // 检查PHP扩展
        foreach ($this->requiredExtensions as $ext) {
            $checks['extensions']['items'][] = [
                'name' => $ext,
                'status' => extension_loaded($ext),
                'current' => extension_loaded($ext) ? '已安装' : '未安装'
            ];
        }
        
        // 检查目录权限
        foreach ($this->requiredWritableDirs as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            $writable = is_writable($fullPath);
            $checks['directories']['items'][] = [
                'name' => $dir,
                'status' => $writable,
                'current' => $writable ? '可写' : '不可写'
            ];
        }
        
        return $checks;
    }
    
    /**
     * 验证输入参数
     */
    private function validateInput(array $data): ?array {
        $required = [
            'db_host', 'db_port', 'db_name', 'db_user', 'db_pass',
            'admin_username', 'admin_password', 'admin_email',
            'site_url', 'site_name'
        ];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => "缺少必填字段：{$field}"
                ]);
                return null;
            }
        }
        
        // 验证邮箱格式
        if (!filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $this->jsonResponse([
                'success' => false,
                'message' => '管理员邮箱格式不正确'
            ]);
            return null;
        }
        
        // 验证URL格式
        if (!filter_var($data['site_url'], FILTER_VALIDATE_URL)) {
            $this->jsonResponse([
                'success' => false,
                'message' => '站点URL格式不正确'
            ]);
            return null;
        }
        
        return [
            'db' => [
                'host' => $data['db_host'],
                'port' => $data['db_port'],
                'database' => $data['db_name'],
                'username' => $data['db_user'],
                'password' => $data['db_pass']
            ],
            'site' => [
                'url' => rtrim($data['site_url'], '/'),
                'name' => $data['site_name']
            ],
            'app' => [
                'env' => $data['app_env'] ?? 'production',
                'debug' => ($data['app_debug'] ?? 'false') === 'true',
                'timezone' => $data['app_timezone'] ?? 'UTC'
            ]
        ];
    }
    
    /**
     * 测试数据库连接
     */
    private function testDatabaseConnection(array $config): bool {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
            $pdo = new \PDO($dsn, $config['username'], $config['password']);
            
            // 检查数据库是否存在
            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$config['database']}'");
            if (!$stmt->fetch()) {
                // 创建数据库
                $pdo->exec("CREATE DATABASE `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
            
            return true;
            
        } catch (\PDOException $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '数据库连接失败：' . $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 保存配置文件
     */
    private function saveConfig(array $config): bool {
        try {
            // 数据库配置
            $dbConfigFile = $this->configPath . '/database.php';
            $dbContent = "<?php\nreturn " . var_export($config['db'], true) . ";\n";
            
            // 应用配置
            $appConfigFile = $this->configPath . '/app.php';
            $appContent = "<?php\nreturn " . var_export(array_merge($config['app'], $config['site']), true) . ";\n";
            
            // 创建.env文件
            $envFile = $this->basePath . '/.env';
            $envContent = $this->generateEnvContent($config);
            
            if (
                file_put_contents($dbConfigFile, $dbContent) === false ||
                file_put_contents($appConfigFile, $appContent) === false ||
                file_put_contents($envFile, $envContent) === false
            ) {
                throw new \Exception('无法写入配置文件');
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '保存配置文件失败：' . $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 生成.env文件内容
     */
    private function generateEnvContent(array $config): string {
        return <<<EOT
# Database Configuration
DB_HOST={$config['db']['host']}
DB_PORT={$config['db']['port']}
DB_DATABASE={$config['db']['database']}
DB_USERNAME={$config['db']['username']}
DB_PASSWORD={$config['db']['password']}

# Application Settings
APP_NAME={$config['site']['name']}
APP_URL={$config['site']['url']}
APP_ENV={$config['app']['env']}
APP_DEBUG={$config['app']['debug'] ? 'true' : 'false'}
APP_TIMEZONE={$config['app']['timezone']}

# Security
JWT_SECRET={$this->generateJwtSecret()}
EOT;
    }
    
    /**
     * 生成JWT密钥
     */
    private function generateJwtSecret(): string {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * 创建数据库表
     */
    private function createDatabaseTables(): bool {
        try {
            $db = Database::getInstance();
            
            // 执行SQL文件
            $sqlFiles = [
                '/sql/migrations/20240328_create_notification_tables.sql',
                '/sql/migrations/20240329_create_notification_queue_tables.sql',
                '/sql/migrations/20240329_create_ad_tables.sql'
            ];
            
            foreach ($sqlFiles as $file) {
                $sql = file_get_contents($this->basePath . $file);
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        $db->execute($statement);
                    }
                }
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '创建数据库表失败：' . $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 初始化基础数据
     */
    private function initializeBaseData(): bool {
        try {
            $db = Database::getInstance();
            
            // 初始化通知渠道
            $sql = "INSERT INTO notification_channels (channel_type, name, is_enabled, config) VALUES
                ('in_app', '站内信', TRUE, '{\"queue\": \"notifications_in_app\"}'),
                ('email', '邮件通知', FALSE, '{\"smtp_host\": \"\", \"smtp_port\": \"\", \"smtp_user\": \"\", \"smtp_pass\": \"\", \"from_email\": \"\", \"from_name\": \"\", \"queue\": \"notifications_email\"}'),
                ('sms', '短信通知', FALSE, '{\"api_url\": \"\", \"api_key\": \"\", \"api_secret\": \"\", \"queue\": \"notifications_sms\"}')";
            
            $db->execute($sql);
            return true;
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '初始化基础数据失败：' . $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 创建管理员账号
     */
    private function createAdminAccount(string $username, string $password, string $email): bool {
        try {
            $db = Database::getInstance();
            
            // 创建管理员账号
            $sql = "INSERT INTO users (username, email, password, role, status, created_at) 
                   VALUES (?, ?, ?, 'admin', 'active', NOW())";
            $db->execute($sql, [
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => '创建管理员账号失败：' . $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 创建安装锁定文件
     */
    private function createInstallLock(): void {
        $lockFile = $this->basePath . '/install.lock';
        file_put_contents($lockFile, date('Y-m-d H:i:s'));
    }
    
    /**
     * 检查内存限制
     */
    private function checkMemoryLimit(): bool {
        $limit = ini_get('memory_limit');
        $limit = $this->normalizeMemoryLimit($limit);
        return $limit >= 128 * 1024 * 1024; // 128M
    }
    
    /**
     * 转换内存限制为字节数
     */
    private function normalizeMemoryLimit(string $limit): int {
        $limit = strtolower(trim($limit));
        $last = strtolower($limit[strlen($limit)-1]);
        $value = (int)$limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * 获取应用根目录
     */
    private function getBasePath(): string {
        return dirname(dirname(__DIR__));
    }
} 