<?php

namespace App\Controllers;

use App\Models\ActivationKey;
use App\Models\User;
use App\Core\Request;
use App\Core\Response;

class AdminController
{
    protected $response;
    protected $user;
    protected $activationKey;
    public function __construct() {
        $this->response = new Response();
        $this->user = new User();
        $this->activationKey = new ActivationKey();
    }
    public function dashboard(Request $request)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是管理员
        if (!isset($_SESSION['user_id'])) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized', 'message' => '请先登录']);
                exit;
            }
            header('Location: /admin/login');
            exit;
        }

        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden', 'message' => '需要管理员权限']);
                exit;
            }
            header('Location: /admin/login');
            exit;
        }

        // 获取管理员信息
        $users = $this->user->getAll();

        // 渲染管理员面板
        return $this->response->renderView('admin/dashboard', ['users' => $users]);
    }

    public function generateKeys(Request $request)
    {
        // 检查管理员是否登录
        session_start();
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /admin/login');
            exit;
        }
        
        // 处理GET请求 - 显示生成密钥表单
        if ($request->getMethod() === "GET")
        {
            return $this->response->renderView('admin/generate_keys');
        }
        
        // 处理POST请求
        $data = $request->getBody();
        
        // 调试输出，查看接收到的数据
        error_log('POST data received: ' . print_r($data, true));
        
        // 添加检查确保索引存在，否则使用默认值
        $amount = isset($data['amount']) ? $data['amount'] : 0;
        $quantity = isset($data['quantity']) ? $data['quantity'] : 0;
        $createdBy = $_SESSION['user_id'];

        // 如果没有有效的输入，直接返回视图
        if (empty($amount) || empty($quantity)) {
            return $this->response->renderView('admin/generate_keys', [
                'error' => '请输入有效的金额和数量'
            ]);
        }

        // 生成密钥
        $keys = [];
        for ($i = 0; $i < $quantity; $i++) {
            $key = bin2hex(random_bytes(16)); // 生成随机密钥
            $this->activationKey->create($key, $amount, $createdBy);
            $keys[] = $key;
        }

        // 导出 CSV
        if (isset($data['export']) && $data['export'] === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="activation_keys.csv"');
            
            // 确保在设置header前没有任何输出
            ob_end_clean();
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Key', 'Amount']);
            foreach ($keys as $key) {
                fputcsv($output, [$key, $amount]);
            }
            fclose($output);
            exit;
        }

        // 返回正常视图（带生成的密钥）
        return $this->response->renderView('admin/generate_keys', ['keys' => $keys]);
    }

    public function login(Request $request)
    {
        if ($request->getMethod() === 'GET') {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
                if ($_SESSION['user_role'] === 'admin') {
                    header('Location: /admin/dashboard');
                    exit;
                }
            }
            return $this->response->renderView('admin/login');
        }

        $data = $request->getBody();
        $username = $data['username'];
        $password = $data['password'];

        $user = $this->user->findByUsername($username);
        var_dump($user);

        if ($user && password_verify($password, $user['password'])) {
            var_dump("Before session_start");
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];

            // 移除所有调试输出，确保在header之前没有任何输出
            if ($user['role'] === 'admin') {
                var_dump("Before header, role: admin");
                header('Location: /admin/dashboard');
                exit;
            } elseif ($user['role'] === 'advertiser') {
                var_dump("Before header, role: advertiser");
                header('Location: /advertiser/dashboard');
                exit;
            } elseif ($user['role'] === 'publisher') {
                var_dump("Before header, role: publisher");
                header('Location: /publisher/dashboard');
                exit;
            }
        } else {
            var_dump("Authentication failed");
            return $this->response->renderView('admin/login', ['error' => 'Invalid username or password']);
        }
    }

    public function users() {
        // 用户管理页面占位符
        return $this->response->renderView('admin/placeholder', [
            'title' => '用户管理',
            'message' => '用户管理功能正在开发中...'
        ]);
    }

    public function settings() {
        // 系统设置页面占位符
        return $this->response->renderView('admin/placeholder', [
            'title' => '系统设置',
            'message' => '系统设置功能正在开发中...'
        ]);
    }

    /**
     * 广告定向规则管理页面
     */
    public function targeting() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是管理员
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /admin/login');
            exit;
        }

        // 获取所有广告的定向规则
        $adModel = new \App\Models\Ad();
        $adTargetingModel = new \App\Models\AdTargetingModel();
        
        $ads = $adModel->getAll();
        $targetingData = [];
        
        foreach ($ads as $ad) {
            $targeting = $adTargetingModel->getTargeting($ad['id']);
            if ($targeting) {
                $targetingData[$ad['id']] = [
                    'ad' => $ad,
                    'targeting' => $targeting
                ];
            }
        }

        return $this->response->renderView('admin/targeting', [
            'targetingData' => $targetingData
        ]);
    }

    /**
     * 查看定向规则统计数据
     */
    public function targetingStats() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是管理员
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: /admin/login');
            exit;
        }

        $adTargetingModel = new \App\Models\AdTargetingModel();
        
        // 获取所有广告的定向统计数据
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $stats = [];
        $adModel = new \App\Models\Ad();
        $ads = $adModel->getAll();
        
        foreach ($ads as $ad) {
            $adStats = $adTargetingModel->getTargetingStats($ad['id'], $startDate, $endDate);
            if ($adStats) {
                $stats[$ad['id']] = [
                    'ad' => $ad,
                    'stats' => $adStats
                ];
            }
        }

        return $this->response->renderView('admin/targeting_stats', [
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    /**
     * 批量更新定向规则
     */
    public function updateTargeting() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是管理员
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['ads'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request data']);
            exit;
        }

        $adTargetingModel = new \App\Models\AdTargetingModel();
        $results = [];

        foreach ($data['ads'] as $adId => $targeting) {
            try {
                $success = $adTargetingModel->saveTargeting($adId, $targeting);
                $results[$adId] = [
                    'success' => $success,
                    'message' => $success ? 'Updated successfully' : 'Update failed'
                ];
            } catch (\Exception $e) {
                $results[$adId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'results' => $results
        ]);
        exit;
    }
}
