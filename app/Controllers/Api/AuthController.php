<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;

class AuthController extends BaseController
{
    /**
     * 检查用户登录状态
     * 返回用户是否已登录及其权限信息
     */
    public function checkStatus()
    {
        // 启动会话(如果尚未启动)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查用户是否已登录
        $isLoggedIn = isset($_SESSION['user_id']);
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
        
        // 设置正确的头信息
        header('Content-Type: application/json');
        
        // 返回JSON响应
        echo json_encode([
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
            'username' => $isLoggedIn ? ($_SESSION['username'] ?? null) : null
        ]);
        exit;
    }
    
    /**
     * 处理用户登出请求
     */
    public function logout()
    {
        // 启动会话(如果尚未启动)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 清除所有会话数据
        $_SESSION = [];
        
        // 如果存在会话Cookie，销毁它
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // 销毁会话
        session_destroy();
        
        // 返回成功消息
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '已成功登出']);
        exit;
    }
} 