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
     * 获取站点统计数据
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getStats()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $userModel = new UserModel();
        $adModel = new AdModel();
        $transactionModel = new TransactionModel();
        
        // 获取广告主数量
        $advertiserCount = $userModel->where('role', 'advertiser')->countAllResults();
        
        // 获取发布者数量
        $publisherCount = $userModel->where('role', 'publisher')->countAllResults();
        
        // 获取活跃广告数量
        $activeAds = $adModel->where('status', 'active')->countAllResults();
        
        // 获取24小时内的收入
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $revenue = $transactionModel->where('type', 'income')
                                   ->where('created_at >=', $oneDayAgo)
                                   ->selectSum('amount')
                                   ->get()
                                   ->getRow();
        
        $revenue24h = $revenue ? $revenue->amount : 0;
        
        return $this->response->setJSON([
            'advertiser_count' => $advertiserCount,
            'publisher_count' => $publisherCount,
            'active_ads' => $activeAds,
            'revenue_24h' => $revenue24h
        ]);
    }
    
    /**
     * 获取系统用户列表
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getUsers()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $userModel = new UserModel();
        
        // 获取前10个用户
        $users = $userModel->select('id, username, email, role, balance, created_at')
                          ->orderBy('id', 'DESC')
                          ->limit(10)
                          ->find();
        
        return $this->response->setJSON($users);
    }
    
    /**
     * 获取单个用户详情
     * 
     * @param int $id 用户ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function getUser($id)
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $userModel = new UserModel();
        $user = $userModel->select('id, username, email, role, balance, created_at, last_login_at, status')
                         ->find($id);
        
        if (!$user) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Not Found',
                'message' => 'User not found'
            ]);
        }
        
        // 获取用户的广告或发布位信息
        if ($user['role'] === 'advertiser') {
            $adModel = new AdModel();
            $user['ads'] = $adModel->where('user_id', $id)
                                  ->orderBy('id', 'DESC')
                                  ->limit(5)
                                  ->find();
        }
        
        return $this->response->setJSON($user);
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