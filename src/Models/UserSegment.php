<?php

namespace App\Models;

use PDO;
use App\Utils\Database;
use App\Utils\Logger;

/**
 * UserSegment Model
 * 
 * Handles user segmentation operations for targeted advertising
 */
class UserSegment extends BaseModel
{
    private $db;
    private $logger;
    private $id;
    
    /**
     * Initialize the UserSegment model
     * 
     * @param int|null $id Segment ID
     */
    public function __construct($id = null)
    {
        parent::__construct();
        $this->db = new Database();
        $this->logger = new Logger();
        $this->id = $id;
    }
    
    /**
     * Find a segment by ID
     * 
     * @param int $id Segment ID
     * @return array|null Segment data
     */
    public function find($id)
    {
        try {
            $query = "SELECT * FROM user_segments WHERE id = :id";
            return $this->db->fetchOne($query, ['id' => $id]);
        } catch (\Exception $e) {
            $this->logger->error('Error finding segment: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all segments for a user
     * 
     * @param int $userId User ID
     * @return array Segments
     */
    public function getByUserId($userId)
    {
        try {
            $query = "SELECT * FROM user_segments WHERE user_id = :user_id ORDER BY name ASC";
            return $this->db->fetchAll($query, ['user_id' => $userId]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting segments for user: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Create a new segment
     * 
     * @param array $data Segment data
     * @return int|bool New segment ID or false on failure
     */
    public function create($data)
    {
        try {
            // Ensure criteria is stored as JSON
            if (isset($data['criteria']) && is_array($data['criteria'])) {
                $data['criteria'] = json_encode($data['criteria']);
            }
            
            $query = "INSERT INTO user_segments (name, description, user_id, criteria, is_dynamic, last_updated) 
                     VALUES (:name, :description, :user_id, :criteria, :is_dynamic, NOW())";
            
            $params = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'user_id' => $data['user_id'],
                'criteria' => $data['criteria'],
                'is_dynamic' => $data['is_dynamic'] ?? 1
            ];
            
            return $this->db->insert($query, $params);
        } catch (\Exception $e) {
            $this->logger->error('Error creating segment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an existing segment
     * 
     * @param array $data Updated segment data
     * @return bool Success
     */
    public function update($data)
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            // Ensure criteria is stored as JSON
            if (isset($data['criteria']) && is_array($data['criteria'])) {
                $data['criteria'] = json_encode($data['criteria']);
            }
            
            $query = "UPDATE user_segments SET 
                     name = :name,
                     description = :description,
                     criteria = :criteria,
                     is_dynamic = :is_dynamic,
                     last_updated = NOW()
                     WHERE id = :id";
            
            $params = [
                'id' => $this->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'criteria' => $data['criteria'],
                'is_dynamic' => $data['is_dynamic'] ?? 1
            ];
            
            return $this->db->execute($query, $params);
        } catch (\Exception $e) {
            $this->logger->error('Error updating segment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a segment
     * 
     * @return bool Success
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            $query = "DELETE FROM user_segments WHERE id = :id";
            return $this->db->execute($query, ['id' => $this->id]);
        } catch (\Exception $e) {
            $this->logger->error('Error deleting segment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get segment members count
     * 
     * @return int Count of members
     */
    public function getMembersCount()
    {
        if (!$this->id) {
            return 0;
        }
        
        try {
            $query = "SELECT COUNT(*) AS count FROM segment_members WHERE segment_id = :segment_id";
            $result = $this->db->fetchOne($query, ['segment_id' => $this->id]);
            return $result ? (int)$result['count'] : 0;
        } catch (\Exception $e) {
            $this->logger->error('Error getting segment members count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get segment members
     * 
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Members data
     */
    public function getMembers($limit = 100, $offset = 0)
    {
        if (!$this->id) {
            return [];
        }
        
        try {
            $query = "SELECT sm.*, vp.first_seen, vp.last_seen, vp.visit_count, vp.geo_country, vp.geo_city, vp.device_type
                     FROM segment_members sm
                     LEFT JOIN visitor_profiles vp ON sm.visitor_id = vp.visitor_id
                     WHERE sm.segment_id = :segment_id
                     ORDER BY sm.added_at DESC
                     LIMIT :limit OFFSET :offset";
            
            return $this->db->fetchAll($query, [
                'segment_id' => $this->id,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting segment members: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add a visitor to a segment
     * 
     * @param string $visitorId Visitor ID
     * @return bool Success
     */
    public function addMember($visitorId)
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            $query = "INSERT IGNORE INTO segment_members (segment_id, visitor_id)
                     VALUES (:segment_id, :visitor_id)";
            
            return $this->db->execute($query, [
                'segment_id' => $this->id,
                'visitor_id' => $visitorId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error adding member to segment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Remove a visitor from a segment
     * 
     * @param string $visitorId Visitor ID
     * @return bool Success
     */
    public function removeMember($visitorId)
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            $query = "DELETE FROM segment_members 
                     WHERE segment_id = :segment_id AND visitor_id = :visitor_id";
            
            return $this->db->execute($query, [
                'segment_id' => $this->id,
                'visitor_id' => $visitorId
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error removing member from segment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Link a segment to an ad for targeting
     * 
     * @param int $adId Ad ID
     * @return bool Success
     */
    public function linkToAd($adId)
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            $query = "INSERT IGNORE INTO segment_targeting (ad_id, segment_id)
                     VALUES (:ad_id, :segment_id)";
            
            return $this->db->execute($query, [
                'ad_id' => $adId,
                'segment_id' => $this->id
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error linking segment to ad: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unlink a segment from an ad
     * 
     * @param int $adId Ad ID
     * @return bool Success
     */
    public function unlinkFromAd($adId)
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            $query = "DELETE FROM segment_targeting 
                     WHERE ad_id = :ad_id AND segment_id = :segment_id";
            
            return $this->db->execute($query, [
                'ad_id' => $adId,
                'segment_id' => $this->id
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error unlinking segment from ad: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get ads targeted to this segment
     * 
     * @return array Ads
     */
    public function getTargetedAds()
    {
        if (!$this->id) {
            return [];
        }
        
        try {
            $query = "SELECT a.*, st.created_at as targeting_created_at 
                     FROM segment_targeting st
                     JOIN advertisements a ON st.ad_id = a.id
                     WHERE st.segment_id = :segment_id
                     ORDER BY st.created_at DESC";
            
            return $this->db->fetchAll($query, ['segment_id' => $this->id]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting targeted ads: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get performance metrics for this segment
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Performance data
     */
    public function getPerformanceMetrics($startDate, $endDate)
    {
        if (!$this->id) {
            return [];
        }
        
        try {
            $query = "SELECT 
                     ad_id,
                     SUM(impressions) as total_impressions,
                     SUM(clicks) as total_clicks,
                     AVG(ctr) as avg_ctr,
                     SUM(conversions) as total_conversions,
                     AVG(conversion_rate) as avg_conversion_rate,
                     SUM(conversion_value) as total_value,
                     SUM(cost) as total_cost,
                     AVG(roi) as avg_roi
                     FROM segment_performance
                     WHERE segment_id = :segment_id
                     AND date BETWEEN :start_date AND :end_date
                     GROUP BY ad_id";
            
            return $this->db->fetchAll($query, [
                'segment_id' => $this->id,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting segment performance: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update segment members based on criteria (for dynamic segments)
     * 
     * @return bool Success
     */
    public function updateDynamicMembers()
    {
        if (!$this->id) {
            return false;
        }
        
        try {
            $segment = $this->find($this->id);
            
            if (!$segment || !$segment['is_dynamic']) {
                return false;
            }
            
            // Parse criteria
            $criteria = json_decode($segment['criteria'], true);
            if (!$criteria) {
                return false;
            }
            
            // Build WHERE clause based on criteria
            list($whereClause, $params) = $this->buildCriteriaWhere($criteria);
            
            // Clear existing members
            $clearQuery = "DELETE FROM segment_members WHERE segment_id = :segment_id";
            $this->db->execute($clearQuery, ['segment_id' => $this->id]);
            
            // Insert new members based on criteria
            $insertQuery = "INSERT INTO segment_members (segment_id, visitor_id)
                           SELECT :segment_id, visitor_id
                           FROM visitor_profiles
                           WHERE {$whereClause}";
            
            $params['segment_id'] = $this->id;
            
            $success = $this->db->execute($insertQuery, $params);
            
            // Update last_updated timestamp
            $updateQuery = "UPDATE user_segments SET last_updated = NOW() WHERE id = :id";
            $this->db->execute($updateQuery, ['id' => $this->id]);
            
            return $success;
        } catch (\Exception $e) {
            $this->logger->error('Error updating dynamic segment: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Build WHERE clause from segment criteria
     * 
     * @param array $criteria Segment criteria
     * @return array [where_clause, params]
     */
    private function buildCriteriaWhere($criteria)
    {
        $conditions = [];
        $params = [];
        $i = 0;
        
        foreach ($criteria as $criterion) {
            if (empty($criterion['field']) || empty($criterion['operator'])) {
                continue;
            }
            
            $field = $criterion['field'];
            $operator = $criterion['operator'];
            $value = $criterion['value'] ?? null;
            $paramName = 'param' . $i++;
            
            switch ($operator) {
                case 'equals':
                    $conditions[] = "{$field} = :{$paramName}";
                    $params[$paramName] = $value;
                    break;
                case 'not_equals':
                    $conditions[] = "{$field} != :{$paramName}";
                    $params[$paramName] = $value;
                    break;
                case 'contains':
                    $conditions[] = "{$field} LIKE :{$paramName}";
                    $params[$paramName] = "%{$value}%";
                    break;
                case 'starts_with':
                    $conditions[] = "{$field} LIKE :{$paramName}";
                    $params[$paramName] = "{$value}%";
                    break;
                case 'greater_than':
                    $conditions[] = "{$field} > :{$paramName}";
                    $params[$paramName] = $value;
                    break;
                case 'less_than':
                    $conditions[] = "{$field} < :{$paramName}";
                    $params[$paramName] = $value;
                    break;
                case 'between':
                    if (isset($criterion['value2'])) {
                        $paramName2 = 'param' . $i++;
                        $conditions[] = "{$field} BETWEEN :{$paramName} AND :{$paramName2}";
                        $params[$paramName] = $value;
                        $params[$paramName2] = $criterion['value2'];
                    }
                    break;
                case 'in':
                    if (is_array($value)) {
                        $placeholders = [];
                        foreach ($value as $j => $val) {
                            $placeholderName = "{$paramName}_{$j}";
                            $placeholders[] = ":{$placeholderName}";
                            $params[$placeholderName] = $val;
                        }
                        if ($placeholders) {
                            $conditions[] = "{$field} IN (" . implode(", ", $placeholders) . ")";
                        }
                    }
                    break;
                case 'exists':
                    $conditions[] = "{$field} IS NOT NULL";
                    break;
                case 'not_exists':
                    $conditions[] = "{$field} IS NULL";
                    break;
            }
        }
        
        $whereClause = count($conditions) > 0 ? implode(' AND ', $conditions) : '1=1';
        
        return [$whereClause, $params];
    }
} 