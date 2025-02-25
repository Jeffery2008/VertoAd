<?php

namespace App\Models;

use App\Models\BaseModel;

class Impression extends BaseModel
{
    protected $tableName = 'impressions';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Record a new impression
     * 
     * @param array $data Impression data
     * @return int|bool The new impression ID or false on failure
     */
    public function record(array $data)
    {
        // Ensure required fields
        $requiredFields = ['ad_id', 'position_id', 'ip_address', 'user_agent', 'cost'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $this->logger->error("Missing required field for impression: {$field}");
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
     * Get impressions by ad ID
     * 
     * @param int $adId Advertisement ID
     * @param array $options Additional query options
     * @return array
     */
    public function getByAdId($adId, array $options = [])
    {
        $query = "SELECT * FROM {$this->tableName} WHERE ad_id = :ad_id";
        $params = ['ad_id' => $adId];
        
        // Add date range filter if provided
        if (isset($options['start_date']) && isset($options['end_date'])) {
            $query .= " AND created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $options['start_date'];
            $params['end_date'] = $options['end_date'];
        }
        
        // Add order and limit
        $query .= " ORDER BY created_at DESC";
        if (isset($options['limit'])) {
            $query .= " LIMIT :limit";
            $params['limit'] = $options['limit'];
        }
        
        return $this->db->fetchAll($query, $params);
    }

    /**
     * Get impression count by ad ID
     * 
     * @param int $adId Advertisement ID
     * @param array $options Additional query options
     * @return int
     */
    public function getCountByAdId($adId, array $options = [])
    {
        $query = "SELECT COUNT(*) as count FROM {$this->tableName} WHERE ad_id = :ad_id";
        $params = ['ad_id' => $adId];
        
        // Add date range filter if provided
        if (isset($options['start_date']) && isset($options['end_date'])) {
            $query .= " AND created_at BETWEEN :start_date AND :end_date";
            $params['start_date'] = $options['start_date'];
            $params['end_date'] = $options['end_date'];
        }
        
        $result = $this->db->fetchRow($query, $params);
        return (int)$result['count'];
    }

    /**
     * Get impressions aggregated by date
     * 
     * @param int $adId Advertisement ID
     * @param string $interval Aggregation interval (day, week, month)
     * @param array $options Additional query options
     * @return array
     */
    public function getAggregatedByDate($adId, $interval = 'day', array $options = [])
    {
        // Format date based on interval
        $dateFmt = "DATE(created_at)";
        if ($interval == 'week') {
            $dateFmt = "DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY))";
        } elseif ($interval == 'month') {
            $dateFmt = "DATE_FORMAT(created_at, '%Y-%m-01')";
        }
        
        $query = "SELECT 
                    {$dateFmt} as date, 
                    COUNT(*) as impressions,
                    SUM(cost) as total_cost
                  FROM {$this->tableName} 
                  WHERE ad_id = :ad_id";
        
        $params = ['ad_id' => $adId];
        
        // Add date range filter if provided
        if (isset($options['start_date']) && isset($options['end_date'])) {
            $query .= " AND created_at BETWEEN :start_date AND :end_date";
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

    /**
     * Get geographic distribution of impressions for an ad
     * 
     * @param int $adId Advertisement ID
     * @return array
     */
    public function getGeoDistribution($adId)
    {
        $query = "SELECT 
                    location_country, 
                    location_region,
                    location_city,
                    COUNT(*) as count 
                  FROM {$this->tableName} 
                  WHERE ad_id = :ad_id AND location_country IS NOT NULL
                  GROUP BY location_country, location_region, location_city
                  ORDER BY count DESC";
        
        return $this->db->fetchAll($query, ['ad_id' => $adId]);
    }

    /**
     * Get device distribution of impressions for an ad
     * 
     * @param int $adId Advertisement ID
     * @return array
     */
    public function getDeviceDistribution($adId)
    {
        $query = "SELECT 
                    device_type, 
                    COUNT(*) as count 
                  FROM {$this->tableName} 
                  WHERE ad_id = :ad_id
                  GROUP BY device_type
                  ORDER BY count DESC";
        
        return $this->db->fetchAll($query, ['ad_id' => $adId]);
    }
} 