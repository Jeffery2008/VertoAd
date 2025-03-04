<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;

class AuthController extends BaseController
{
    /**
     * 处理用户登录请求
     */
    public function login()
    {
        // 清除任何之前的输出缓冲
        if (ob_get_level()) {
            ob_clean();
        }
        
        // 设置内容类型为JSON
        header('Content-Type: application/json');
        
        // 启动会话(如果尚未启动)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 获取并解析JSON输入
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // 如果JSON解析失败，尝试使用POST数据
        if (json_last_error() !== JSON_ERROR_NONE) {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
        } else {
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';
        }
        
        // 记录输入数据到日志（仅用于调试）
        error_log('Login attempt: Username=' . $username . ', Password=' . substr($password, 0, 3) . '***');
        
        try {
            // 尝试从数据库验证用户
            $dbConfig = require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';
            
            if (is_array($dbConfig)) {
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset=utf8mb4";
                $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]);
                
                // 查询用户
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    // 登录成功 - 从数据库验证
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = ($user['role'] === 'admin');
                    $_SESSION['role'] = $user['role'];
                    
                    echo json_encode([
                        'success' => true,
                        'message' => '登录成功',
                        'user' => [
                            'id' => $user['id'],
                            'username' => $user['username'],
                            'is_admin' => ($user['role'] === 'admin')
                        ]
                    ]);
                    exit;
                }
            }
        } catch (\Exception $e) {
            error_log('Database login error: ' . $e->getMessage());
            // 返回错误信息而不是尝试硬编码验证
            echo json_encode([
                'success' => false,
                'message' => '数据库连接错误，请检查配置或联系管理员'
            ]);
            exit;
        }
        
        // 登录失败
        echo json_encode([
            'success' => false,
            'message' => '用户名或密码错误'
        ]);
        exit;
    }
    
    /**
     * 检查用户登录状态
     * 返回用户是否已登录及其权限信息
     */
    public function checkStatus()
    {
        // 清除任何之前的输出缓冲
        if (ob_get_level()) {
            ob_clean();
        }
        
        // 启动会话(如果尚未启动)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查用户是否已登录
        $isLoggedIn = isset($_SESSION['user_id']);
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
        
        // 返回数据而不是直接输出
        return [
            'isLoggedIn' => $isLoggedIn,
            'isAdmin' => $isAdmin,
            'userId' => $isLoggedIn ? $_SESSION['user_id'] : null,
            'username' => $isLoggedIn ? ($_SESSION['username'] ?? null) : null
        ];
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