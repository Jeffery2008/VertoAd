<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\Ad;
use App\Models\AdView;
use App\Models\AdZone;

class PublisherController extends Controller
{
    protected $user;
    protected $ad;
    protected $adView;
    protected $adZone;

    public function __construct()
    {
        parent::__construct();
        $this->user = new User();
        $this->ad = new Ad();
        $this->adView = new AdView();
        $this->adZone = new AdZone();
    }

    public function index()
    {
        // 检查用户是否登录
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        // 检查用户是否是发布者
        if ($this->user['role'] !== 'publisher') {
            header('Location: /');
            exit;
        }

        // 获取发布者的广告统计
        $stats = $this->adView->getViewsByPublisher($this->user['id']);

        // 渲染视图
        $this->view('publisher/dashboard', [
            'stats' => $stats,
            'user' => $this->user
        ]);
    }

    public function getAd()
    {
        // 获取活跃的广告
        $ads = $this->ad->getActiveAds();
        
        if (empty($ads)) {
            echo json_encode(['success' => false, 'message' => 'No active ads available']);
            return;
        }

        // 随机选择一个广告
        $ad = $ads[array_rand($ads)];

        echo json_encode([
            'success' => true,
            'ad' => [
                'id' => $ad['id'],
                'title' => $ad['title'],
                'content' => $ad['content']
            ]
        ]);
    }

    public function recordView()
    {
        try {
            // 验证请求
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \Exception('Invalid request method');
            }

            // 获取参数
            $data = json_decode(file_get_contents('php://input'), true);
            $adId = $data['ad_id'] ?? null;
            $publisherId = $data['publisher_id'] ?? null;
            $ipAddress = $_SERVER['REMOTE_ADDR'];

            if (!$adId || !$publisherId) {
                throw new \Exception('Missing required parameters');
            }

            // 记录广告浏览
            $viewId = $this->adView->record($adId, $publisherId, $ipAddress);

            echo json_encode([
                'success' => true,
                'view_id' => $viewId
            ]);
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function stats()
    {
        // 检查用户是否登录
        if (!$this->isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        // 获取统计数据
        $views = $this->adView->getViewsByPublisher($this->user['id']);
        $earnings = 0;
        foreach ($views as $view) {
            $earnings += $view['cost'];
        }

        // 渲染视图
        $this->view('publisher/stats', [
            'views' => $views,
            'earnings' => $earnings,
            'user' => $this->user
        ]);
    }

    /**
     * 广告位列表页面
     */
    public function zones() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是网站主
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Location: /login');
            exit;
        }

        // 获取网站主的广告位列表
        $zones = $this->adZone->getByUserId($_SESSION['user_id']);

        return $this->response->renderView('publisher/zones', [
            'zones' => $zones
        ]);
    }

    /**
     * 创建新广告位
     */
    public function createZone() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['name']) || !isset($data['size']) || !isset($data['type'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            $zoneId = $this->adZone->create([
                'user_id' => $_SESSION['user_id'],
                'name' => $data['name'],
                'size' => $data['size'],
                'type' => $data['type'],
                'description' => $data['description'] ?? '',
                'website_url' => $data['website_url'] ?? '',
                'status' => 'active'
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'zone_id' => $zoneId
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * 更新广告位设置
     */
    public function updateZone($zoneId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 验证广告位所有权
        $zone = $this->adZone->getById($zoneId);
        if (!$zone || $zone['user_id'] !== $_SESSION['user_id']) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access to zone']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request data']);
            exit;
        }

        try {
            $success = $this->adZone->update($zoneId, $data);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * 删除广告位
     */
    public function deleteZone($zoneId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 验证广告位所有权
        $zone = $this->adZone->getById($zoneId);
        if (!$zone || $zone['user_id'] !== $_SESSION['user_id']) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access to zone']);
            exit;
        }

        try {
            $success = $this->adZone->delete($zoneId);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * 广告位广告管理页面
     */
    public function zoneAds($zoneId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Location: /login');
            exit;
        }

        // 验证广告位所有权
        $zone = $this->adZone->getById($zoneId);
        if (!$zone || $zone['user_id'] !== $_SESSION['user_id']) {
            header('Location: /publisher/zones');
            exit;
        }

        // 获取所有可用的广告
        $ads = $this->ad->getAllActive();
        
        // 获取已选择的广告
        $selectedAds = $this->adZone->getSelectedAds($zoneId);

        return $this->response->renderView('publisher/zone_ads', [
            'zone' => $zone,
            'ads' => $ads,
            'selectedAds' => $selectedAds
        ]);
    }

    /**
     * 更新广告位的广告选择
     */
    public function updateZoneAds($zoneId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 验证广告位所有权
        $zone = $this->adZone->getById($zoneId);
        if (!$zone || $zone['user_id'] !== $_SESSION['user_id']) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access to zone']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['ad_ids'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request data']);
            exit;
        }

        try {
            $success = $this->adZone->updateSelectedAds($zoneId, $data['ad_ids']);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success
            ]);
        } catch (\Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
} 