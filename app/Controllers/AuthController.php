<?php

namespace App\Controllers;

use App\Models\User;
use App\Core\Request;
use App\Core\Response;

class AuthController
{
    protected $user;
    protected $response;

    public function __construct() {
        $this->user = new User();
        $this->response = new Response();
    }

    public function login(Request $request)
    {
        // 只有在会话尚未启动的情况下才启动会话
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 如果已经登录，直接重定向到对应页面
        if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
            if ($_SESSION['user_role'] === 'admin') {
                if ($request->isAjax() || $request->getUri() === '/api/auth/login') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => '/admin/dashboard']);
                    exit;
                }
                header('Location: /admin/dashboard');
                exit;
            }
        }

        // GET 请求显示登录页面
        if ($request->getMethod() === 'GET') {
            if ($request->getUri() === '/api/auth/login') {
                header('HTTP/1.1 405 Method Not Allowed');
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                exit;
            }
            
            // 这里不再需要，因为路由已经被修改为直接提供静态文件
            return $this->response->renderView('admin/login');
        }

        // POST 请求处理登录
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // 记录调试信息
        error_log('Login attempt: ' . json_encode(['username' => $username, 'password_length' => strlen($password)]));
        error_log('Request data: ' . json_encode($data));
        error_log('Raw input: ' . file_get_contents('php://input'));

        // 验证输入
        if (empty($username) || empty($password)) {
            if ($request->isAjax() || $request->getUri() === '/api/auth/login') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Please enter both username and password'
                ]);
                exit;
            }
            return $this->response->renderView('admin/login', ['error' => 'Please enter both username and password']);
        }

        $user = $this->user->findByUsername($username);
        
        // 调试日志记录
        error_log('User found: ' . ($user ? 'Yes' : 'No'));
        if ($user) {
            error_log('User data: ' . json_encode($user));
            error_log('Password verification: ' . (password_verify($password, $user['password_hash']) ? 'Success' : 'Failed'));
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            if ($user['role'] === 'admin') {
                if ($request->isAjax() || $request->getUri() === '/api/auth/login') {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'redirect' => '/admin/dashboard'
                    ]);
                    exit;
                }
                header('Location: /admin/dashboard');
                exit;
            }
        }

        // 登录失败
        if ($request->isAjax() || $request->getUri() === '/api/auth/login') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password'
            ]);
            exit;
        }
        return $this->response->renderView('admin/login', ['error' => 'Invalid username or password']);
    }

    public function register(Request $request)
    {
        if ($request->getMethod() === 'GET') {
            return $this->response->renderView('auth/register');
        }

        $data = $request->getBody();
        $role = $data['role'];
        $username = $data['username'];
        $email = $data['email'];
        $password = $data['password'];
        $passwordConfirmation = $data['password_confirmation'];

        // 验证输入
        $errors = [];
        if (empty($role) || !in_array($role, ['advertiser', 'publisher'])) {
            $errors[] = 'Invalid role';
        }
        if (empty($username) || strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if (empty($password) || strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        if ($password !== $passwordConfirmation) {
            $errors[] = 'Passwords do not match';
        }

        // 检查用户名和邮箱是否已存在
        if ($this->user->findByUsername($username)) {
            $errors[] = 'Username already exists';
        }
        if ($this->user->findByEmail($email)) {
            $errors[] = 'Email already exists';
        }

        if (!empty($errors)) {
            return $this->response->renderView('auth/register', ['errors' => $errors]);
        }

        // 创建用户
        if ($this->user->create($role, $username, $email, $password)) {
            // 注册成功，重定向到登录页面
            header('Location: /admin/login');
            exit;
        } else {
            // 注册失败
            $error = 'Registration failed';
            return $this->response->renderView('auth/register', ['error' => $error]);
        }
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        header('Location: /admin/login');
        exit;
    }
} 