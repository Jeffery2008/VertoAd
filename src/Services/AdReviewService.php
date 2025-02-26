<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Models\AdReview;
use VertoAD\Core\Models\AdReviewLog;
use VertoAD\Core\Models\Advertisement;
use VertoAD\Core\Models\ViolationType;

class AdReviewService
{
    private $adReviewModel;
    private $adReviewLogModel;
    private $advertisementModel;
    private $violationTypeModel;
    private $db;
    private $logger;
    
    /**
     * Initialize the service with models
     */
    public function __construct($db = null, $logger = null)
    {
        $this->db = $db ?: new \VertoAD\Core\Utils\Database();
        $this->logger = $logger ?: new \VertoAD\Core\Utils\Logger();
        
        $this->adReviewModel = new AdReview();
        $this->adReviewLogModel = new AdReviewLog();
        $this->advertisementModel = new Advertisement();
        $this->violationTypeModel = new ViolationType();
    }
    
    /**
     * Start a review for an advertisement
     * 
     * @param int $adId Advertisement ID
     * @param int $reviewerId Reviewer user ID
     * @return int|bool Review ID or false on failure
     */
    public function startReview($adId, $reviewerId)
    {
        // Check if ad exists and is in pending status
        $ad = $this->advertisementModel->find($adId);
        if (!$ad || $ad['status'] !== 'pending') {
            return false;
        }
        
        // Check if there's already a pending review
        $existingReviews = $this->adReviewModel->getByAdId($adId);
        foreach ($existingReviews as $review) {
            if ($review['status'] === 'pending') {
                // A pending review already exists
                return $review['id'];
            }
        }
        
        // Create a new review
        $reviewData = [
            'ad_id' => $adId,
            'reviewer_id' => $reviewerId,
            'status' => 'pending'
        ];
        
        $reviewId = $this->adReviewModel->create($reviewData);
        
        if ($reviewId) {
            // Log the action
            $this->adReviewLogModel->log([
                'review_id' => $reviewId,
                'action' => 'start_review',
                'new_status' => 'pending',
                'actor_id' => $reviewerId
            ]);
        }
        
        return $reviewId;
    }
    
    /**
     * Approve an advertisement
     * 
     * @param int $reviewId Review ID
     * @param int $reviewerId Reviewer user ID
     * @param string $comments Optional comments
     * @return bool Success
     */
    public function approveAd($reviewId, $reviewerId, $comments = '')
    {
        // Get the review
        $review = $this->adReviewModel->find($reviewId);
        if (!$review || $review['status'] !== 'pending') {
            return false;
        }
        
        // Update the review status
        $oldStatus = $review['status'];
        $updateSuccess = $this->adReviewModel->update($reviewId, [
            'status' => 'approved',
            'comments' => $comments
        ]);
        
        if ($updateSuccess) {
            // Update the ad status
            $this->advertisementModel->update($review['ad_id'], [
                'status' => 'active'
            ]);
            
            // Log the action
            $this->adReviewLogModel->log([
                'review_id' => $reviewId,
                'action' => 'approve',
                'old_status' => $oldStatus,
                'new_status' => 'approved',
                'actor_id' => $reviewerId,
                'comments' => $comments
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Reject an advertisement
     * 
     * @param int $reviewId Review ID
     * @param int $reviewerId Reviewer user ID
     * @param string $violationType Violation type
     * @param string $comments Rejection comments
     * @return bool Success
     */
    public function rejectAd($reviewId, $reviewerId, $violationType, $comments)
    {
        // Get the review
        $review = $this->adReviewModel->find($reviewId);
        if (!$review || $review['status'] !== 'pending') {
            return false;
        }
        
        // Update the review status
        $oldStatus = $review['status'];
        $updateSuccess = $this->adReviewModel->update($reviewId, [
            'status' => 'rejected',
            'violation_type' => $violationType,
            'comments' => $comments
        ]);
        
        if ($updateSuccess) {
            // Update the ad status
            $this->advertisementModel->update($review['ad_id'], [
                'status' => 'rejected'
            ]);
            
            // Log the action
            $this->adReviewLogModel->log([
                'review_id' => $reviewId,
                'action' => 'reject',
                'old_status' => $oldStatus,
                'new_status' => 'rejected',
                'actor_id' => $reviewerId,
                'comments' => $comments
            ]);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get ads pending review
     * 
     * @param int $limit Maximum number of ads to return
     * @param int $offset Result offset
     * @return array Ads pending review
     */
    public function getPendingReviewAds($limit = 20, $offset = 0)
    {
        $query = "SELECT a.*, u.username as advertiser_name,
                         p.name as position_name, p.width, p.height
                  FROM advertisements a
                  JOIN users u ON a.advertiser_id = u.id
                  JOIN ad_positions p ON a.position_id = p.id
                  WHERE a.status = 'pending'
                  ORDER BY a.created_at ASC
                  LIMIT :limit OFFSET :offset";
        
        return $this->db->fetchAll($query, [
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
    
    /**
     * Get count of ads pending review
     * 
     * @return int Count
     */
    public function getPendingReviewCount()
    {
        $query = "SELECT COUNT(*) as count 
                  FROM advertisements 
                  WHERE status = 'pending'";
        
        $result = $this->db->fetchOne($query);
        return $result ? (int)$result['count'] : 0;
    }
    
    /**
     * Get all violation types
     * 
     * @return array Violation types
     */
    public function getViolationTypes()
    {
        return $this->violationTypeModel->getAll();
    }
    
    /**
     * Get review history for an ad
     * 
     * @param int $adId Advertisement ID
     * @return array Review history
     */
    public function getAdReviewHistory($adId)
    {
        return $this->adReviewModel->getByAdId($adId);
    }
    
    /**
     * Get ads with recent review activity
     * 
     * @param int $limit Maximum number of ads to return
     * @return array Recent ad reviews
     */
    public function getRecentReviews($limit = 10)
    {
        $query = "SELECT r.*, a.title as ad_title, a.advertiser_id,
                         u1.username as reviewer_name, u2.username as advertiser_name,
                         a.created_at as ad_created_at
                  FROM ad_reviews r
                  JOIN advertisements a ON r.ad_id = a.id
                  JOIN users u1 ON r.reviewer_id = u1.id
                  JOIN users u2 ON a.advertiser_id = u2.id
                  ORDER BY r.updated_at DESC
                  LIMIT :limit";
        
        return $this->db->fetchAll($query, ['limit' => $limit]);
    }
} 