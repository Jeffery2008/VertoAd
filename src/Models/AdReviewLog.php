<?php

namespace VertoAD\Core\Models;

class AdReviewLog extends BaseModel
{
    protected $tableName = 'ad_review_logs';
    
    /**
     * Log a review action
     * 
     * @param array $data Log data
     * @return int|bool Log ID on success, false on failure
     */
    public function log($data)
    {
        $query = "INSERT INTO {$this->tableName} (
            review_id,
            action,
            old_status,
            new_status,
            actor_id,
            comments
        ) VALUES (
            :review_id,
            :action,
            :old_status,
            :new_status,
            :actor_id,
            :comments
        )";
        
        $params = [
            'review_id' => $data['review_id'],
            'action' => $data['action'],
            'old_status' => $data['old_status'] ?? null,
            'new_status' => $data['new_status'] ?? null,
            'actor_id' => $data['actor_id'],
            'comments' => $data['comments'] ?? null
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Get logs for a review
     * 
     * @param int $reviewId Review ID
     * @return array Logs
     */
    public function getByReviewId($reviewId)
    {
        $query = "SELECT l.*, u.username as actor_name
                  FROM {$this->tableName} l
                  JOIN users u ON l.actor_id = u.id
                  WHERE l.review_id = :review_id
                  ORDER BY l.created_at DESC";
        
        return $this->db->fetchAll($query, ['review_id' => $reviewId]);
    }
    
    /**
     * Get logs for an ad
     * 
     * @param int $adId Ad ID
     * @return array Logs
     */
    public function getByAdId($adId)
    {
        $query = "SELECT l.*, u.username as actor_name, r.ad_id
                  FROM {$this->tableName} l
                  JOIN ad_reviews r ON l.review_id = r.id
                  JOIN users u ON l.actor_id = u.id
                  WHERE r.ad_id = :ad_id
                  ORDER BY l.created_at DESC";
        
        return $this->db->fetchAll($query, ['ad_id' => $adId]);
    }
    
    /**
     * Get recent logs
     * 
     * @param int $limit Number of logs to return
     * @return array Logs
     */
    public function getRecent($limit = 20)
    {
        $query = "SELECT l.*, u.username as actor_name, r.ad_id, a.title as ad_title
                  FROM {$this->tableName} l
                  JOIN ad_reviews r ON l.review_id = r.id
                  JOIN advertisements a ON r.ad_id = a.id
                  JOIN users u ON l.actor_id = u.id
                  ORDER BY l.created_at DESC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, ['limit' => $limit]);
    }
    
    /**
     * Get log by ID
     * 
     * @param int $id Log ID
     * @return array|bool Log data or false if not found
     */
    public function find($id)
    {
        $query = "SELECT l.*, u.username as actor_name, r.ad_id
                  FROM {$this->tableName} l
                  JOIN ad_reviews r ON l.review_id = r.id
                  JOIN users u ON l.actor_id = u.id
                  WHERE l.id = :id";
        
        return $this->db->fetchOne($query, ['id' => $id]);
    }
    
    /**
     * Get activity statistics for a time period
     * 
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @return array Statistics
     */
    public function getActivityStats($startDate, $endDate)
    {
        $query = "SELECT 
                    COUNT(*) as total_actions,
                    SUM(CASE WHEN action = 'approve' THEN 1 ELSE 0 END) as approve_count,
                    SUM(CASE WHEN action = 'reject' THEN 1 ELSE 0 END) as reject_count,
                    COUNT(DISTINCT review_id) as reviews_affected,
                    COUNT(DISTINCT actor_id) as reviewers_active
                  FROM {$this->tableName}
                  WHERE created_at BETWEEN :start_date AND :end_date";
        
        return $this->db->fetchOne($query, [
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        ]);
    }
} 