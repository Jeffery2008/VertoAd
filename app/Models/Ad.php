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

    /**
     * 获取广告位的所有活跃广告
     */
    public function getActiveAdsForZone($zoneId) {
        $sql = "SELECT a.* FROM ads a
                INNER JOIN ad_rules ar ON a.id = ar.ad_id
                WHERE ar.zone_id = ? 
                AND a.status = 'active'
                AND ar.status = 'active'
                AND (ar.start_date IS NULL OR ar.start_date <= NOW())
                AND (ar.end_date IS NULL OR ar.end_date >= NOW())
                AND (ar.daily_budget IS NULL OR (
                    SELECT COALESCE(SUM(cost), 0)
                    FROM ad_costs
                    WHERE ad_id = a.id
                    AND DATE(created_at) = CURDATE()
                ) < ar.daily_budget)
                AND (ar.total_budget IS NULL OR (
                    SELECT COALESCE(SUM(cost), 0)
                    FROM ad_costs
                    WHERE ad_id = a.id
                ) < ar.total_budget)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$zoneId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 创建新广告
     */
    public function create($userId, $title, $description, $imageUrl, $targetUrl) {
        $sql = "INSERT INTO ads (
            advertiser_id, title, description, image_url, target_url
        ) VALUES (?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $title, $description, $imageUrl, $targetUrl]);
        return $this->db->lastInsertId();
    }

    /**
     * 更新广告
     */
    public function update($id, $title, $description, $imageUrl, $targetUrl) {
        $sql = "UPDATE ads SET 
            title = ?,
            description = ?,
            image_url = ?,
            target_url = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$title, $description, $imageUrl, $targetUrl, $id]);
    }

    /**
     * 获取用户的广告列表
     */
    public function listByUser($userId) {
        $sql = "SELECT * FROM ads WHERE advertiser_id = ? ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 删除广告
     */
    public function delete($id) {
        $sql = "UPDATE ads SET status = 'deleted' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 提交广告审核
     */
    public function submit($id) {
        $sql = "UPDATE ads SET status = 'pending' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 审核通过
     */
    public function approve($id) {
        $sql = "UPDATE ads SET status = 'active' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 审核拒绝
     */
    public function reject($id) {
        $sql = "UPDATE ads SET status = 'rejected' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 暂停广告
     */
    public function pause($id) {
        $sql = "UPDATE ads SET status = 'paused' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * 恢复广告
     */
    public function resume($id) {
        $sql = "UPDATE ads SET status = 'active' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
} 