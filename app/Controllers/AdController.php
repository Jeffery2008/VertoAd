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
     * Validates the structure and basic values of the targeting data array.
     * Returns true if valid, false otherwise.
     */
    private function validateTargeting($data) {
        if (!is_array($data)) {
             error_log("Validation Error: Targeting data is not an array.");
             return false;
         }

        // 验证地理定向 (geo)
        if (isset($data['geo'])) {
            if (!is_array($data['geo'])) {
                 error_log("Validation Error: geo is not an array.");
                 return false;
             }
             // Check sub-arrays contain only strings if they exist and are arrays
             foreach (['countries', 'regions', 'cities'] as $key) {
                 if (isset($data['geo'][$key])) {
                     if (!is_array($data['geo'][$key])) {
                         error_log("Validation Error: geo[{$key}] is not an array.");
                         return false;
                     }
                     foreach ($data['geo'][$key] as $item) {
                         if (!is_string($item)) {
                             error_log("Validation Error: geo[{$key}] contains non-string value.");
                            return false;
                         }
                     }
                 }
             }
        }

        // 验证设备定向 (devices)
        if (isset($data['devices'])) {
            if (!is_array($data['devices'])) { error_log("Validation Error: devices is not an array."); return false; }
            $validDevices = ['desktop', 'mobile', 'tablet'];
            foreach ($data['devices'] as $device) {
                if (!is_string($device) || !in_array(strtolower($device), $validDevices)) {
                     error_log("Validation Error: Invalid device '{$device}'.");
                     return false;
                 }
            }
        }

        // 验证浏览器定向 (browsers)
        if (isset($data['browsers'])) {
            if (!is_array($data['browsers'])) { error_log("Validation Error: browsers is not an array."); return false; }
            // Allow any string for browser for flexibility, or define a list:
            // $validBrowsers = ['chrome', 'firefox', 'safari', 'edge', 'ie', 'opera', 'other']; 
            foreach ($data['browsers'] as $browser) {
                 if (!is_string($browser)) { error_log("Validation Error: browser value is not a string."); return false; }
                // if (!in_array(strtolower($browser), $validBrowsers)) return false;
            }
        }

        // 验证操作系统定向 (os)
        if (isset($data['os'])) {
            if (!is_array($data['os'])) { error_log("Validation Error: os is not an array."); return false; }
            // Allow any string for OS for flexibility, or define a list:
            // $validOs = ['windows', 'macos', 'linux', 'ios', 'android', 'other'];
            foreach ($data['os'] as $os) {
                if (!is_string($os)) { error_log("Validation Error: os value is not a string."); return false; }
                // if (!in_array(strtolower($os), $validOs)) return false;
            }
        }

        // 验证时间表 (schedule)
        if (isset($data['schedule'])) {
            if (!is_array($data['schedule'])) { error_log("Validation Error: schedule is not an array."); return false; }
            
            // Validate timezone (must be a valid PHP timezone identifier)
            if (isset($data['schedule']['timezone'])) {
                 if (!is_string($data['schedule']['timezone']) || 
                     !in_array($data['schedule']['timezone'], \DateTimeZone::listIdentifiers())) {
                     error_log("Validation Error: Invalid timezone '{$data['schedule']['timezone']}'.");
                    return false;
                }
            } // If not set, AdTargetingModel defaults to UTC, which is fine.

            // Validate hours array
            if (isset($data['schedule']['hours'])) {
                 if (!is_array($data['schedule']['hours'])) { error_log("Validation Error: schedule[hours] is not an array."); return false; }
                foreach ($data['schedule']['hours'] as $hour) {
                    if (!is_int($hour) || $hour < 0 || $hour > 23) {
                         error_log("Validation Error: Invalid hour '{$hour}'. Must be 0-23.");
                         return false;
                     }
                }
            }

             // Validate days array (optional)
            if (isset($data['schedule']['days'])) {
                 if (!is_array($data['schedule']['days'])) { error_log("Validation Error: schedule[days] is not an array."); return false; }
                foreach ($data['schedule']['days'] as $day) {
                    if (!is_int($day) || $day < 1 || $day > 7) {
                         error_log("Validation Error: Invalid day '{$day}'. Must be 1-7.");
                         return false;
                     }
                }
            }
        }

        // 验证语言定向 (language)
        if (isset($data['language'])) {
            if (!is_array($data['language'])) { error_log("Validation Error: language is not an array."); return false; }
            foreach ($data['language'] as $lang) {
                 // Basic check for 2-letter language codes, case-insensitive
                if (!is_string($lang) || !preg_match('/^[a-zA-Z]{2}$/', $lang)) {
                     error_log("Validation Error: Invalid language code '{$lang}'. Must be 2 letters.");
                     return false;
                 }
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