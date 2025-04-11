<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
require_once dirname(dirname(__DIR__)) . '/Core/Database.php';
require_once dirname(dirname(__DIR__)) . '/Models/UserModel.php';
require_once dirname(dirname(__DIR__)) . '/Models/AdModel.php';
require_once dirname(dirname(__DIR__)) . '/Models/ZoneModel.php';

use App\Core\Database;
use App\Models\UserModel;
use App\Models\AdModel;
use App\Models\ZoneModel;
use App\Models\TransactionModel;

class AdminController extends BaseController
{
    protected $userModel;
    protected $adModel;
    protected $zoneModel;
    
    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->adModel = new AdModel();
        $this->zoneModel = new ZoneModel();
    }

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
        
        try {
            // 使用已有的数据库连接
            $db = Database::getInstance();
            
            // 获取广告主数量
            $advertiserCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'advertiser'")->fetchColumn();
            
            // 获取发布者数量
            $publisherCount = $db->query("SELECT COUNT(*) FROM users WHERE role = 'publisher'")->fetchColumn();
            
            // 获取活跃广告数量
            $activeAds = $db->query("SELECT COUNT(*) FROM ads WHERE status = 'active'")->fetchColumn();
            
            // 获取24小时内的收入
            $revenue24h = $db->query("
                SELECT COALESCE(SUM(cost), 0) 
                FROM ad_views 
                WHERE viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->fetchColumn();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'advertiser_count' => (int)$advertiserCount,
                    'publisher_count' => (int)$publisherCount,
                    'active_ads' => (int)$activeAds,
                    'revenue_24h' => (float)$revenue24h
                ]
            ]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * 获取用户列表
     */
    public function getUsers()
    {
        // 确保用户已登录且是管理员
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            // 获取用户列表
            $users = $db->query("
                SELECT 
                    id,
                    username,
                    email,
                    role,
                    COALESCE(balance, 0) as balance,
                    created_at
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 10
            ")->fetchAll();
            
            // 处理每个用户的数据
            $processedUsers = array_map(function($user) {
                return [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'balance' => number_format((float)$user['balance'], 2),
                    'created_at' => $user['created_at']
                ];
            }, $users);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'users' => $processedUsers,
                'total' => count($processedUsers)
            ]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * 获取单个用户数据
     */
    public function getUser($id)
    {
        // 确保用户已登录且是管理员
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            // 获取用户数据
            $user = $db->query("
                SELECT 
                    id,
                    username,
                    email,
                    role,
                    balance,
                    created_at
                FROM users 
                WHERE id = ?
            ", [$id])->fetch();
            
            if (!$user) {
                header('Content-Type: application/json');
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'User not found'
                ]);
                exit;
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $user
            ]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * 获取所有用户（带分页）
     */
    public function getAllUsers()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            // 获取查询参数
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
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
            $total = $db->query(
                "SELECT COUNT(*) FROM users $whereClause",
                $params
            )->fetchColumn();
            
            // 计算偏移量
            $offset = ($page - 1) * $limit;
            
            // 获取用户列表
            $users = $db->query(
                "SELECT 
                    id,
                    username,
                    email,
                    role,
                    balance,
                    created_at
                FROM users 
                $whereClause
                ORDER BY created_at DESC 
                LIMIT ?, ?",
                array_merge($params, [$offset, $limit])
            )->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'users' => $users,
                'pager' => [
                    'currentPage' => $page,
                    'pageCount' => ceil($total / $limit),
                    'total' => $total,
                    'perPage' => $limit
                ]
            ]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取错误统计数据
     */
    public function errorStats()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            // 获取总错误数
            $totalErrors = $db->query("SELECT COUNT(*) FROM errors")->fetchColumn();
            
            // 获取未解决的错误数
            $unresolvedErrors = $db->query("SELECT COUNT(*) FROM errors WHERE status IN ('new', 'in_progress')")->fetchColumn();
            
            // 获取24小时内的错误数
            $errors24h = $db->query("SELECT COUNT(*) FROM errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
            
            // 获取一周内的错误数
            $errors7d = $db->query("SELECT COUNT(*) FROM errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => (int)$totalErrors,
                    'unresolved' => (int)$unresolvedErrors,
                    'last_24h' => (int)$errors24h,
                    'last_7d' => (int)$errors7d
                ]
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取错误类型统计
     */
    public function errorTypes()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            $types = $db->query("
                SELECT type, COUNT(*) as count
                FROM errors
                GROUP BY type
                ORDER BY count DESC
            ")->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $types
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取错误列表
     */
    public function errors()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
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
            $total = $db->query(
                "SELECT COUNT(*) FROM errors $whereClause",
                $params
            )->fetchColumn();
            
            // 获取错误列表
            $errors = $db->query(
                "SELECT 
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
                LIMIT ?, ?",
                array_merge($params, [$offset, $limit])
            )->fetchAll();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'errors' => $errors,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($total / $limit),
                        'total' => $total,
                        'per_page' => $limit
                    ]
                ]
            ]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取系统设置
     */
    public function settings()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            // 从数据库获取设置
            $result = $db->query("SELECT * FROM settings");
            $settings = [];
            while ($row = $result->fetch()) {
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
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
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
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Invalid input data'
            ]);
            exit;
        }
        
        try {
            $db = Database::getInstance();
            $db->beginTransaction();
            
            // 准备更新语句
            $stmt = $db->prepare("
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
            
            $db->commit();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => '设置已保存'
            ]);
            exit;
        } catch (\Exception $e) {
            $db->rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => '保存设置失败',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
    
    /**
     * 获取系统信息
     */
    public function systemInfo()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            // 获取MySQL版本
            $mysqlVersion = $db->query('SELECT VERSION()')->fetchColumn();
            
            // 获取安装信息
            $installLockFile = dirname(dirname(dirname(__DIR__))) . '/install.lock';
            $installInfo = [];
            if (file_exists($installLockFile)) {
                $installInfo = json_decode(file_get_contents($installLockFile), true);
            }
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'version' => $installInfo['version'] ?? '1.0.0',
                    'php_version' => PHP_VERSION,
                    'mysql_version' => $mysqlVersion,
                    'install_time' => $installInfo['installed_at'] ?? date('Y-m-d H:i:s'),
                    'server_info' => $installInfo['server_info'] ?? $_SERVER['SERVER_SOFTWARE']
                ]
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取所有发布商列表
     */
    public function publishers()
    {
        $this->ensureAdmin();

        try {
            $publishers = $this->userModel->where('role', 'publisher')->findAll();
            
            // 处理每个发布商的数据
            $result = array_map(function($publisher) {
                return [
                    'id' => $publisher['id'],
                    'username' => $publisher['username'],
                    'email' => $publisher['email'],
                    'status' => $publisher['status'],
                    'created_at' => $publisher['created_at'],
                    'last_login' => $publisher['last_login'],
                    'zone_count' => $this->zoneModel->where('publisher_id', $publisher['id'])->countAllResults()
                ];
            }, $publishers);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'publishers' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => '获取发布商列表失败',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取广告位列表
     */
    public function zones()
    {
        $this->ensureAdmin();

        try {
            // 获取查询参数
            $status = $_GET['status'] ?? '';
            $publisher = $_GET['publisher'] ?? '';
            $type = $_GET['type'] ?? '';
            $search = $_GET['search'] ?? '';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;

            // 构建查询
            $query = $this->zoneModel;

            if ($status) {
                $query = $query->where('status', $status);
            }
            if ($publisher) {
                $query = $query->where('publisher_id', $publisher);
            }
            if ($type) {
                $query = $query->where('type', $type);
            }
            if ($search) {
                $query = $query->groupStart()
                    ->like('name', "%$search%")
                    ->orLike('description', "%$search%")
                    ->groupEnd();
            }

            // 获取总记录数
            $total = $query->countAllResults(false);

            // 获取分页数据
            $zones = $query->limit($limit, $offset)->findAll();

            // 处理每个广告位的数据
            $result = array_map(function($zone) {
                $publisher = $this->userModel->find($zone['publisher_id']);
                return [
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'type' => $zone['type'],
                    'size' => $zone['size'],
                    'status' => $zone['status'],
                    'publisher' => [
                        'id' => $publisher['id'],
                        'username' => $publisher['username']
                    ],
                    'created_at' => $zone['created_at'],
                    'updated_at' => $zone['updated_at'],
                    'description' => $zone['description'],
                    'website_url' => $zone['website_url'],
                    'ad_count' => $zone['ad_count'] ?? 0
                ];
            }, $zones);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'zones' => $result,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => '获取广告位列表失败',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取广告位统计数据
     */
    public function zoneStats()
    {
        $this->ensureAdmin();

        try {
            $db = Database::getInstance();
            
            // 获取总广告位数量
            $totalZones = $this->zoneModel->countAllResults();
            
            // 获取活跃广告位数量
            $activeZones = $this->zoneModel->where('status', 'active')->countAllResults();
            
            // 获取总展示量
            $totalImpressions = $db->query("
                SELECT COALESCE(SUM(views), 0) as total 
                FROM ad_views 
                WHERE zone_id IS NOT NULL"
            )->fetchColumn();
            
            // 获取24小时内的展示量
            $dailyImpressions = $db->query("
                SELECT COALESCE(SUM(views), 0) as daily 
                FROM ad_views 
                WHERE zone_id IS NOT NULL 
                AND viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            )->fetchColumn();

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'total_zones' => (int)$totalZones,
                'active_zones' => (int)$activeZones,
                'total_impressions' => (int)$totalImpressions,
                'daily_impressions' => (int)$dailyImpressions
            ]);
            exit;
            
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => '获取统计数据失败',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取每日错误统计
     */
    public function dailyErrors()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            $result = $db->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM errors
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ")->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取错误类型分布
     */
    public function errorsByType()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            $result = $db->query("
                SELECT 
                    type,
                    COUNT(*) as count
                FROM errors
                GROUP BY type
                ORDER BY count DESC
            ")->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取每小时错误统计
     */
    public function hourlyErrors()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            $result = $db->query("
                SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM errors
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(created_at)
                ORDER BY hour ASC
            ")->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取最近错误
     */
    public function recentErrors()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            $result = $db->query("
                SELECT 
                    id,
                    type,
                    message,
                    file,
                    line,
                    status,
                    created_at
                FROM errors
                ORDER BY created_at DESC
                LIMIT 10
            ")->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * 获取常见错误消息
     */
    public function commonMessages()
    {
        $this->ensureAdmin();
        
        try {
            $db = Database::getInstance();
            
            $result = $db->query("
                SELECT 
                    message,
                    COUNT(*) as count
                FROM errors
                GROUP BY message
                ORDER BY count DESC
                LIMIT 10
            ")->fetchAll();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }
} 