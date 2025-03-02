<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;

class AuthController extends BaseController
{
    /**
     * 检查用户登录状态
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function checkStatus()
    {
        $session = session();
        $isLoggedIn = $session->has('user_id');
        $role = $isLoggedIn ? $session->get('role') : null;
        
        // 设置响应头
        $this->response->setHeader('Content-Type', 'application/json');
        
        return $this->response->setJSON([
            'isLoggedIn' => $isLoggedIn,
            'role' => $role,
            'userId' => $isLoggedIn ? $session->get('user_id') : null
        ]);
    }
    
    /**
     * 用户登出
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function logout()
    {
        $session = session();
        $session->destroy();
        
        $this->response->setHeader('Content-Type', 'application/json');
        
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
} 