<?php

namespace App\Controllers;

use App\Core\Response;
use App\Models\User;
use App\Models\Ad;

class BillingController {
    private $userModel;
    private $adModel;
    private $response;
    
    public function __construct() {
        $this->userModel = new User();
        $this->adModel = new Ad();
        $this->response = new Response();
    }
    
    /**
     * Get user's credit balance
     */
    public function getCredits() {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $user = $this->userModel->getById($_SESSION['user_id']);
            return $this->response->json([
                'credits' => $user['credits']
            ]);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Add credits to user's account
     */
    public function addCredits() {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['amount']) || !is_numeric($data['amount']) || $data['amount'] <= 0) {
            return $this->response->json(['error' => 'Invalid amount'], 400);
        }
        
        try {
            // In a real system, this would integrate with a payment processor
            $this->userModel->addCredits($_SESSION['user_id'], $data['amount']);
            
            $newBalance = $this->userModel->getById($_SESSION['user_id'])['credits'];
            
            return $this->response->json([
                'message' => 'Credits added successfully',
                'new_balance' => $newBalance
            ]);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get statistics for a specific ad
     */
    public function getAdStats($id) {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $ad = $this->adModel->getById($id);
            
            if (!$ad) {
                return $this->response->json(['error' => 'Ad not found'], 404);
            }
            
            if ($ad['user_id'] !== $_SESSION['user_id'] && !$this->userModel->isAdmin($_SESSION['user_id'])) {
                return $this->response->json(['error' => 'Unauthorized'], 401);
            }
            
            $stats = $this->adModel->getStats($id);
            
            // Add additional statistics
            $stats['remaining_budget'] = $ad['remaining_budget'];
            $stats['total_budget'] = $ad['budget'];
            $stats['budget_used_percentage'] = ($ad['budget'] - $ad['remaining_budget']) / $ad['budget'] * 100;
            
            // Get hourly view distribution for the last 24 hours
            $hourlyStats = $this->getHourlyStats($id);
            $stats['hourly_views'] = $hourlyStats;
            
            return $this->response->json($stats);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get hourly view statistics for an ad
     */
    private function getHourlyStats($adId) {
        $sql = "SELECT 
                    DATE_FORMAT(viewed_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as views,
                    SUM(cost) as cost
                FROM ad_views 
                WHERE ad_id = ? 
                    AND viewed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY hour
                ORDER BY hour ASC";
        
        return $this->adModel->db->query($sql, [$adId])->fetchAll();
    }
    
    /**
     * Get publisher earnings report
     */
    public function getPublisherStats() {
        if (!isset($_SESSION['user_id'])) {
            return $this->response->json(['error' => 'Unauthorized'], 401);
        }
        
        try {
            $sql = "SELECT 
                        DATE(viewed_at) as date,
                        COUNT(*) as views,
                        SUM(cost) as earnings
                    FROM ad_views 
                    WHERE publisher_id = ?
                        AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY date
                    ORDER BY date DESC";
            
            $stats = $this->adModel->db->query($sql, [$_SESSION['user_id']])->fetchAll();
            
            // Calculate totals
            $totalViews = 0;
            $totalEarnings = 0;
            foreach ($stats as $day) {
                $totalViews += $day['views'];
                $totalEarnings += $day['earnings'];
            }
            
            return $this->response->json([
                'daily_stats' => $stats,
                'total_views' => $totalViews,
                'total_earnings' => $totalEarnings
            ]);
        } catch (\Exception $e) {
            return $this->response->json(['error' => $e->getMessage()], 500);
        }
    }
} 