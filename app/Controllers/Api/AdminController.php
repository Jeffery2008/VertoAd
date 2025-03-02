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
    private function ensureAdmin()
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
     * 获取管理员面板统计数据
     */
    public function getStats()
    {
        // 设置响应头并确保在输出前没有任何内容
        ob_clean(); // 清除之前可能的输出缓冲
        header('Content-Type: application/json');
        
        // 在实际环境中，这些数据应从数据库获取
        // 这里提供模拟数据用于开发测试
        $stats = [
            'advertiser_count' => 24,
            'publisher_count' => 18,
            'active_ads' => 42,
            'revenue_24h' => 1250.75
        ];
        
        // 确保输出前没有任何其他输出
        echo json_encode($stats);
        exit;
    }
    
    /**
     * 获取用户列表
     */
    public function getUsers()
    {
        // 设置响应头并确保在输出前没有任何内容
        ob_clean(); // 清除之前可能的输出缓冲
        header('Content-Type: application/json');
        
        // 在实际环境中，这些数据应从数据库获取
        // 这里提供模拟数据用于开发测试
        $users = [
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@vertoad.com',
                'role' => 'admin',
                'balance' => '0.00',
                'created_at' => '2023-01-01 00:00:00'
            ],
            [
                'id' => 2,
                'username' => 'advertiser1',
                'email' => 'advertiser1@example.com',
                'role' => 'advertiser',
                'balance' => '500.00',
                'created_at' => '2023-01-15 10:30:00'
            ],
            [
                'id' => 3,
                'username' => 'publisher1',
                'email' => 'publisher1@example.com',
                'role' => 'publisher',
                'balance' => '250.00',
                'created_at' => '2023-02-01 14:45:00'
            ]
        ];
        
        // 确保输出前没有任何其他输出
        echo json_encode($users);
        exit;
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