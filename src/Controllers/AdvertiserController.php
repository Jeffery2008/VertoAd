<?php
namespace Controllers;

use Services\AuthService;
use Services\AdService;
use Models\Advertisement;
use Models\AdPosition;
use Utils\Logger;
use Utils\Validator;

class AdvertiserController {
    protected $auth;
    protected $validator;
    protected $adService;
    protected $adModel;
    protected $positionModel;
    protected $db;
    
    public function __construct() {
        $this->auth = AuthService::getInstance();
        $this->validator = Validator::getInstance();
        $this->adService = AdService::getInstance();
        $this->adModel = new Advertisement();
        $this->positionModel = new AdPosition();
        
        // Get database connection
        $dbConnection = new \Database();
        $this->db = $dbConnection->connect();
        
        // Check authentication for all advertiser routes
        $this->checkAdvertiserAuth();
    }
    
    protected function checkAdvertiserAuth() {
        // Skip auth check for login and registration pages
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentPath, '/advertiser/login') !== false || 
            strpos($currentPath, '/advertiser/register') !== false) {
            return;
        }
        
        // Get JWT from header or cookie
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['advertiser_token'] ?? null;
        if (!$token) {
            $this->redirectToLogin();
        }
        
        // Remove Bearer prefix if exists
        $token = str_replace('Bearer ', '', $token);
        
        // Validate token
        $payload = $this->auth->validateToken($token);
        if (!$payload || $payload['type'] !== 'advertiser') {
            $this->redirectToLogin();
        }
        
        // Store advertiser info in request
        $_REQUEST['advertiser_id'] = $payload['sub'];
    }
    
    protected function redirectToLogin() {
        header('Location: /advertiser/login.php');
        exit;
    }
    
    protected function validateCSRF() {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token || $token !== $_SESSION['csrf_token']) {
            Logger::error('CSRF validation failed', [
                'provided_token' => $token,
                'session_token' => $_SESSION['csrf_token'] ?? null
            ]);
            $this->jsonResponse(['error' => 'Invalid request token'], 403);
        }
    }
    
    protected function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    protected function getAdvertiserInfo() {
        try {
            $advertiserId = $_REQUEST['advertiser_id'];
            $sql = "SELECT * FROM advertisers WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$advertiserId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error('Error fetching advertiser info: ' . $e->getMessage());
            return null;
        }
    }
    
    protected function renderView($view, $data = []) {
        // Add common data
        $data['advertiser'] = $this->getAdvertiserInfo();
        $data['csrf_token'] = $_SESSION['csrf_token'] ?? '';
        
        // Extract data to make variables available in view
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include view file
        $viewFile = __DIR__ . "/../../templates/advertiser/$view.php";
        if (!file_exists($viewFile)) {
            throw new \Exception("View file not found: $view");
        }
        include $viewFile;
        
        // Get buffered content
        $content = ob_get_clean();
        
        // Render with layout
        include __DIR__ . "/../../templates/advertiser/layout.php";
    }
    
    // Dashboard page
    public function dashboard() {
        $advertiserId = $_REQUEST['advertiser_id'];
        
        // Get active ads
        $activeAds = $this->adModel->all([
            'advertiser_id' => $advertiserId,
            'status' => 'active'
        ]);
        
        // Get pending ads
        $pendingAds = $this->adModel->all([
            'advertiser_id' => $advertiserId,
            'status' => 'pending'
        ]);
        
        // Get recently completed ads
        $completedAds = $this->adModel->all([
            'advertiser_id' => $advertiserId,
            'status' => 'completed'
        ], 'end_date DESC', 5);
        
        // Get performance metrics
        $startDate = date('Y-m-d', strtotime('-30 days'));
        $endDate = date('Y-m-d');
        $statistics = [];
        
        foreach ($activeAds as $ad) {
            $adStats = $this->adModel->getStatistics($ad['id'], $startDate, $endDate);
            $statistics[$ad['id']] = $adStats;
        }
        
        $this->renderView('dashboard', [
            'activeAds' => $activeAds,
            'pendingAds' => $pendingAds,
            'completedAds' => $completedAds,
            'statistics' => $statistics
        ]);
    }
    
    // Ad Canvas Editor
    public function adEditor($adId = null) {
        $advertiserId = $_REQUEST['advertiser_id'];
        $templateId = $_GET['template'] ?? null;
        
        // Get available ad positions
        $positions = $this->positionModel->getActivePositions();
        
        if ($adId) {
            // Edit existing ad
            $ad = $this->adModel->find($adId);
            
            // Check if ad belongs to this advertiser
            if (!$ad || $ad['advertiser_id'] != $advertiserId) {
                $this->jsonResponse(['error' => 'Ad not found'], 404);
            }
            
            $this->renderView('canvas', [
                'ad' => $ad,
                'positions' => $positions,
                'mode' => 'edit'
            ]);
        } else {
            // New ad
            $defaultPosition = !empty($positions) ? $positions[0]['id'] : null;
            
            // If template is specified, get template content
            $template = null;
            if ($templateId) {
                try {
                    $sql = "SELECT * FROM ad_templates WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$templateId]);
                    $template = $stmt->fetch(\PDO::FETCH_ASSOC);
                } catch (\Exception $e) {
                    Logger::error('Error fetching template: ' . $e->getMessage());
                }
            }
            
            $this->renderView('canvas', [
                'positions' => $positions,
                'selectedPosition' => $_GET['position'] ?? $defaultPosition,
                'template' => $template,
                'mode' => 'create'
            ]);
        }
    }
    
    // Save ad from canvas editor
    public function saveAd() {
        try {
            $this->validateCSRF();
            $advertiserId = $_REQUEST['advertiser_id'];
            
            // Validate input
            $rules = [
                'title' => 'required|max:255',
                'content' => 'required',
                'position_id' => 'required|int',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'total_budget' => 'required|numeric|min:0',
            ];
            
            if (!$this->validator->validate($_POST, $rules)) {
                $this->jsonResponse([
                    'success' => false,
                    'errors' => $this->validator->getErrors()
                ], 422);
            }
            
            // Check if editing existing ad
            $adId = $_POST['ad_id'] ?? null;
            
            if ($adId) {
                // Check if ad belongs to this advertiser
                $ad = $this->adModel->find($adId);
                if (!$ad || $ad['advertiser_id'] != $advertiserId) {
                    $this->jsonResponse(['error' => 'Unauthorized'], 403);
                }
                
                // Update ad
                $updateData = [
                    'title' => $_POST['title'],
                    'content' => $_POST['content'],
                    'original_content' => $_POST['original_content'] ?? $ad['original_content'],
                    'position_id' => $_POST['position_id'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'total_budget' => $_POST['total_budget'],
                    'remaining_budget' => $_POST['total_budget'],
                    'status' => 'pending', // Reset to pending for review
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $success = $this->adModel->update($adId, $updateData);
                
                $this->jsonResponse([
                    'success' => $success,
                    'message' => $success ? 'Ad updated successfully' : 'Failed to update ad',
                    'ad_id' => $adId
                ]);
            } else {
                // Create new ad
                $advertisserInfo = $this->getAdvertiserInfo();
                $adData = [
                    'title' => $_POST['title'],
                    'content' => $_POST['content'],
                    'original_content' => $_POST['original_content'] ?? null,
                    'position_id' => $_POST['position_id'],
                    'start_date' => $_POST['start_date'],
                    'end_date' => $_POST['end_date'],
                    'total_budget' => $_POST['total_budget'],
                    'remaining_budget' => $_POST['total_budget'],
                    'priority' => 0,
                    'status' => 'pending'
                ];
                
                $newAdId = $this->adService->createAd(
                    $advertisserInfo,
                    $adData,
                    $_POST['position_id']
                );
                
                $this->jsonResponse([
                    'success' => $newAdId ? true : false,
                    'message' => $newAdId ? 'Ad created successfully' : 'Failed to create ad',
                    'ad_id' => $newAdId
                ]);
            }
        } catch (\Exception $e) {
            Logger::error('Error saving ad: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error', 'message' => $e->getMessage()], 500);
        }
    }
    
    // Get ad statistics
    public function getAdStatistics($adId) {
        try {
            $advertiserId = $_REQUEST['advertiser_id'];
            
            // Check if ad belongs to this advertiser
            $ad = $this->adModel->find($adId);
            if (!$ad || $ad['advertiser_id'] != $advertiserId) {
                $this->jsonResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Get requested period
            $period = $_GET['period'] ?? '30days';
            $validPeriods = ['24hours', '7days', '30days', '90days', 'all'];
            if (!in_array($period, $validPeriods)) {
                $period = '30days';
            }
            
            // Get statistics
            $stats = $this->adService->getPerformanceDashboard($adId, $period);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Logger::error('Error fetching ad statistics: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }
    
    // Ad management page
    public function manageAds() {
        $advertiserId = $_REQUEST['advertiser_id'];
        $status = $_GET['status'] ?? 'all';
        $page = max(1, intval($_GET['page'] ?? 1));
        $perPage = 10;
        
        try {
            // Build query conditions
            $conditions = ['advertiser_id' => $advertiserId];
            if ($status !== 'all') {
                $conditions['status'] = $status;
            }
            
            // Get total count for pagination
            $totalAds = $this->adModel->count($conditions);
            $totalPages = ceil($totalAds / $perPage);
            
            // Get ads for current page
            $offset = ($page - 1) * $perPage;
            $ads = $this->adModel->all(
                $conditions,
                'created_at DESC',
                $perPage,
                $offset
            );
            
            $this->renderView('manage-ads', [
                'ads' => $ads,
                'currentStatus' => $status,
                'pagination' => [
                    'current' => $page,
                    'total' => $totalPages,
                    'perPage' => $perPage,
                    'totalItems' => $totalAds
                ]
            ]);
        } catch (\Exception $e) {
            Logger::error('Error managing ads: ' . $e->getMessage());
            $this->renderView('error', [
                'message' => 'An error occurred while loading your advertisements'
            ]);
        }
    }
    
    // Pause ad
    public function pauseAd($adId) {
        $this->validateCSRF();
        $advertiserId = $_REQUEST['advertiser_id'];
        
        try {
            // Check if ad belongs to this advertiser
            $ad = $this->adModel->find($adId);
            if (!$ad || $ad['advertiser_id'] != $advertiserId) {
                $this->jsonResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Check if ad can be paused
            if ($ad['status'] !== 'active') {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Only active ads can be paused'
                ], 400);
            }
            
            $success = $this->adModel->updateStatus($adId, 'paused');
            
            $this->jsonResponse([
                'success' => $success,
                'message' => $success ? 'Ad paused successfully' : 'Failed to pause ad'
            ]);
        } catch (\Exception $e) {
            Logger::error('Error pausing ad: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }
    
    // Resume ad
    public function resumeAd($adId) {
        $this->validateCSRF();
        $advertiserId = $_REQUEST['advertiser_id'];
        
        try {
            // Check if ad belongs to this advertiser
            $ad = $this->adModel->find($adId);
            if (!$ad || $ad['advertiser_id'] != $advertiserId) {
                $this->jsonResponse(['error' => 'Unauthorized'], 403);
            }
            
            // Check if ad can be resumed
            if ($ad['status'] !== 'paused') {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Only paused ads can be resumed'
                ], 400);
            }
            
            $success = $this->adModel->updateStatus($adId, 'active');
            
            $this->jsonResponse([
                'success' => $success,
                'message' => $success ? 'Ad resumed successfully' : 'Failed to resume ad'
            ]);
        } catch (\Exception $e) {
            Logger::error('Error resuming ad: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }
    
    // Account settings page
    public function accountSettings() {
        $advertiserId = $_REQUEST['advertiser_id'];
        $advertiser = $this->getAdvertiserInfo();
        
        $this->renderView('account-settings', [
            'advertiser' => $advertiser
        ]);
    }
    
    // Update account settings
    public function updateAccount() {
        $this->validateCSRF();
        $advertiserId = $_REQUEST['advertiser_id'];
        
        try {
            // Validate input
            $rules = [
                'name' => 'required|max:100',
                'email' => "required|email|unique:advertisers,email,$advertiserId",
                'company_name' => 'max:100',
                'current_password' => 'required_with:new_password',
                'new_password' => 'min:8|max:64'
            ];
            
            if (!$this->validator->validate($_POST, $rules)) {
                $this->jsonResponse([
                    'success' => false,
                    'errors' => $this->validator->getErrors()
                ], 422);
            }
            
            $updateData = [
                'name' => $_POST['name'],
                'email' => $_POST['email'],
                'company_name' => $_POST['company_name'] ?? null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // If changing password, verify current password
            if (!empty($_POST['new_password'])) {
                // Get current password hash
                $advertiser = $this->getAdvertiserInfo();
                if (!$this->auth->verifyPassword($_POST['current_password'], $advertiser['password'])) {
                    $this->jsonResponse([
                        'success' => false,
                        'errors' => ['current_password' => ['Current password is incorrect']]
                    ], 422);
                }
                
                $updateData['password'] = $this->auth->hashPassword($_POST['new_password']);
            }
            
            // Update advertiser
            $success = false;
            try {
                $sql = "UPDATE advertisers SET ";
                $setValues = [];
                $params = [];
                
                foreach ($updateData as $key => $value) {
                    $setValues[] = "$key = ?";
                    $params[] = $value;
                }
                
                $sql .= implode(', ', $setValues);
                $sql .= " WHERE id = ?";
                $params[] = $advertiserId;
                
                $stmt = $this->db->prepare($sql);
                $success = $stmt->execute($params);
            } catch (\Exception $e) {
                Logger::error('Error updating account: ' . $e->getMessage());
                $success = false;
            }
            
            $this->jsonResponse([
                'success' => $success,
                'message' => $success ? 'Account updated successfully' : 'Failed to update account'
            ]);
        } catch (\Exception $e) {
            Logger::error('Error updating account: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Server error'], 500);
        }
    }
}
