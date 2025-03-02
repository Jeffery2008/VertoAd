<?php

namespace App\Core;

class Controller
{
    protected $user;
    protected $session;

    public function __construct()
    {
        // 在测试环境中，$_SESSION已经在bootstrap.php中初始化
        // 只有在非测试环境中才启动会话
        if (!defined('PHPUNIT_RUNNING') && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 确保$_SESSION已初始化
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        
        $this->session = $_SESSION;
    }

    protected function view($name, $data = [])
    {
        extract($data);
        
        $viewFile = ROOT_PATH . "/app/Views/{$name}.php";
        
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            throw new \Exception("View file not found: {$name}");
        }
    }

    protected function isLoggedIn()
    {
        return isset($this->session['user_id']);
    }

    protected function getCurrentUser()
    {
        if ($this->isLoggedIn()) {
            if (!$this->user) {
                $userModel = new \App\Models\User();
                $this->user = $userModel->getById($this->session['user_id']);
            }
            return $this->user;
        }
        return null;
    }

    protected function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }
    }

    protected function requireRole($role)
    {
        $this->requireLogin();
        $user = $this->getCurrentUser();
        
        if ($user['role'] !== $role) {
            header('Location: /');
            exit;
        }
    }

    protected function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect($url)
    {
        header("Location: {$url}");
        exit;
    }
} 