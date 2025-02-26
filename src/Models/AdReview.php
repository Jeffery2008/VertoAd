<?php

namespace VertoAD\Core\Models;

class AdReview extends BaseModel
{
    protected $tableName = 'ad_reviews';
    
    /**
     * Create a new ad review
     * 
     * @param array $data Review data
     * @return int|bool Review ID on success, false on failure
     */
    public function create($data)
    {
        $query = "INSERT INTO {$this->tableName} (
            ad_id, 
            reviewer_id, 
            status, 
            comments, 
            violation_type
        ) VALUES (
            :ad_id, 
            :reviewer_id, 
            :status, 
            :comments, 
            :violation_type
        )";
        
        $params = [
            'ad_id' => $data['ad_id'],
            'reviewer_id' => $data['reviewer_id'],
            'status' => $data['status'],
            'comments' => $data['comments'] ?? null,
            'violation_type' => $data['violation_type'] ?? null
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Get review by ID
     * 
     * @param int $id Review ID
     * @return array|bool Review data or false if not found
     */
    public function find($id)
    {
        $query = "SELECT * FROM {$this->tableName} WHERE id = :id";
        return $this->db->fetchOne($query, ['id' => $id]);
    }
    
    /**
     * Get reviews for an ad
     * 
     * @param int $adId Ad ID
     * @return array Reviews
     */
    public function getByAdId($adId)
    {
        $query = "SELECT r.*, u.username as reviewer_name
                  FROM {$this->tableName} r
                  JOIN users u ON r.reviewer_id = u.id
                  WHERE r.ad_id = :ad_id
                  ORDER BY r.created_at DESC";
        
        return $this->db->fetchAll($query, ['ad_id' => $adId]);
    }
    
    /**
     * Get the most recent review for an ad
     * 
     * @param int $adId Ad ID
     * @return array|bool Review data or false if not found
     */
    public function getLatestForAd($adId)
    {
        $query = "SELECT r.*, u.username as reviewer_name
                  FROM {$this->tableName} r
                  JOIN users u ON r.reviewer_id = u.id
                  WHERE r.ad_id = :ad_id
                  ORDER BY r.created_at DESC
                  LIMIT 1";
        
        return $this->db->fetchOne($query, ['ad_id' => $adId]);
    }
    
    /**
     * Get pending reviews
     * 
     * @param int $limit Limit number of results
     * @param int $offset Result offset
     * @return array Reviews
     */
    public function getPendingReviews($limit = 20, $offset = 0)
    {
        $query = "SELECT r.*, a.title as ad_title, u.username as advertiser_name
                  FROM {$this->tableName} r
                  JOIN advertisements a ON r.ad_id = a.id
                  JOIN users u ON a.advertiser_id = u.id
                  WHERE a.status = 'pending'
                  ORDER BY a.created_at ASC
                  LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($query, [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Update a review
     * 
     * @param int $id Review ID
     * @param array $data Updated data
     * @return bool Success
     */
    public function update($id, $data)
    {
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        
        $query = "UPDATE {$this->tableName} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }
    
    /**
     * Delete a review
     * 
     * @param int $id Review ID
     * @return bool Success
     */
    public function delete($id)
    {
        $query = "DELETE FROM {$this->tableName} WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }
    
    /**
     * Get review statistics 
     * 
     * @return array Statistics
     */
    public function getStatistics()
    {
        $query = "SELECT 
                    COUNT(*) as total_reviews,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                  FROM {$this->tableName}";
        
        return $this->db->fetchOne($query);
    }
} 