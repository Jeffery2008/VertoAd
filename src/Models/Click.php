<?php

namespace App\Models;

use App\Models\BaseModel;

class Click extends BaseModel
{
    protected $tableName = 'clicks';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Record a new click
     * 
     * @param array $data Click data
     * @return int|bool The new click ID or false on failure
     */
    public function record(array $data)
    {
        // Ensure required fields
        $requiredFields = ['impression_id', 'ip_address', 'user_agent'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->logger->error("Missing required field for click: {$field}");
                return false;
            }
        }

        // Add viewer_id if not set
        if (!isset($data['viewer_id'])) {
            $data['viewer_id'] = $this->generateViewerId($data['ip_address'], $data['user_agent']);
        }
        
        // Add timestamp
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert($this->tableName, $data);
    }

    /**
     * Get clicks for an ad
     * 
     * @param int $adId Advertisement ID
     * @param array $options Additional query options
     * @return array
     */
    public function getByAdId($adId, array $options = [])
    {
        $query = "SELECT c.* FROM {$this->tableName} c
                  JOIN impressions i ON c.impression_id = i.id
                  WHERE i.ad_id = :ad_id";
        
        $params = ['ad_id' => $adId];
        
        // Add date range filter if provided
        if (isset($options['start_date']) && isset($options['end_date'])) {
            $query .= " AND c.created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $options['start_date'];
            $params['end_date'] = $options['end_date'];
        }
        
        // Add order and limit
        $query .= " ORDER BY c.created_at DESC";
        if (isset($options['limit'])) {
            $query .= " LIMIT :limit";
            $params['limit'] = $options['limit'];
        }
        
        return $this->db->fetchAll($query, $params);
    }

    /**
     * Get click count for an ad
     * 
     * @param int $adId Advertisement ID
     * @param array $options Additional query options
     * @return int
     */
    public function getCountByAdId($adId, array $options = [])
    {
        $query = "SELECT COUNT(*) as count FROM {$this->tableName} c
                  JOIN impressions i ON c.impression_id = i.id
                  WHERE i.ad_id = :ad_id";
        
        $params = ['ad_id' => $adId];
        
        // Add date range filter if provided
        if (isset($options['start_date']) && isset($options['end_date'])) {
            $query .= " AND c.created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $options['start_date'];
            $params['end_date'] = $options['end_date'];
        }
        
        $result = $this->db->fetchRow($query, $params);
        return (int)$result['count'];
    }

    /**
     * Get click-through rate (CTR) for an ad
     * 
     * @param int $adId Advertisement ID
     * @param array $options Additional query options
     * @return float
     */
    public function getCtrByAdId($adId, array $options = [])
    {
        // Get click count
        $clickCount = $this->getCountByAdId($adId, $options);
        
        // Get impression count
        $impressionModel = new Impression();
        $impressionCount = $impressionModel->getCountByAdId($adId, $options);
        
        // Calculate CTR (avoid division by zero)
        if ($impressionCount > 0) {
            return round(($clickCount / $impressionCount) * 100, 2);
        }
        
        return 0;
    }

    /**
     * Get clicks aggregated by date
     * 
     * @param int $adId Advertisement ID
     * @param string $interval Aggregation interval (day, week, month)
     * @param array $options Additional query options
     * @return array
     */
    public function getAggregatedByDate($adId, $interval = 'day', array $options = [])
    {
        // Format date based on interval
        $dateFmt = "DATE(c.created_at)";
        if ($interval == 'week') {
            $dateFmt = "DATE(DATE_SUB(c.created_at, INTERVAL WEEKDAY(c.created_at) DAY))";
        } elseif ($interval == 'month') {
            $dateFmt = "DATE_FORMAT(c.created_at, '%Y-%m-01')";
        }
        
        $query = "SELECT 
                    {$dateFmt} as date, 
                    COUNT(*) as clicks
                  FROM {$this->tableName} c
                  JOIN impressions i ON c.impression_id = i.id
                  WHERE i.ad_id = :ad_id";
        
        $params = ['ad_id' => $adId];
        
        // Add date range filter if provided
        if (isset($options['start_date']) && isset($options['end_date'])) {
            $query .= " AND c.created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $options['start_date'];
            $params['end_date'] = $options['end_date'];
        }
        
        $query .= " GROUP BY date ORDER BY date";
        
        return $this->db->fetchAll($query, $params);
    }

    /**
     * Generate a consistent viewer ID from IP and user agent
     * 
     * @param string $ip IP address
     * @param string $userAgent User agent string
     * @return string Hashed viewer ID
     */
    protected function generateViewerId($ip, $userAgent)
    {
        // Simple hashing to maintain some privacy while still identifying unique viewers
        return hash('sha256', $ip . $userAgent . date('Y-m-d'));
    }
} 