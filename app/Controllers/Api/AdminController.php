<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\AdModel;
use App\Models\TransactionModel;

class AdminController extends BaseController
{
    /**
     * 确保用户是管理员
     */
    protected function ensureAdmin()
    {
        // 确保会话已启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查用户是否已登录且是管理员
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this resource'
            ]);
            exit;
        }
    }
    
    /**
     * 获取管理面板统计数据
     */
    public function getStats()
    {
        // 确保用户已登录且是管理员
        $this->ensureAdmin();
        
        // 获取数据库配置
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 获取广告主数量
        $advertiserCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'advertiser'")->fetchColumn();
        
        // 获取发布者数量
        $publisherCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'publisher'")->fetchColumn();
        
        // 获取活跃广告数量
        $activeAds = $pdo->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn();
        
        // 获取24小时内的收入
        $revenue24h = $pdo->query("
            SELECT COALESCE(SUM(cost), 0) 
            FROM ad_views 
            WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetchColumn();
        
        return [
            'advertiser_count' => (int)$advertiserCount,
            'publisher_count' => (int)$publisherCount,
            'active_ads' => (int)$activeAds,
            'revenue_24h' => (float)$revenue24h
        ];
    }
    
    /**
     * 获取用户列表
     */
    public function getUsers()
    {
        // 确保用户已登录且是管理员
        $this->ensureAdmin();
        
        // 获取数据库配置
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 获取用户列表
        $stmt = $pdo->query("
            SELECT 
                id,
                username,
                email,
                role,
                balance,
                created_at
            FROM users 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取单个用户数据
     */
    public function getUser($id)
    {
        // 设置响应头并确保在输出前没有任何内容
        ob_clean(); // 清除之前可能的输出缓冲
        header('Content-Type: application/json');
        
        // 在实际环境中，应从数据库获取用户数据
        // 这里返回模拟数据
        $user = [
            'id' => $id,
            'username' => 'user' . $id,
            'email' => 'user' . $id . '@example.com',
            'role' => $id == 1 ? 'admin' : ($id % 2 == 0 ? 'advertiser' : 'publisher'),
            'balance' => rand(0, 1000) . '.00',
            'created_at' => '2023-' . rand(1, 12) . '-' . rand(1, 28) . ' ' . rand(0, 23) . ':' . rand(0, 59) . ':00'
        ];
        
        echo json_encode($user);
        exit;
    }
    
    /**
     * 获取所有用户（带分页）
     */
    public function getAllUsers()
    {
        $this->ensureAdmin();
        
        // 获取数据库配置
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 获取查询参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $role = isset($_GET['role']) ? $_GET['role'] : '';
        
        // 构建查询
        $where = [];
        $params = [];
        
        if (!empty($search)) {
            $where[] = "(username LIKE ? OR email LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        if (!empty($role)) {
            $where[] = "role = ?";
            $params[] = $role;
        }
        
        $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
        
        // 获取总数
        $countQuery = "SELECT COUNT(*) FROM users $whereClause";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // 计算偏移量
        $offset = ($page - 1) * $limit;
        
        // 获取用户列表
        $query = "
            SELECT 
                id,
                username,
                email,
                role,
                balance,
                created_at
            FROM users 
            $whereClause
            ORDER BY created_at DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // 计算总页数
        $pageCount = ceil($total / $limit);
        
        // 返回 JSON 响应
        header('Content-Type: application/json');
        echo json_encode([
            'users' => $users,
            'pager' => [
                'currentPage' => $page,
                'pageCount' => $pageCount,
                'total' => $total
            ]
        ]);
        exit;
    }

    /**
     * 获取错误统计数据
     */
    public function errorStats()
    {
        $this->ensureAdmin();
        
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 获取总错误数
        $totalErrors = $pdo->query("SELECT COUNT(*) FROM errors")->fetchColumn();
        
        // 获取未解决的错误数
        $unresolvedErrors = $pdo->query("SELECT COUNT(*) FROM errors WHERE status IN ('new', 'in_progress')")->fetchColumn();
        
        // 获取24小时内的错误数
        $errors24h = $pdo->query("SELECT COUNT(*) FROM errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
        
        // 获取一周内的错误数
        $errors7d = $pdo->query("SELECT COUNT(*) FROM errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        
        return [
            'total' => (int)$totalErrors,
            'unresolved' => (int)$unresolvedErrors,
            'last_24h' => (int)$errors24h,
            'last_7d' => (int)$errors7d
        ];
    }

    /**
     * 获取错误类型统计
     */
    public function errorTypes()
    {
        $this->ensureAdmin();
        
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->query("
            SELECT type, COUNT(*) as count
            FROM errors
            GROUP BY type
            ORDER BY count DESC
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取错误列表
     */
    public function errors()
    {
        $this->ensureAdmin();
        
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 获取查询参数
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // 构建查询
        $where = [];
        $params = [];
        
        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        
        if ($type) {
            $where[] = "type = ?";
            $params[] = $type;
        }
        
        if ($search) {
            $where[] = "(message LIKE ? OR file LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
        
        // 获取总数
        $countQuery = "SELECT COUNT(*) FROM errors $whereClause";
        $stmt = $pdo->prepare($countQuery);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
        
        // 获取错误列表
        $query = "
            SELECT 
                id,
                type,
                message,
                file,
                line,
                status,
                created_at
            FROM errors 
            $whereClause
            ORDER BY created_at DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $errors = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'errors' => $errors,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 获取系统设置
     */
    public function settings()
    {
        $this->ensureAdmin();
        
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 从数据库获取设置
        $stmt = $pdo->query("SELECT * FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        // 设置默认值
        $defaults = [
            'siteName' => 'VertoAD',
            'siteDescription' => '广告投放系统',
            'adminEmail' => '',
            'minBidAmount' => '0.01',
            'maxAdsPerPage' => '10',
            'adApprovalRequired' => 'true',
            'maxLoginAttempts' => '5',
            'sessionTimeout' => '120',
            'enableTwoFactor' => 'false'
        ];
        
        // 合并默认值和数据库值
        foreach ($defaults as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
            }
        }
        
        // 转换布尔值
        $settings['adApprovalRequired'] = $settings['adApprovalRequired'] === 'true';
        $settings['enableTwoFactor'] = $settings['enableTwoFactor'] === 'true';
        
        return $settings;
    }
    
    /**
     * 保存系统设置
     */
    public function saveSettings()
    {
        $this->ensureAdmin();
        
        // 获取POST数据
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            return ['error' => 'Invalid input data'];
        }
        
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        try {
            $pdo->beginTransaction();
            
            // 准备更新语句
            $stmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            
            // 更新每个设置
            foreach ($input as $key => $value) {
                // 布尔值转换为字符串
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $stmt->execute([$key, (string)$value]);
            }
            
            $pdo->commit();
            return ['success' => true, 'message' => '设置已保存'];
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            return ['error' => '保存设置失败', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取系统信息
     */
    public function systemInfo()
    {
        $this->ensureAdmin();
        
        $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
        $pdo = new \PDO(
            "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4",
            $dbConfig['username'],
            $dbConfig['password'],
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        
        // 获取MySQL版本
        $mysqlVersion = $pdo->query('SELECT VERSION()')->fetchColumn();
        
        // 获取安装信息
        $installLockFile = dirname(dirname(dirname(__DIR__))) . '/install.lock';
        $installInfo = [];
        if (file_exists($installLockFile)) {
            $installInfo = json_decode(file_get_contents($installLockFile), true);
        }
        
        return [
            'version' => $installInfo['version'] ?? '1.0.0',
            'php_version' => PHP_VERSION,
            'mysql_version' => $mysqlVersion,
            'install_time' => $installInfo['installed_at'] ?? date('Y-m-d H:i:s'),
            'server_info' => $installInfo['server_info'] ?? $_SERVER['SERVER_SOFTWARE']
        ];
    }
} 