<?php
namespace Models;

use Utils\Logger;
use PDO;

class AdPosition extends BaseModel {
    public $id;
    public $name;
    public $description;
    public $template;
    public $max_ads;
    public $width;
    public $height;
    public $status;
    public $placement_type;
    public $rotation_interval;
    public $price_per_impression;
    public $price_per_click;
    public $created_at;
    public $updated_at;

    protected $table = 'ad_positions';
    protected $fillable = [
        'name',
        'description',
        'template',
        'max_ads',
        'width',
        'height',
        'status',
        'placement_type',
        'rotation_interval',
        'price_per_impression',
        'price_per_click'
    ];

    /**
     * Get all active positions
     * @return array|false Array of active positions or false on error
     */
    public function getActivePositions() {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE status = 'active'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error("Error fetching active positions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get position with its current active ads
     * @param int $positionId Position ID
     * @return array|false Position data with ads or false on error
     */
    public function getPositionWithAds($positionId) {
        try {
            $this->beginTransaction();

            // Get position details
            $position = $this->find($positionId);
            if (!$position) {
                throw new \Exception("Position not found");
            }

            // Get active ads for this position
            $adModel = new Advertisement();
            $ads = $adModel->getActiveAdsForPosition($positionId);

            $position['ads'] = $ads;

            $this->commit();
            return $position;
        } catch (\Exception $e) {
            $this->rollback();
            Logger::error("Error fetching position with ads: " . $e->getMessage(), [
                'position_id' => $positionId
            ]);
            return false;
        }
    }

    /**
     * Get position performance metrics
     * @param int $positionId Position ID
     * @param string|null $startDate Start date (Y-m-d)
     * @param string|null $endDate End date (Y-m-d)
     * @return array|false Performance data or false on error
     */
    public function getPositionPerformance($positionId, $startDate = null, $endDate = null) {
        try {
            $sql = "SELECT 
                    DATE(s.date) as date,
                    p.name as position_name,
                    COUNT(DISTINCT s.ad_id) as total_ads,
                    SUM(s.impressions) as total_impressions,
                    SUM(s.clicks) as total_clicks,
                    SUM(s.conversions) as total_conversions,
                    SUM(s.spent_amount) as total_revenue,
                    ROUND((SUM(s.clicks) / NULLIF(SUM(s.impressions), 0) * 100), 2) as avg_ctr,
                    ROUND((SUM(s.conversions) / NULLIF(SUM(s.clicks), 0) * 100), 2) as avg_conversion_rate
                   FROM {$this->table} p
                   LEFT JOIN advertisements a ON a.position_id = p.id
                   LEFT JOIN ad_statistics s ON s.ad_id = a.id
                   WHERE p.id = ?";
            
            $params = [$positionId];

            if ($startDate) {
                $sql .= " AND s.date >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $sql .= " AND s.date <= ?";
                $params[] = $endDate;
            }

            $sql .= " GROUP BY DATE(s.date), p.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error("Error fetching position performance: " . $e->getMessage(), [
                'position_id' => $positionId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            return false;
        }
    }

    /**
     * Update position status
     * @param int $positionId Position ID
     * @param string $status New status ('active', 'inactive', 'maintenance')
     * @return bool Success status
     */
    public function updateStatus($positionId, $status) {
        try {
            $validStatuses = ['active', 'inactive', 'maintenance'];
            if (!in_array($status, $validStatuses)) {
                throw new \Exception("Invalid status value. Must be one of: " . implode(', ', $validStatuses));
            }
            return $this->update($positionId, ['status' => $status]);
        } catch (\Exception $e) {
            Logger::error("Error updating position status: " . $e->getMessage(), [
                'position_id' => $positionId,
                'status' => $status
            ]);
            return false;
        }
    }

    /**
     * Update position pricing
     * @param int $positionId Position ID
     * @param float $pricePerImpression Price per impression
     * @param float $pricePerClick Price per click
     * @return bool Success status
     */
    public function updatePricing($positionId, $pricePerImpression, $pricePerClick) {
        try {
            if ($pricePerImpression < 0 || $pricePerClick < 0) {
                throw new \Exception("Prices cannot be negative");
            }
            return $this->update($positionId, [
                'price_per_impression' => $pricePerImpression,
                'price_per_click' => $pricePerClick,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            Logger::error("Error updating position pricing: " . $e->getMessage(), [
                'position_id' => $positionId,
                'price_per_impression' => $pricePerImpression,
                'price_per_click' => $pricePerClick
            ]);
            return false;
        }
    }

    /**
     * Get position fill rate
     * @param int $positionId Position ID
     * @param int $days Number of days to analyze
     * @return array|false Fill rate statistics or false on error
     */
    public function getFillRate($positionId, $days = 30) {
        try {
            $sql = "SELECT 
                    COUNT(DISTINCT date) as total_days,
                    COUNT(DISTINCT CASE WHEN total_ads > 0 THEN date END) as days_with_ads,
                    ROUND((COUNT(DISTINCT CASE WHEN total_ads > 0 THEN date END) / 
                           NULLIF(COUNT(DISTINCT date), 0) * 100), 2) as fill_rate
                   FROM (
                       SELECT 
                           DATE(s.date) as date,
                           COUNT(DISTINCT a.id) as total_ads
                       FROM {$this->table} p
                       LEFT JOIN advertisements a ON a.position_id = p.id
                       LEFT JOIN ad_statistics s ON s.ad_id = a.id
                       WHERE p.id = ? 
                       AND s.date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                       GROUP BY DATE(s.date)
                   ) daily_stats";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$positionId, $days]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error("Error calculating fill rate: " . $e->getMessage(), [
                'position_id' => $positionId,
                'days' => $days
            ]);
            return false;
        }
    }

    /**
     * Check if position can accept new ads
     * @param int $positionId Position ID
     * @return bool True if position can accept new ads
     */
    public function canAcceptNewAds($positionId) {
        try {
            $position = $this->find($positionId);
            if (!$position || $position['status'] !== 'active') {
                return false;
            }

            $sql = "SELECT COUNT(*) as active_ads
                   FROM advertisements
                   WHERE position_id = ?
                   AND status = 'active'
                   AND start_date <= NOW()
                   AND end_date >= NOW()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$positionId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)$result['active_ads'] < (int)$position['max_ads'];
        } catch (\Exception $e) {
            Logger::error("Error checking position capacity: " . $e->getMessage(), [
                'position_id' => $positionId
            ]);
            return false;
        }
    }
}
