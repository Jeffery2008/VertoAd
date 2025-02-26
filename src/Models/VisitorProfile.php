<?php

namespace VertoAD\Core\Models;

use PDO;
use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Logger;

/**
 * VisitorProfile Model
 * 
 * Handles visitor profile data for audience segmentation
 */
class VisitorProfile extends BaseModel
{
    private $db;
    private $logger;
    private $visitorId;
    
    /**
     * Initialize the VisitorProfile model
     * 
     * @param string|null $visitorId Visitor ID
     */
    public function __construct($visitorId = null)
    {
        parent::__construct();
        $this->db = new Database();
        $this->logger = new Logger();
        $this->visitorId = $visitorId;
    }
    
    /**
     * Find a visitor profile by ID
     * 
     * @param string $visitorId Visitor ID
     * @return array|null Visitor profile data
     */
    public function find($visitorId)
    {
        try {
            $query = "SELECT * FROM visitor_profiles WHERE visitor_id = :visitor_id";
            return $this->db->fetchOne($query, ['visitor_id' => $visitorId]);
        } catch (\Exception $e) {
            $this->logger->error('Error finding visitor profile: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create or update a visitor profile
     * 
     * @param array $data Visitor profile data
     * @return bool Success
     */
    public function createOrUpdate($data)
    {
        if (!isset($data['visitor_id'])) {
            return false;
        }
        
        $this->visitorId = $data['visitor_id'];
        
        try {
            // Check if visitor exists
            $exists = $this->find($this->visitorId);
            
            if ($exists) {
                return $this->update($data);
            } else {
                return $this->create($data);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error creating/updating visitor profile: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a new visitor profile
     * 
     * @param array $data Visitor profile data
     * @return bool Success
     */
    public function create($data)
    {
        if (!isset($data['visitor_id'])) {
            return false;
        }
        
        // Convert JSON arrays
        if (isset($data['interests']) && is_array($data['interests'])) {
            $data['interests'] = json_encode($data['interests']);
        }
        
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        
        if (isset($data['custom_attributes']) && is_array($data['custom_attributes'])) {
            $data['custom_attributes'] = json_encode($data['custom_attributes']);
        }
        
        try {
            $columns = [];
            $placeholders = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if ($value !== null) {
                    $columns[] = $key;
                    $placeholders[] = ":{$key}";
                    $params[$key] = $value;
                }
            }
            
            $columnsStr = implode(', ', $columns);
            $placeholdersStr = implode(', ', $placeholders);
            
            $query = "INSERT INTO visitor_profiles ({$columnsStr}) VALUES ({$placeholdersStr})";
            
            return $this->db->execute($query, $params);
        } catch (\Exception $e) {
            $this->logger->error('Error creating visitor profile: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update an existing visitor profile
     * 
     * @param array $data Updated visitor profile data
     * @return bool Success
     */
    public function update($data)
    {
        if (!$this->visitorId && !isset($data['visitor_id'])) {
            return false;
        }
        
        $visitorId = $this->visitorId ?: $data['visitor_id'];
        
        // Convert JSON arrays
        if (isset($data['interests']) && is_array($data['interests'])) {
            $data['interests'] = json_encode($data['interests']);
        }
        
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        
        if (isset($data['custom_attributes']) && is_array($data['custom_attributes'])) {
            $data['custom_attributes'] = json_encode($data['custom_attributes']);
        }
        
        try {
            $updates = [];
            $params = ['visitor_id' => $visitorId];
            
            foreach ($data as $key => $value) {
                // Don't update the primary key
                if ($key !== 'visitor_id' && $value !== null) {
                    $updates[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                }
            }
            
            // If there's nothing to update, return success
            if (empty($updates)) {
                return true;
            }
            
            $updatesStr = implode(', ', $updates);
            
            $query = "UPDATE visitor_profiles SET {$updatesStr} WHERE visitor_id = :visitor_id";
            
            return $this->db->execute($query, $params);
        } catch (\Exception $e) {
            $this->logger->error('Error updating visitor profile: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record a visitor event
     * 
     * @param array $data Event data
     * @return int|bool Event ID or false on failure
     */
    public function recordEvent($data)
    {
        if (!isset($data['visitor_id']) || !isset($data['event_type'])) {
            return false;
        }
        
        try {
            $columns = [];
            $placeholders = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if ($value !== null) {
                    $columns[] = $key;
                    $placeholders[] = ":{$key}";
                    $params[$key] = $value;
                }
            }
            
            $columnsStr = implode(', ', $columns);
            $placeholdersStr = implode(', ', $placeholders);
            
            $query = "INSERT INTO visitor_events ({$columnsStr}) VALUES ({$placeholdersStr})";
            
            return $this->db->insert($query, $params);
        } catch (\Exception $e) {
            $this->logger->error('Error recording visitor event: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get events for a visitor
     * 
     * @param string|null $eventType Filter by event type
     * @param int $limit Limit results
     * @param int $offset Offset for pagination
     * @return array Events
     */
    public function getEvents($eventType = null, $limit = 100, $offset = 0)
    {
        if (!$this->visitorId) {
            return [];
        }
        
        try {
            $params = ['visitor_id' => $this->visitorId, 'limit' => $limit, 'offset' => $offset];
            $whereClause = "visitor_id = :visitor_id";
            
            if ($eventType) {
                $whereClause .= " AND event_type = :event_type";
                $params['event_type'] = $eventType;
            }
            
            $query = "SELECT * FROM visitor_events 
                     WHERE {$whereClause}
                     ORDER BY occurred_at DESC
                     LIMIT :limit OFFSET :offset";
            
            return $this->db->fetchAll($query, $params);
        } catch (\Exception $e) {
            $this->logger->error('Error getting visitor events: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get segments that a visitor belongs to
     * 
     * @return array Segments
     */
    public function getSegments()
    {
        if (!$this->visitorId) {
            return [];
        }
        
        try {
            $query = "SELECT us.* 
                     FROM segment_members sm
                     JOIN user_segments us ON sm.segment_id = us.id
                     WHERE sm.visitor_id = :visitor_id";
            
            return $this->db->fetchAll($query, ['visitor_id' => $this->visitorId]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting visitor segments: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update visitor interests
     * 
     * @param array $interests Interest categories
     * @return bool Success
     */
    public function updateInterests($interests)
    {
        if (!$this->visitorId) {
            return false;
        }
        
        try {
            // Get existing interests
            $profile = $this->find($this->visitorId);
            
            if (!$profile) {
                return false;
            }
            
            $existingInterests = $profile['interests'] ? json_decode($profile['interests'], true) : [];
            
            // Merge with new interests
            $mergedInterests = array_unique(array_merge($existingInterests, $interests));
            
            // Update profile
            return $this->update(['interests' => $mergedInterests]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating visitor interests: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add tags to visitor profile
     * 
     * @param array $tags Tags to add
     * @return bool Success
     */
    public function addTags($tags)
    {
        if (!$this->visitorId) {
            return false;
        }
        
        try {
            // Get existing tags
            $profile = $this->find($this->visitorId);
            
            if (!$profile) {
                return false;
            }
            
            $existingTags = $profile['tags'] ? json_decode($profile['tags'], true) : [];
            
            // Merge with new tags
            $mergedTags = array_unique(array_merge($existingTags, $tags));
            
            // Update profile
            return $this->update(['tags' => $mergedTags]);
        } catch (\Exception $e) {
            $this->logger->error('Error adding visitor tags: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update visitor's custom attributes
     * 
     * @param array $attributes Custom attributes
     * @return bool Success
     */
    public function updateCustomAttributes($attributes)
    {
        if (!$this->visitorId) {
            return false;
        }
        
        try {
            // Get existing attributes
            $profile = $this->find($this->visitorId);
            
            if (!$profile) {
                return false;
            }
            
            $existingAttributes = $profile['custom_attributes'] ? json_decode($profile['custom_attributes'], true) : [];
            
            // Merge with new attributes
            $mergedAttributes = array_merge($existingAttributes, $attributes);
            
            // Update profile
            return $this->update(['custom_attributes' => $mergedAttributes]);
        } catch (\Exception $e) {
            $this->logger->error('Error updating visitor custom attributes: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get top visitor locations
     * 
     * @param int $limit Limit results
     * @return array Location data with counts
     */
    public static function getTopLocations($limit = 10)
    {
        try {
            $db = new Database();
            $query = "SELECT 
                     geo_country,
                     geo_region,
                     geo_city,
                     COUNT(*) as count
                     FROM visitor_profiles
                     WHERE geo_country IS NOT NULL
                     GROUP BY geo_country, geo_region, geo_city
                     ORDER BY count DESC
                     LIMIT :limit";
            
            return $db->fetchAll($query, ['limit' => $limit]);
        } catch (\Exception $e) {
            $logger = new Logger();
            $logger->error('Error getting top visitor locations: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get device distribution
     * 
     * @return array Device type data with counts
     */
    public static function getDeviceDistribution()
    {
        try {
            $db = new Database();
            $query = "SELECT 
                     device_type,
                     COUNT(*) as count
                     FROM visitor_profiles
                     WHERE device_type IS NOT NULL
                     GROUP BY device_type
                     ORDER BY count DESC";
            
            return $db->fetchAll($query, []);
        } catch (\Exception $e) {
            $logger = new Logger();
            $logger->error('Error getting device distribution: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get browser distribution
     * 
     * @return array Browser data with counts
     */
    public static function getBrowserDistribution()
    {
        try {
            $db = new Database();
            $query = "SELECT 
                     browser,
                     COUNT(*) as count
                     FROM visitor_profiles
                     WHERE browser IS NOT NULL
                     GROUP BY browser
                     ORDER BY count DESC";
            
            return $db->fetchAll($query, []);
        } catch (\Exception $e) {
            $logger = new Logger();
            $logger->error('Error getting browser distribution: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get visitor count by date range
     * 
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Daily visitor counts
     */
    public static function getVisitorCountByDate($startDate, $endDate)
    {
        try {
            $db = new Database();
            $query = "SELECT 
                     DATE(first_seen) as date,
                     COUNT(*) as new_visitors
                     FROM visitor_profiles
                     WHERE first_seen BETWEEN :start_date AND :end_date
                     GROUP BY DATE(first_seen)
                     ORDER BY date";
            
            return $db->fetchAll($query, [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
        } catch (\Exception $e) {
            $logger = new Logger();
            $logger->error('Error getting visitor count by date: ' . $e->getMessage());
            return [];
        }
    }
} 