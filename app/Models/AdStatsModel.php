<?php

namespace App\Models;

use App\Core\Database;

class AdStatsModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 记录广告展示
     */
    public function logView($adId) {
        $sql = "INSERT INTO ad_views (ad_id, view_time, ip_address) VALUES (?, NOW(), ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$adId, $_SERVER['REMOTE_ADDR']]);
    }

    /**
     * 记录广告点击
     */
    public function logClick($adId) {
        $sql = "INSERT INTO ad_clicks (ad_id, click_time, ip_address) VALUES (?, NOW(), ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$adId, $_SERVER['REMOTE_ADDR']]);
    }

    /**
     * 获取广告统计数据
     */
    public function getStats($adId) {
        $viewsSql = "SELECT COUNT(*) as views FROM ad_views WHERE ad_id = ?";
        $clicksSql = "SELECT COUNT(*) as clicks FROM ad_clicks WHERE ad_id = ?";
        
        $viewsStmt = $this->db->prepare($viewsSql);
        $clicksStmt = $this->db->prepare($clicksSql);
        
        $viewsStmt->execute([$adId]);
        $clicksStmt->execute([$adId]);
        
        $views = $viewsStmt->fetch(\PDO::FETCH_ASSOC)['views'];
        $clicks = $clicksStmt->fetch(\PDO::FETCH_ASSOC)['clicks'];
        
        return [
            'views' => $views,
            'clicks' => $clicks,
            'ctr' => $views > 0 ? round(($clicks / $views) * 100, 2) : 0
        ];
    }
} 