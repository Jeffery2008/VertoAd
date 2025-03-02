<?php
namespace App\Controllers;

/**
 * BaseController
 * 所有控制器的基类
 */
class BaseController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        // 通用的初始化代码
    }
    
    /**
     * 返回JSON响应
     */
    protected function json($data, $statusCode = 200)
    {
        // 设置HTTP状态码
        http_response_code($statusCode);
        
        // 设置内容类型
        header('Content-Type: application/json');
        
        // 输出JSON
        echo json_encode($data);
        exit;
    }
    
    /**
     * 检查用户是否为管理员
     */
    protected function isAdmin()
    {
        // 启动会话
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
    
    /**
     * 确保用户已登录
     */
    protected function ensureLoggedIn()
    {
        // 启动会话
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            $this->json([
                'error' => 'Unauthorized',
                'message' => '请先登录'
            ], 401);
            return false;
        }
        
        return true;
    }
    
    /**
     * 确保用户是管理员
     */
    protected function ensureAdmin()
    {
        // 先确保已登录
        if (!$this->ensureLoggedIn()) {
            return false;
        }
        
        // 再检查管理员权限
        if (!$this->isAdmin()) {
            $this->json([
                'error' => 'Forbidden',
                'message' => '您没有权限访问该资源'
            ], 403);
            return false;
        }
        
        return true;
    }
} 