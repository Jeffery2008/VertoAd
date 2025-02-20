<?php
namespace Services;

use Models\Advertisement;
use Models\AdPosition;
use Utils\Logger;
use PDO;

class AdService {
    private $adModel;
    private $positionModel;
    private $logger;
    
    public function __construct() {
        $this->adModel = new Advertisement();
        $this->positionModel = new AdPosition();
        $this->logger = new Logger();
    }
    
    /**
     * Get eligible ads for a position
     */
    public function getEligibleAds($positionId, array $criteria = []) {
        try {
            // Validate position exists
            $position = $this->positionModel->findById($positionId);
            if (!$position) {
                throw new \Exception("Invalid position ID: {$positionId}");
            }
            
            // Build query conditions
            $conditions = [
                'position_id' => $positionId,
                'status' => 'active',
                'start_date <= NOW()',
                'end_date >= NOW()',
                'remaining_budget > 0'
            ];
            
            // Add targeting conditions
            if (!empty($criteria['device_type'])) {
                $conditions[] = "JSON_CONTAINS(targeting, '\"{$criteria['device_type']}\"', '$.device_types')";
            }
            
            if (!empty($criteria['country'])) {
                $conditions[] = "JSON_CONTAINS(targeting, '\"{$criteria['country']}\"', '$.countries')";
            }
            
            // Get matching ads
            $ads = $this->adModel->findWhere($conditions);
            if (empty($ads)) {
                return [];
            }
            
            // Score and rank ads
            $scoredAds = array_map(function($ad) use ($criteria) {
                $score = $this->calculateAdScore($ad, $criteria);
                return ['ad' => $ad, 'score' => $score];
            }, $ads);
            
            usort($scoredAds, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Return top ads
            return array_slice(array_column($scoredAds, 'ad'), 0, 5);
            
        } catch (\Exception $e) {
            $this->logger->error("Error getting eligible ads: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate ad score based on various factors
     */
    private function calculateAdScore($ad, $criteria) {
        $score = 0;
        
        // Base priority score (0-40 points)
        $score += $ad['priority'] * 10;
        
        // CTR score (0-30 points)
        if ($ad['impressions'] > 0) {
            $ctr = $ad['clicks'] / $ad['impressions'];
            $score += $ctr * 30;
        }
        
        // Budget utilization (0-20 points)
        // Prefer ads that haven't used much of their budget
        $budgetUsage = $ad['spent'] / $ad['budget'];
        $score += (1 - $budgetUsage) * 20;
        
        // Targeting match score (0-10 points)
        $targeting = json_decode($ad['targeting'], true);
        if ($targeting) {
            if (!empty($criteria['device_type']) && 
                in_array($criteria['device_type'], $targeting['device_types'] ?? [])) {
                $score += 5;
            }
            if (!empty($criteria['country']) && 
                in_array($criteria['country'], $targeting['countries'] ?? [])) {
                $score += 5;
            }
        }
        
        return $score;
    }
    
    /**
     * Record ad impression
     */
    public function recordImpression($adId, $impressionId, array $metadata = []) {
        try {
            $this->db->beginTransaction();
            
            // Update ad statistics
            $this->db->prepare("
                UPDATE advertisements 
                SET impressions = impressions + 1,
                    last_impression_at = NOW()
                WHERE id = ?
            ")->execute([$adId]);
            
            // Record impression details
            $stmt = $this->db->prepare("
                INSERT INTO ad_impressions (
                    ad_id, impression_id, position_id,
                    device_type, device_os, browser,
                    ip_address, country, region, city
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $adId,
                $impressionId,
                $metadata['position_id'] ?? null,
                $metadata['device_type'] ?? null,
                $metadata['device_os'] ?? null,
                $metadata['browser'] ?? null,
                $metadata['ip_address'] ?? null,
                $metadata['country'] ?? null,
                $metadata['region'] ?? null,
                $metadata['city'] ?? null
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error recording impression: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record ad click
     */
    public function recordClick($adId, $impressionId, array $metadata = []) {
        try {
            $this->db->beginTransaction();
            
            // Update ad statistics
            $this->db->prepare("
                UPDATE advertisements 
                SET clicks = clicks + 1,
                    last_click_at = NOW()
                WHERE id = ?
            ")->execute([$adId]);
            
            // Mark impression as clicked
            $this->db->prepare("
                UPDATE ad_impressions
                SET clicked = true,
                    clicked_at = NOW()
                WHERE impression_id = ?
            ")->execute([$impressionId]);
            
            // Record click details
            $stmt = $this->db->prepare("
                INSERT INTO ad_clicks (
                    ad_id, impression_id,
                    device_type, device_os, browser,
                    ip_address, country, region, city,
                    referrer_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $adId,
                $impressionId,
                $metadata['device_type'] ?? null,
                $metadata['device_os'] ?? null,
                $metadata['browser'] ?? null,
                $metadata['ip_address'] ?? null,
                $metadata['country'] ?? null,
                $metadata['region'] ?? null,
                $metadata['city'] ?? null,
                $metadata['referrer_url'] ?? null
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error recording click: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record ad conversion
     */
    public function recordConversion($adId, $impressionId, array $metadata = []) {
        try {
            $this->db->beginTransaction();
            
            // Update ad statistics
            $this->db->prepare("
                UPDATE advertisements 
                SET conversions = conversions + 1,
                    last_conversion_at = NOW()
                WHERE id = ?
            ")->execute([$adId]);
            
            // Mark impression as converted
            $this->db->prepare("
                UPDATE ad_impressions
                SET converted = true,
                    converted_at = NOW()
                WHERE impression_id = ?
            ")->execute([$impressionId]);
            
            // Record conversion details
            $stmt = $this->db->prepare("
                INSERT INTO ad_conversions (
                    ad_id, impression_id,
                    conversion_type, value,
                    device_type, device_os, browser,
                    ip_address, country, region, city
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $adId,
                $impressionId,
                $metadata['conversion_type'] ?? 'general',
                $metadata['value'] ?? 0,
                $metadata['device_type'] ?? null,
                $metadata['device_os'] ?? null,
                $metadata['browser'] ?? null,
                $metadata['ip_address'] ?? null,
                $metadata['country'] ?? null,
                $metadata['region'] ?? null,
                $metadata['city'] ?? null
            ]);
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error recording conversion: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get ad performance metrics
     */
    public function getPerformanceMetrics($adId, $startDate = null, $endDate = null) {
        try {
            // Get basic metrics
            $sql = "
                SELECT 
                    COUNT(DISTINCT i.impression_id) as total_impressions,
                    COUNT(DISTINCT c.id) as total_clicks,
                    COUNT(DISTINCT v.id) as total_conversions,
                    ROUND(COUNT(DISTINCT c.id) / COUNT(DISTINCT i.impression_id) * 100, 2) as ctr,
                    ROUND(COUNT(DISTINCT v.id) / COUNT(DISTINCT c.id) * 100, 2) as conversion_rate,
                    SUM(v.value) as total_conversion_value
                FROM ad_impressions i
                LEFT JOIN ad_clicks c ON i.impression_id = c.impression_id
                LEFT JOIN ad_conversions v ON i.impression_id = v.impression_id
                WHERE i.ad_id = ?
            ";
            
            $params = [$adId];
            
            if ($startDate) {
                $sql .= " AND i.created_at >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $sql .= " AND i.created_at <= ?";
                $params[] = $endDate;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get device breakdown
            $metrics['devices'] = $this->getDeviceBreakdown($adId, $startDate, $endDate);
            
            // Get geographic breakdown
            $metrics['geography'] = $this->getGeographicBreakdown($adId, $startDate, $endDate);
            
            return $metrics;
            
        } catch (\Exception $e) {
            $this->logger->error("Error getting performance metrics: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get device type breakdown
     */
    private function getDeviceBreakdown($adId, $startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                device_type,
                COUNT(DISTINCT impression_id) as impressions,
                COUNT(DISTINCT CASE WHEN clicked = true THEN impression_id END) as clicks,
                COUNT(DISTINCT CASE WHEN converted = true THEN impression_id END) as conversions
            FROM ad_impressions
            WHERE ad_id = ?
        ";
        
        if ($startDate) {
            $sql .= " AND created_at >= ?";
        }
        if ($endDate) {
            $sql .= " AND created_at <= ?";
        }
        
        $sql .= " GROUP BY device_type";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_filter([$adId, $startDate, $endDate]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get geographic breakdown
     */
    private function getGeographicBreakdown($adId, $startDate = null, $endDate = null) {
        $sql = "
            SELECT 
                country,
                region,
                city,
                COUNT(DISTINCT impression_id) as impressions,
                COUNT(DISTINCT CASE WHEN clicked = true THEN impression_id END) as clicks,
                COUNT(DISTINCT CASE WHEN converted = true THEN impression_id END) as conversions
            FROM ad_impressions
            WHERE ad_id = ?
        ";
        
        if ($startDate) {
            $sql .= " AND created_at >= ?";
        }
        if ($endDate) {
            $sql .= " AND created_at <= ?";
        }
        
        $sql .= " GROUP BY country, region, city";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_filter([$adId, $startDate, $endDate]));
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
