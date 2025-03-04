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
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getAllUsers()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $userModel = new UserModel();
        
        $page = $this->request->getGet('page') ?? 1;
        $limit = $this->request->getGet('limit') ?? 20;
        $search = $this->request->getGet('search') ?? '';
        $role = $this->request->getGet('role') ?? '';
        
        // 基本查询
        $userModel->select('id, username, email, role, balance, created_at, status');
        
        // 搜索条件
        if (!empty($search)) {
            $userModel->groupStart()
                     ->like('username', $search)
                     ->orLike('email', $search)
                     ->groupEnd();
        }
        
        // 角色筛选
        if (!empty($role)) {
            $userModel->where('role', $role);
        }
        
        // 分页
        $users = $userModel->orderBy('id', 'DESC')
                          ->paginate($limit, 'default', $page);
        
        $pager = $userModel->pager;
        
        return $this->response->setJSON([
            'users' => $users,
            'pager' => [
                'currentPage' => $page,
                'pageCount' => $pager->getPageCount(),
                'total' => $pager->getTotal()
            ]
        ]);
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
} 