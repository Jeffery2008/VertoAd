<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Ad;
use App\Models\User;
use App\Models\AdModel;
use App\Models\AdStatsModel;
use App\Models\AdTargetingModel;
use App\Utils\AdContextDetector;

class AdController {
    private $adModel;
    private $userModel;
    private $response;
    private $adStatsModel;
    private $adTargetingModel;
    private $contextDetector;
    
    public function __construct() {
        $this->adModel = new Ad();
        $this->userModel = new User();
        $this->response = new Response();
        $this->adStatsModel = new AdStatsModel();
        $this->adTargetingModel = new AdTargetingModel();
        $this->contextDetector = new AdContextDetector();
    }
    
    /**
     * List ads
     */
    public function list() {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $ads = $this->adModel->listByUser($_SESSION['user_id']);
        return $this->response->json(['ads' => $ads]);
    }
    
    /**
     * Create new ad
     */
    public function create() {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title']) || !isset($data['content']) || !isset($data['budget']) || !isset($data['cost_per_view'])) {
            return $this->response->json(['error' => 'Missing required fields'], 400);
        }
        
        try {
            $adId = $this->adModel->create(
                $_SESSION['user_id'],
                $data['title'],
                $data['content'],
                $data['budget'],
                $data['cost_per_view']
            );
            
            return $this->response->json(['id' => $adId, 'message' => 'Ad created successfully']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get ad details
     */
    public function get($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $ad = $this->adModel->getById($id);
        
        if (!$ad) {
            return $this->response->json(['error' => 'Ad not found'], 404);
        }
        
        if ($ad['user_id'] !== $_SESSION['user_id'] && !$this->userModel->isAdmin($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        return $this->response->json($ad);
    }
    
    /**
     * Update ad
     */
    public function update($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $ad = $this->adModel->getById($id);
        
        if (!$ad) {
            return $this->response->json(['error' => 'Ad not found'], 404);
        }
        
        if ($ad['user_id'] !== $_SESSION['user_id']) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title']) || !isset($data['content'])) {
            return $this->response->json(['error' => 'Missing required fields'], 400);
        }
        
        try {
            $this->adModel->update($id, $data['title'], $data['content']);
            return $this->response->json(['message' => 'Ad updated successfully']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Delete ad
     */
    public function delete($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $ad = $this->adModel->getById($id);
        
        if (!$ad) {
            return $this->response->json(['error' => 'Ad not found'], 404);
        }
        
        if ($ad['user_id'] !== $_SESSION['user_id']) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $this->adModel->delete($id);
            return $this->response->json(['message' => 'Ad deleted successfully']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Submit ad for review
     */
    public function submit($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $ad = $this->adModel->getById($id);
        
        if (!$ad) {
            return $this->response->json(['error' => 'Ad not found'], 404);
        }
        
        if ($ad['user_id'] !== $_SESSION['user_id']) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $this->adModel->submit($id);
            return $this->response->json(['message' => 'Ad submitted for review']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Approve ad (admin only)
     */
    public function approve($id) {
        if (!isset($_SESSION['user_id']) || !$this->userModel->isAdmin($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $this->adModel->approve($id);
            return $this->response->json(['message' => 'Ad approved']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Reject ad (admin only)
     */
    public function reject($id) {
        if (!isset($_SESSION['user_id']) || !$this->userModel->isAdmin($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $this->adModel->reject($id);
            return $this->response->json(['message' => 'Ad rejected']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * 设置广告定向规则
     */
    public function setTargeting($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }

        $ad = $this->adModel->getById($id);
        if (!$ad || $ad['user_id'] !== $_SESSION['user_id']) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$this->validateTargeting($data)) {
            return $this->response->json(['error' => 'Invalid targeting data'], 400);
        }

        try {
            $this->adTargetingModel->saveTargeting($id, $data);
            return $this->response->json(['message' => 'Targeting rules updated successfully']);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取广告定向规则
     */
    public function getTargeting($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }

        $ad = $this->adModel->getById($id);
        if (!$ad || $ad['user_id'] !== $_SESSION['user_id']) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $targeting = $this->adTargetingModel->getTargeting($id);
            return $this->response->json(['targeting' => $targeting]);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取广告定向效果统计
     */
    public function getTargetingStats($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }

        $ad = $this->adModel->getById($id);
        if (!$ad || $ad['user_id'] !== $_SESSION['user_id']) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }

        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        try {
            $stats = $this->adTargetingModel->getTargetingStats($id, $startDate, $endDate);
            return $this->response->json(['stats' => $stats]);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 验证定向规则数据
     */
    private function validateTargeting($data) {
        // 验证地理定向
        if (isset($data['geo'])) {
            if (!is_array($data['geo'])) return false;
            if (isset($data['geo']['countries']) && !is_array($data['geo']['countries'])) return false;
            if (isset($data['geo']['regions']) && !is_array($data['geo']['regions'])) return false;
            if (isset($data['geo']['cities']) && !is_array($data['geo']['cities'])) return false;
        }

        // 验证设备定向
        if (isset($data['devices'])) {
            if (!is_array($data['devices'])) return false;
            $validDevices = ['desktop', 'mobile', 'tablet'];
            foreach ($data['devices'] as $device) {
                if (!in_array($device, $validDevices)) return false;
            }
        }

        // 验证浏览器定向
        if (isset($data['browsers'])) {
            if (!is_array($data['browsers'])) return false;
            $validBrowsers = ['chrome', 'firefox', 'safari', 'edge', 'ie', 'opera'];
            foreach ($data['browsers'] as $browser) {
                if (!in_array($browser, $validBrowsers)) return false;
            }
        }

        // 验证操作系统定向
        if (isset($data['os'])) {
            if (!is_array($data['os'])) return false;
            $validOs = ['windows', 'macos', 'linux', 'ios', 'android'];
            foreach ($data['os'] as $os) {
                if (!in_array($os, $validOs)) return false;
            }
        }

        // 验证时间表
        if (isset($data['schedule'])) {
            if (!is_array($data['schedule'])) return false;
            if (!isset($data['schedule']['timezone'])) return false;
            if (!isset($data['schedule']['hours']) || !is_array($data['schedule']['hours'])) return false;
            foreach ($data['schedule']['hours'] as $hour) {
                if (!is_int($hour) || $hour < 0 || $hour > 23) return false;
            }
        }

        // 验证语言定向
        if (isset($data['language'])) {
            if (!is_array($data['language'])) return false;
            foreach ($data['language'] as $lang) {
                if (!preg_match('/^[a-z]{2}$/', $lang)) return false;
            }
        }

        return true;
    }
    
    /**
     * 投放广告
     */
    public function serve() {
        $zoneId = $_GET['zone'] ?? null;
        
        if (!$zoneId) {
            return json_encode([
                'success' => false,
                'message' => 'Zone ID is required'
            ]);
        }

        try {
            // 获取访问者上下文
            $context = $this->contextDetector->getContext();

            // 获取适合该广告位的广告
            $ads = $this->adModel->getActiveAdsForZone($zoneId);
            
            // 筛选符合定向条件的广告
            $matchedAds = [];
            foreach ($ads as $ad) {
                if ($this->adTargetingModel->matchTargeting($ad['id'], $context)) {
                    $matchedAds[] = $ad;
                }
            }

            if (empty($matchedAds)) {
                return json_encode([
                    'success' => false,
                    'message' => 'No matching ad available'
                ]);
            }

            // 简单随机选择一个广告（后续可以改进为加权随机）
            $ad = $matchedAds[array_rand($matchedAds)];

            // 记录定向统计
            $this->adTargetingModel->logTargetingStats($ad['id'], $context, 'view');

            return json_encode([
                'success' => true,
                'ad' => [
                    'id' => $ad['id'],
                    'title' => $ad['title'],
                    'image_url' => $ad['image_url'],
                    'target_url' => $ad['target_url']
                ]
            ]);
        } catch (\Exception $e) {
            error_log("Error serving ad: " . $e->getMessage());
            return json_encode([
                'success' => false,
                'message' => 'Error serving ad'
            ]);
        }
    }
    
    /**
     * 记录广告展示
     */
    public function logView() {
        $data = json_decode(file_get_contents('php://input'), true);
        $adId = $data['ad_id'] ?? null;
        
        if (!$adId) {
            return json_encode([
                'success' => false,
                'message' => 'Ad ID is required'
            ]);
        }

        try {
            $context = $this->contextDetector->getContext();
            
            // 记录基本展示统计
            $this->adStatsModel->logView($adId);
            
            // 记录定向统计
            $this->adTargetingModel->logTargetingStats($adId, $context, 'view');
            
            return json_encode(['success' => true]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Error logging view'
            ]);
        }
    }
    
    /**
     * 记录广告点击
     */
    public function logClick() {
        $data = json_decode(file_get_contents('php://input'), true);
        $adId = $data['ad_id'] ?? null;
        
        if (!$adId) {
            return json_encode([
                'success' => false,
                'message' => 'Ad ID is required'
            ]);
        }

        try {
            $context = $this->contextDetector->getContext();
            
            // 记录基本点击统计
            $this->adStatsModel->logClick($adId);
            
            // 记录定向统计
            $this->adTargetingModel->logTargetingStats($adId, $context, 'click');
            
            return json_encode(['success' => true]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Error logging click'
            ]);
        }
    }
} 