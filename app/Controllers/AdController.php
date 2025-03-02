<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\Ad;
use App\Models\User;

class AdController {
    private $adModel;
    private $userModel;
    private $response;
    
    public function __construct() {
        $this->adModel = new Ad();
        $this->userModel = new User();
        $this->response = new Response();
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
     * Serve ad via iframe
     */
    public function serve() {
        header('Content-Type: text/html');
        
        try {
            $ads = $this->adModel->getActiveAds();
            
            if (empty($ads)) {
                echo '<div style="text-align: center; padding: 20px;">No active ads available</div>';
                return;
            }
            
            // Simple rotation: pick a random ad
            $ad = $ads[array_rand($ads)];
            
            // Output the ad content with tracking pixel
            echo <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { margin: 0; padding: 0; }
                    .ad-container { width: 100%; height: 100%; }
                </style>
            </head>
            <body>
                <div class="ad-container">
                    {$ad['content']}
                </div>
                <img src="/api/track?ad_id={$ad['id']}" style="position: absolute; width: 1px; height: 1px; opacity: 0;" />
            </body>
            </html>
            HTML;
        } catch (\Exception $e) {
            echo '<div style="text-align: center; padding: 20px;">Error loading ad</div>';
        }
    }
    
    /**
     * Track ad view
     */
    public function track() {
        if (!isset($_GET['ad_id']) || !isset($_SESSION['publisher_id'])) {
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            return;
        }
        
        try {
            $this->adModel->trackView(
                $_GET['ad_id'],
                $_SESSION['publisher_id'],
                $_SERVER['REMOTE_ADDR']
            );
        } catch (\Exception $e) {
            // Silently fail - we still need to return the tracking pixel
        }
        
        // Return a 1x1 transparent GIF
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    }
} 