<?php

namespace App\Models;

use App\Utils\Database;

/**
 * Conversion Model
 * 
 * Manages conversion events tracking
 */
class Conversion
{
    /**
     * @var Database $db Database connection
     */
    private $db;
    
    /**
     * @var int $id Conversion ID
     */
    private $id;
    
    /**
     * Constructor
     * 
     * @param int|null $id Conversion ID
     */
    public function __construct($id = null)
    {
        $this->db = Database::getConnection();
        
        if ($id) {
            $this->id = $id;
        }
    }
    
    /**
     * Get conversion by ID
     * 
     * @param int $id Conversion ID
     * @return array|false Conversion data or false if not found
     */
    public function find($id)
    {
        $sql = "SELECT c.*, ct.name as conversion_type_name 
                FROM conversions c
                JOIN conversion_types ct ON c.conversion_type_id = ct.id
                WHERE c.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        
        if ($result) {
            $this->id = $id;
        }
        
        return $result;
    }
    
    /**
     * Track a conversion event
     * 
     * @param array $data Conversion data
     * @return int|false ID of the new conversion or false on failure
     */
    public function track($data)
    {
        $requiredFields = ['ad_id', 'conversion_type_id', 'ip_address'];
        
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        // Get default value for conversion type if not provided
        if (!isset($data['value']) || $data['value'] === '') {
            $conversionType = new ConversionType($data['conversion_type_id']);
            $typeInfo = $conversionType->find($data['conversion_type_id']);
            
            if ($typeInfo) {
                $data['value'] = $typeInfo['default_value'];
            } else {
                $data['value'] = 0;
            }
        }
        
        $sql = "INSERT INTO conversions 
                (ad_id, click_id, conversion_type_id, visitor_id, order_id, 
                value, ip_address, user_agent, referrer, conversion_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            $data['ad_id'],
            $data['click_id'] ?? null,
            $data['conversion_type_id'],
            $data['visitor_id'] ?? null,
            $data['order_id'] ?? null,
            $data['value'],
            $data['ip_address'],
            $data['user_agent'] ?? null,
            $data['referrer'] ?? null
        ]);
        
        if ($result) {
            $conversionId = $this->db->lastInsertId();
            $this->id = $conversionId;
            
            // Update ROI analytics
            $this->updateRoiAnalytics($data['ad_id'], $data['value']);
            
            return $conversionId;
        }
        
