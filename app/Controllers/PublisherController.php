<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\Ad;
use App\Models\AdView;

class PublisherController extends Controller
{
    protected $user;
    protected $ad;
    protected $adView;

    public function __construct()
    {
        parent::__construct();
        $this->user = new User();
        $this->ad = new Ad();
        $this->adView = new AdView();
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
} 