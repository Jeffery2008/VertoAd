<?php

namespace VertoAD\Core\Models;

use PDO;
use DateTime;

/**
 * Conversion Model
 * 
 * Handles conversion tracking data
 */
class Conversion
{
    /**
     * @var PDO $db Database connection
     */
    private $db;
    
    /**
     * @var int $id Conversion ID
     */
    private $id;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     * @param int $id Optional conversion ID
     */
    public function __construct($db, $id = null)
    {
        $this->db = $db;
        $this->id = $id;
    }
    
    /**
     * Find a conversion by ID
     * 
     * @param int $id Conversion ID
     * @return array|false Conversion data or false if not found
     */
    public function find($id)
    {
        $stmt = $this->db->prepare("
            SELECT c.*, ct.name as conversion_type_name, ct.value_type
            FROM conversions c
            JOIN conversion_types ct ON c.conversion_type_id = ct.id
            WHERE c.id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Record a new conversion
     * 
     * @param array $data Conversion data
     * @return int|false New conversion ID or false on failure
     */
    public function record($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO conversions (
                ad_id, click_id, conversion_type_id, visitor_id, 
                order_id, conversion_value, ip_address, user_agent, 
                referrer, conversion_time, created_at
            ) VALUES (
                :ad_id, :click_id, :conversion_type_id, :visitor_id,
                :order_id, :conversion_value, :ip_address, :user_agent,
                :referrer, :conversion_time, NOW()
            )
        ");
        
        $conversionTime = isset($data['conversion_time']) ? $data['conversion_time'] : date('Y-m-d H:i:s');
        
        $stmt->bindParam(':ad_id', $data['ad_id'], PDO::PARAM_INT);
        $stmt->bindParam(':click_id', $data['click_id'], PDO::PARAM_INT);
        $stmt->bindParam(':conversion_type_id', $data['conversion_type_id'], PDO::PARAM_INT);
        $stmt->bindParam(':visitor_id', $data['visitor_id'], PDO::PARAM_STR);
        $stmt->bindParam(':order_id', $data['order_id'], PDO::PARAM_STR);
        $stmt->bindParam(':conversion_value', $data['conversion_value'], PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $data['ip_address'], PDO::PARAM_STR);
        $stmt->bindParam(':user_agent', $data['user_agent'], PDO::PARAM_STR);
        $stmt->bindParam(':referrer', $data['referrer'], PDO::PARAM_STR);
        $stmt->bindParam(':conversion_time', $conversionTime, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Get conversions by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $filters Optional filters
     * @return array Conversions
     */
    public function getByAdId($adId, $filters = [])
    {
        $sql = "
            SELECT c.*, ct.name as conversion_type_name, ct.value_type
            FROM conversions c
            JOIN conversion_types ct ON c.conversion_type_id = ct.id
            WHERE c.ad_id = :ad_id
        ";
        
        $params = [':ad_id' => $adId];
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND c.conversion_time BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        
        // Apply conversion type filter if provided
        if (!empty($filters['conversion_type_id'])) {
            $sql .= " AND c.conversion_type_id = :conversion_type_id";
            $params[':conversion_type_id'] = $filters['conversion_type_id'];
        }
        
        $sql .= " ORDER BY c.conversion_time DESC";
        
        // Apply limit and offset if provided
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT :limit";
            $params[':limit'] = $filters['limit'];
            
            if (!empty($filters['offset'])) {
                $sql .= " OFFSET :offset";
                $params[':offset'] = $filters['offset'];
            }
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key == ':limit' || $key == ':offset' || $key == ':ad_id' || $key == ':conversion_type_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get conversion count by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $filters Optional filters
     * @return int Conversion count
     */
    public function getCountByAdId($adId, $filters = [])
    {
        $sql = "
            SELECT COUNT(*) as count
            FROM conversions
            WHERE ad_id = :ad_id
        ";
        
        $params = [':ad_id' => $adId];
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND conversion_time BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        
        // Apply conversion type filter if provided
        if (!empty($filters['conversion_type_id'])) {
            $sql .= " AND conversion_type_id = :conversion_type_id";
            $params[':conversion_type_id'] = $filters['conversion_type_id'];
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key == ':ad_id' || $key == ':conversion_type_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int) $result['count'];
    }
    
    /**
     * Get total conversion value by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $filters Optional filters
     * @return float Total conversion value
     */
    public function getTotalValueByAdId($adId, $filters = [])
    {
        $sql = "
            SELECT SUM(conversion_value) as total_value
            FROM conversions
            WHERE ad_id = :ad_id
        ";
        
        $params = [':ad_id' => $adId];
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND conversion_time BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        
        // Apply conversion type filter if provided
        if (!empty($filters['conversion_type_id'])) {
            $sql .= " AND conversion_type_id = :conversion_type_id";
            $params[':conversion_type_id'] = $filters['conversion_type_id'];
        }
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key == ':ad_id' || $key == ':conversion_type_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (float) ($result['total_value'] ?? 0);
    }
    
    /**
     * Get daily conversion data by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $filters Optional filters
     * @return array Daily conversion data
     */
    public function getDailyDataByAdId($adId, $filters = [])
    {
        $sql = "
            SELECT 
                DATE(conversion_time) as date,
                COUNT(*) as conversions,
                SUM(conversion_value) as total_value
            FROM conversions
            WHERE ad_id = :ad_id
        ";
        
        $params = [':ad_id' => $adId];
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND conversion_time BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        
        // Apply conversion type filter if provided
        if (!empty($filters['conversion_type_id'])) {
            $sql .= " AND conversion_type_id = :conversion_type_id";
            $params[':conversion_type_id'] = $filters['conversion_type_id'];
        }
        
        $sql .= " GROUP BY DATE(conversion_time) ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key == ':ad_id' || $key == ':conversion_type_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate conversion rate for an ad
     * 
     * @param int $adId Ad ID
     * @param array $filters Optional filters
     * @return float Conversion rate (percentage)
     */
    public function calculateConversionRate($adId, $filters = [])
    {
        // Get click count
        $clickModel = new Click($this->db);
        $clickCount = $clickModel->getCountByAdId($adId, $filters);
        
        // Get conversion count
        $conversionCount = $this->getCountByAdId($adId, $filters);
        
        // Calculate conversion rate
        if ($clickCount > 0) {
            return ($conversionCount / $clickCount) * 100;
        }
        
        return 0;
    }
    
    /**
     * Calculate ROI for an ad
     * 
     * @param int $adId Ad ID
     * @param float $adCost Total ad cost
     * @param array $filters Optional filters
     * @return float ROI (percentage)
     */
    public function calculateRoi($adId, $adCost, $filters = [])
    {
        // Get total conversion value
        $totalValue = $this->getTotalValueByAdId($adId, $filters);
        
        // Calculate ROI
        if ($adCost > 0) {
            return (($totalValue - $adCost) / $adCost) * 100;
        }
        
        return 0;
    }
    
    /**
     * Get conversion data grouped by type
     * 
     * @param int $adId Ad ID
     * @param array $filters Optional filters
     * @return array Conversion data by type
     */
    public function getDataByType($adId, $filters = [])
    {
        $sql = "
            SELECT 
                ct.id as type_id,
                ct.name as type_name,
                COUNT(c.id) as conversions,
                SUM(c.conversion_value) as total_value
            FROM conversions c
            JOIN conversion_types ct ON c.conversion_type_id = ct.id
            WHERE c.ad_id = :ad_id
        ";
        
        $params = [':ad_id' => $adId];
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $sql .= " AND c.conversion_time BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        
        $sql .= " GROUP BY ct.id ORDER BY conversions DESC";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key == ':ad_id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 