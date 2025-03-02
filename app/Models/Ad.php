<?php

namespace App\Models;

use App\Core\Database;
use Exception;

class Ad {
    public $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Create a new ad
     */
    public function create($userId, $title, $content, $budget, $cost_per_view) {
        $stmt = $this->db->query(
            'INSERT INTO ads (user_id, title, content, status, budget, remaining_budget, cost_per_view) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $title, $content, 'draft', $budget, $budget, $cost_per_view]
        );
        
        if ($stmt->rowCount() > 0) {
            return (int)$this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Get ad by ID
     */
    public function findById($id) {
        $stmt = $this->db->query('SELECT * FROM ads WHERE id = ?', [$id]);
        return $stmt->fetch();
    }
    
    /**
     * Get ad by ID (alias for findById)
     */
    public function getById($id) {
        return $this->findById($id);
    }
    
    /**
     * Update ad content
     */
    public function update($id, $data) {
        $sets = [];
        $params = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
            $params[] = $value;
        }
        $params[] = $id;
        
        $sql = "UPDATE ads SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->db->query($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Delete ad
     */
    public function delete($id) {
        $stmt = $this->db->query('DELETE FROM ads WHERE id = ?', [$id]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Submit ad for review
     */
    public function submit($id) {
        $this->db->query(
            "UPDATE ads SET status = 'pending' WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * Approve ad
     */
    public function approve($id) {
        $this->db->query(
            "UPDATE ads SET status = 'approved' WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * Reject ad
     */
    public function reject($id) {
        $this->db->query(
            "UPDATE ads SET status = 'rejected' WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * Get active ads for serving
     */
    public function getActiveAds() {
        $result = $this->db->query(
            "SELECT * FROM ads WHERE status = 'approved' AND remaining_budget >= cost_per_view"
        );
        return $result->fetchAll();
    }
    
    /**
     * Track ad view and update remaining budget
     */
    public function trackView($adId, $publisherId, $ipAddress) {
        // Start transaction
        $this->db->query("START TRANSACTION");
        
        try {
            $ad = $this->findById($adId);
            
            if (!$ad || $ad['status'] !== 'approved') {
                throw new Exception('Invalid ad');
            }
            
            // Check budget after getting fresh data within transaction
            if ($ad['remaining_budget'] < $ad['cost_per_view']) {
                throw new Exception('Insufficient budget or invalid ad');
            }
            
            // Update remaining budget
            $affected = $this->db->query(
                "UPDATE ads 
                 SET remaining_budget = remaining_budget - cost_per_view 
                 WHERE id = ? AND remaining_budget >= cost_per_view",
                [$adId]
            )->rowCount();
            
            if ($affected === 0) {
                throw new Exception('Insufficient budget or invalid ad');
            }
            
            // Record the view
            $this->db->query(
                "INSERT INTO ad_views (ad_id, publisher_id, viewer_ip, cost) 
                 VALUES (?, ?, ?, ?)",
                [$adId, $publisherId, $ipAddress, $ad['cost_per_view']]
            );
            
            // Commit transaction
            $this->db->query("COMMIT");
            
        } catch (Exception $e) {
            // Rollback on error
            $this->db->query("ROLLBACK");
            throw $e;
        }
    }
    
    /**
     * Get ad statistics
     */
    public function getStats($adId) {
        $result = $this->db->query(
            "SELECT COUNT(*) as total_views, SUM(cost) as total_cost 
             FROM ad_views WHERE ad_id = ?",
            [$adId]
        );
        return $result->fetch();
    }
    
    /**
     * List ads by user
     */
    public function listByUser($userId) {
        $sql = "SELECT * FROM ads WHERE user_id = ? ORDER BY created_at DESC";
        return $this->db->query($sql, [$userId])->fetchAll();
    }
    
    /**
     * List pending ads for admin review
     */
    public function listPendingAds() {
        $sql = "SELECT ads.*, users.username as advertiser_name 
                FROM ads 
                JOIN users ON ads.user_id = users.id 
                WHERE ads.status = 'pending' 
                ORDER BY ads.created_at ASC";
        return $this->db->query($sql)->fetchAll();
    }

    public function getAll()
    {
        $stmt = $this->db->query('SELECT * FROM ads');
        return $stmt->fetchAll();
    }
} 