        return false;
    }
    
    /**
     * Get conversions by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Options for filtering and pagination
     * @return array Conversions
     */
    public function getByAdId($adId, $options = [])
    {
        $conditions = ["c.ad_id = ?"];
        $params = [$adId];
        
        // Add date range filter if provided
        if (!empty($options['start_date'])) {
            $conditions[] = "c.conversion_time >= ?";
            $params[] = $options['start_date'];
        }
        
        if (!empty($options['end_date'])) {
            $conditions[] = "c.conversion_time <= ?";
            $params[] = $options['end_date'];
        }
        
        // Add conversion type filter if provided
        if (!empty($options['conversion_type_id'])) {
            $conditions[] = "c.conversion_type_id = ?";
            $params[] = $options['conversion_type_id'];
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        // Add pagination
        $limit = isset($options['limit']) ? (int)$options['limit'] : 50;
        $offset = isset($options['offset']) ? (int)$options['offset'] : 0;
        
        // Add ordering
        $orderBy = isset($options['order_by']) ? $options['order_by'] : 'c.conversion_time';
        $order = isset($options['order']) && strtoupper($options['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT c.*, ct.name as conversion_type_name 
                FROM conversions c
                JOIN conversion_types ct ON c.conversion_type_id = ct.id
                WHERE {$whereClause}
                ORDER BY {$orderBy} {$order}
                LIMIT {$limit} OFFSET {$offset}";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total conversions count and value by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Options for filtering
     * @return array Conversion summary
     */
    public function getSummaryByAdId($adId, $options = [])
    {
        $conditions = ["c.ad_id = ?"];
        $params = [$adId];
        
        // Add date range filter if provided
        if (!empty($options['start_date'])) {
            $conditions[] = "c.conversion_time >= ?";
            $params[] = $options['start_date'];
        }
        
        if (!empty($options['end_date'])) {
            $conditions[] = "c.conversion_time <= ?";
            $params[] = $options['end_date'];
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        $sql = "SELECT 
                    COUNT(c.id) as total_conversions,
                    SUM(c.value) as total_value
                FROM conversions c
                WHERE {$whereClause}";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
    
    /**
     * Get conversions by advertiser ID
     * 
     * @param int $advertiserId Advertiser ID
     * @param array $options Options for filtering and pagination
     * @return array Conversions
     */
    public function getByAdvertiserId($advertiserId, $options = [])
    {
        $conditions = ["a.advertiser_id = ?"];
        $params = [$advertiserId];
        
        // Add date range filter if provided
        if (!empty($options['start_date'])) {
            $conditions[] = "c.conversion_time >= ?";
            $params[] = $options['start_date'];
        }
        
        if (!empty($options['end_date'])) {
            $conditions[] = "c.conversion_time <= ?";
            $params[] = $options['end_date'];
        }
        
        // Add conversion type filter if provided
        if (!empty($options['conversion_type_id'])) {
            $conditions[] = "c.conversion_type_id = ?";
            $params[] = $options['conversion_type_id'];
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        // Add pagination
        $limit = isset($options['limit']) ? (int)$options['limit'] : 50;
        $offset = isset($options['offset']) ? (int)$options['offset'] : 0;
        
        // Add ordering
        $orderBy = isset($options['order_by']) ? $options['order_by'] : 'c.conversion_time';
        $order = isset($options['order']) && strtoupper($options['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT c.*, a.title as ad_title, ct.name as conversion_type_name 
                FROM conversions c
                JOIN advertisements a ON c.ad_id = a.id
                JOIN conversion_types ct ON c.conversion_type_id = ct.id
                WHERE {$whereClause}
                ORDER BY {$orderBy} {$order}
                LIMIT {$limit} OFFSET {$offset}";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get conversion rate for an ad
     * 
     * @param int $adId Ad ID
     * @param array $options Options for filtering
     * @return float Conversion rate (percentage)
     */
    public function getConversionRate($adId, $options = [])
    {
        // First, get the number of clicks
        $conditions = ["ad_id = ?"];
        $params = [$adId];
        
        // Add date range filter if provided
        if (!empty($options['start_date'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $options['start_date'];
        }
        
        if (!empty($options['end_date'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $options['end_date'];
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        $sql = "SELECT COUNT(*) as total_clicks FROM ad_clicks WHERE {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $clicksResult = $stmt->fetch();
        
        $totalClicks = (int)$clicksResult['total_clicks'];
        
        if ($totalClicks === 0) {
            return 0; // Avoid division by zero
        }
        
        // Now get the number of conversions for the same period
        $conditions = ["ad_id = ?"];
        $params = [$adId];
        
        if (!empty($options['start_date'])) {
            $conditions[] = "conversion_time >= ?";
            $params[] = $options['start_date'];
        }
        
        if (!empty($options['end_date'])) {
            $conditions[] = "conversion_time <= ?";
            $params[] = $options['end_date'];
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        $sql = "SELECT COUNT(*) as total_conversions FROM conversions WHERE {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $conversionsResult = $stmt->fetch();
        
        $totalConversions = (int)$conversionsResult['total_conversions'];
        
        // Calculate conversion rate as a percentage
        return ($totalConversions / $totalClicks) * 100;
    }
    
    /**
     * Get daily conversion data for an ad
     * 
     * @param int $adId Ad ID
     * @param array $options Options for filtering
     * @return array Daily conversion data
     */
    public function getDailyConversionData($adId, $options = [])
    {
        $conditions = ["c.ad_id = ?"];
        $params = [$adId];
        
        // Add date range filter if provided
        if (!empty($options['start_date'])) {
            $conditions[] = "c.conversion_time >= ?";
            $params[] = $options['start_date'];
        }
        
        if (!empty($options['end_date'])) {
            $conditions[] = "c.conversion_time <= ?";
            $params[] = $options['end_date'];
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        $sql = "SELECT 
                    DATE(c.conversion_time) as date,
                    COUNT(c.id) as conversions,
                    SUM(c.value) as value
                FROM conversions c
                WHERE {$whereClause}
                GROUP BY DATE(c.conversion_time)
                ORDER BY date ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Update ROI analytics after a conversion
     * 
     * @param int $adId Ad ID
     * @param float $value Conversion value
     * @return bool Success
     */
    private function updateRoiAnalytics($adId, $value)
    {
        // Get today's date
        $today = date('Y-m-d');
        
        // Check if there's already a record for today
        $sql = "SELECT id FROM roi_analytics WHERE ad_id = ? AND date = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$adId, $today]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing record
            $sql = "UPDATE roi_analytics 
                    SET conversions = conversions + 1, 
                        revenue = revenue + ? 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$value, $existing['id']]);
        } else {
            // Get impression and click counts for today
            $sql = "SELECT COUNT(*) as count FROM ad_impressions 
                    WHERE ad_id = ? AND DATE(created_at) = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adId, $today]);
            $impressions = (int)$stmt->fetch()['count'];
            
            $sql = "SELECT COUNT(*) as count FROM ad_clicks 
                    WHERE ad_id = ? AND DATE(created_at) = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adId, $today]);
            $clicks = (int)$stmt->fetch()['count'];
            
            // Get today's spend
            $sql = "SELECT SUM(cost) as spend FROM ad_impressions 
                    WHERE ad_id = ? AND DATE(created_at) = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$adId, $today]);
            $spend = (float)$stmt->fetch()['spend'];
            
            // Create new record
            $sql = "INSERT INTO roi_analytics 
                    (ad_id, date, impressions, clicks, conversions, spend, revenue) 
                    VALUES (?, ?, ?, ?, 1, ?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$adId, $today, $impressions, $clicks, $spend, $value]);
        }
    }
} 