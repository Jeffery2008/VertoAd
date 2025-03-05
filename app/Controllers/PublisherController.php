<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\Ad;
use App\Models\AdView;
use App\Core\Response;
use App\Models\AdZone;
use App\Models\AdZoneTargeting;
use App\Models\AdZoneStats;

class PublisherController extends Controller
{
    protected $user;
    protected $ad;
    protected $adView;
    private $response;
    private $adZone;
    private $adZoneTargeting;
    private $adZoneStats;

    public function __construct()
    {
        parent::__construct();
        $this->user = new User();
        $this->ad = new Ad();
        $this->adView = new AdView();
        $this->response = new Response();
        $this->adZone = new AdZone();
        $this->adZoneTargeting = new AdZoneTargeting();
        $this->adZoneStats = new AdZoneStats();
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
     * 网站主仪表板
     */
    public function dashboard() {
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

        return $this->response->renderView('publisher/dashboard', [
            'zones' => $zones
        ]);
    }

    /**
     * 广告位定向规则管理
     */
    public function zoneTargeting() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是网站主
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Location: /login');
            exit;
        }

        // 获取网站主的所有广告位及其定向规则
        $zones = $this->adZone->getByUserId($_SESSION['user_id']);
        $targetingData = [];
        
        foreach ($zones as $zone) {
            $targeting = $this->adZoneTargeting->getTargeting($zone['id']);
            if ($targeting) {
                $targetingData[$zone['id']] = [
                    'zone' => $zone,
                    'targeting' => $targeting
                ];
            }
        }

        return $this->response->renderView('publisher/zone_targeting', [
            'targetingData' => $targetingData
        ]);
    }

    /**
     * 广告位定向效果统计
     */
    public function zoneTargetingStats() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是网站主
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Location: /login');
            exit;
        }

        // 获取日期范围
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        // 获取统计数据
        $zones = $this->adZone->getByUserId($_SESSION['user_id']);
        $stats = [];
        
        foreach ($zones as $zone) {
            $zoneStats = $this->adZoneStats->getTargetingStats($zone['id'], $startDate, $endDate);
            if ($zoneStats) {
                $stats[$zone['id']] = [
                    'zone' => $zone,
                    'stats' => $zoneStats
                ];
            }
        }

        return $this->response->renderView('publisher/zone_targeting_stats', [
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }

    /**
     * 更新广告位定向规则
     */
    public function updateZoneTargeting() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 检查是否登录且是网站主
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'publisher') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        // 验证请求数据
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['zones'])) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request data']);
            exit;
        }

        // 验证广告位所有权
        foreach (array_keys($data['zones']) as $zoneId) {
            $zone = $this->adZone->getById($zoneId);
            if (!$zone || $zone['user_id'] !== $_SESSION['user_id']) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized access to zone']);
                exit;
            }
        }

        // 更新定向规则
        $results = [];
        foreach ($data['zones'] as $zoneId => $targeting) {
            try {
                $success = $this->adZoneTargeting->saveTargeting($zoneId, $targeting);
                $results[$zoneId] = [
                    'success' => $success,
                    'message' => $success ? 'Updated successfully' : 'Update failed'
                ];
            } catch (\Exception $e) {
                $results[$zoneId] = [
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