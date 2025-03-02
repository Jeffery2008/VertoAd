<?php

namespace App\Models;

use App\Core\Database;
use Exception;

class AdView {
    public $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($adId, $placementId, $ipAddress, $userAgent) {
        $stmt = $this->db->query(
            'INSERT INTO impressions (ad_id, placement_id, ip_address, user_agent) VALUES (?, ?, ?, ?)',
            [$adId, $placementId, $ipAddress, $userAgent]
        );
        return $stmt->rowCount() > 0;
    }
    
    public function getViewsForAd($adId) {
        $stmt = $this->db->query(
            'SELECT COUNT(*) as count FROM impressions WHERE ad_id = ?',
            [$adId]
        );
        return $stmt->fetch()['count'];
    }
    
    public function getViewsForPlacement($placementId) {
        $stmt = $this->db->query(
            'SELECT COUNT(*) as count FROM impressions WHERE placement_id = ?',
            [$placementId]
        );
        return $stmt->fetch()['count'];
    }
    
    public function record($adId, $publisherId, $ipAddress) {
        // Get ad details to verify it exists and is active
        $ad = (new Ad())->getById($adId);
        $publisher = (new User())->getById($publisherId);
        
        if (!$ad || !$publisher || $ad['status'] !== 'approved') {
            throw new Exception('Invalid ad or publisher');
        }

        // Check for duplicate views in the last 24 hours
        $result = $this->db->query(
            "SELECT id FROM ad_views 
             WHERE ad_id = ? AND publisher_id = ? AND viewer_ip = ? 
             AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$adId, $publisherId, $ipAddress]
        );
        
        if ($result->fetch()) {
            throw new Exception('Duplicate view detected');
        }
        
        $this->db->query(
            "INSERT INTO ad_views (ad_id, publisher_id, viewer_ip, cost) 
             VALUES (?, ?, ?, ?)",
            [$adId, $publisherId, $ipAddress, $ad['cost_per_view']]
        );
        
        return (int)$this->db->lastInsertId();
    }
    
    public function getById($id) {
        $result = $this->db->query(
            "SELECT * FROM ad_views WHERE id = ?",
            [$id]
        );
        return $result->fetch();
    }
    
    public function getViewsByAd($adId) {
        $result = $this->db->query(
            "SELECT * FROM ad_views WHERE ad_id = ? ORDER BY viewed_at DESC",
            [$adId]
        );
        return $result->fetchAll();
    }
    
    public function getViewsByPublisher($publisherId) {
        $result = $this->db->query(
            "SELECT * FROM ad_views WHERE publisher_id = ? ORDER BY viewed_at DESC",
            [$publisherId]
        );
        return $result->fetchAll();
    }
    
    public function getViewStatistics($adId, $days = 1) {
        $result = $this->db->query(
            "SELECT COUNT(*) as total_views, SUM(cost) as total_cost 
             FROM ad_views 
             WHERE ad_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$adId, $days]
        );
        return $result->fetch();
    }
} 