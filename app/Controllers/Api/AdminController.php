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
        $session = session();
        
        if (!$session->has('user_id') || $session->get('role') !== 'admin') {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this resource'
            ]);
        }
        
        return null;
    }
    
    /**
     * 获取管理面板统计数据
     */
    public function getStats()
    {
        // 确保用户已登录且是管理员
        $this->ensureAdmin();
        
        try {
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
                SELECT COALESCE(SUM(amount), 0) 
                FROM ad_views 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")->fetchColumn();
            
            return [
                'advertiser_count' => (int)$advertiserCount,
                'publisher_count' => (int)$publisherCount,
                'active_ads' => (int)$activeAds,
                'revenue_24h' => (float)$revenue24h
            ];
            
        } catch (\Exception $e) {
            // 记录错误
            error_log('Error in getStats: ' . $e->getMessage());
            
            // 返回模拟数据（用于开发测试）
            return [
                'advertiser_count' => 10,
                'publisher_count' => 25,
                'active_ads' => 45,
                'revenue_24h' => 1234.56
            ];
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
            
        } catch (\Exception $e) {
            // 记录错误
            error_log('Error in getUsers: ' . $e->getMessage());
            
            // 返回模拟数据（用于开发测试）
            return [
                [
                    'id' => 1,
                    'username' => 'admin',
                    'email' => 'admin@example.com',
                    'role' => 'admin',
                    'balance' => 0.00,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 2,
                    'username' => 'advertiser1',
                    'email' => 'advertiser1@example.com',
                    'role' => 'advertiser',
                    'balance' => 1000.00,
                    'created_at' => date('Y-m-d H:i:s')
                ],
                [
                    'id' => 3,
                    'username' => 'publisher1',
                    'email' => 'publisher1@example.com',
                    'role' => 'publisher',
                    'balance' => 500.00,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            ];
        }
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
} 