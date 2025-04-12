<?php

namespace App\Services;

use App\Utils\QuillRenderer;
use Exception;
use PDO;

class AdService
{
    protected $db;

    // Assuming a PDO connection is injected or available
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Selects an active and approved ad for a given zone ID.
     *
     * Uses the ad_rules table to find ads linked to the zone.
     *
     * @param int $zoneId The ID of the ad zone/placement.
     * @return array|null An array containing ad details (id, delta) or null if no suitable ad found.
     */
    public function getEligibleAdForZone(int $zoneId): ?array
    {
        try {
            // Select an active/approved ad linked to the zone via ad_rules
            // Assumes ad_rules has ad_id, zone_id, status fields
            // TODO: Consider rule priority, budget, targeting if implementing those features
            $stmt = $this->db->prepare("
                SELECT
                    a.id,
                    a.content_quill_delta
                FROM ads a
                JOIN ad_rules ar ON a.id = ar.ad_id
                WHERE
                    ar.zone_id = :zone_id
                    AND ar.status = 'active' -- Rule must be active
                    AND a.status = 'approved' -- Ad must be approved
                    AND NOW() BETWEEN a.start_datetime AND a.end_datetime -- Ad must be within its active duration
                    AND a.content_quill_delta IS NOT NULL
                    AND JSON_VALID(a.content_quill_delta)
                ORDER BY RAND() -- Simple random rotation among eligible ads for this zone
                LIMIT 1
            ");
            
            $stmt->bindParam(':zone_id', $zoneId, PDO::PARAM_INT);
            $stmt->execute();
            $ad = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ad && !empty($ad['content_quill_delta'])) {
                return [
                    'id' => $ad['id'],
                    'delta' => $ad['content_quill_delta']
                ];
            }

            return null; // No eligible ad found for this zone

        } catch (Exception $e) {
            error_log("Error fetching eligible ad for zone {$zoneId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Renders the Quill Delta JSON to HTML.
     *
     * @param string $deltaJson
     * @return string HTML content.
     */
    public function renderAdHtml(string $deltaJson): string
    {
        try {
            return QuillRenderer::render($deltaJson);
        } catch (Exception $e) {
            // Log the exception
            error_log("Error rendering Quill delta: " . $e->getMessage());
            // Return safe fallback HTML
            return '<p style="color:red;">Error displaying ad content.</p>';
        }
    }

    /**
     * Creates a new ad for a specific user.
     *
     * @param int $userId The ID of the advertiser creating the ad.
     * @param array $data Ad data ('name', 'target_url', 'content_quill_delta').
     * @return int|false The ID of the newly created ad, or false on failure.
     */
    public function createAd(int $userId, array $data): int|false
    {
        // Basic validation
        if (empty($data['name']) || empty($data['target_url']) || empty($data['content_quill_delta'])) {
            error_log("Create Ad failed: Missing required fields.");
            return false;
        }
        
        // Ensure delta is valid JSON before attempting to store
        $deltaJson = json_encode($data['content_quill_delta']);
        if ($deltaJson === false) {
             error_log("Create Ad failed: Invalid Quill Delta structure provided.");
             return false;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO ads (user_id, title, target_url, content_quill_delta, status, created_at, updated_at)
                VALUES (:user_id, :title, :target_url, :delta, 'draft', NOW(), NOW())
            ");

            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $data['name']);
            $stmt->bindParam(':target_url', $data['target_url']);
            $stmt->bindParam(':delta', $deltaJson); // Store the JSON string
            
            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            } else {
                 error_log("Create Ad DB Error: " . implode(', ', $stmt->errorInfo()));
                return false;
            }
        } catch (Exception $e) {
            error_log("Create Ad Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing ad for a specific user.
     *
     * @param int $adId The ID of the ad to update.
     * @param int $userId The ID of the advertiser owning the ad.
     * @param array $data Ad data ('name', 'target_url', 'content_quill_delta').
     * @return bool True on success, false on failure.
     */
    public function updateAd(int $adId, int $userId, array $data): bool
    {
        // Basic validation
        if (empty($data['name']) || empty($data['target_url']) || empty($data['content_quill_delta'])) {
             error_log("Update Ad failed: Missing required fields for Ad ID: {$adId}");
            return false;
        }
        
         // Ensure delta is valid JSON
        $deltaJson = json_encode($data['content_quill_delta']);
        if ($deltaJson === false) {
             error_log("Update Ad failed: Invalid Quill Delta structure for Ad ID: {$adId}");
             return false;
        }

        try {
            // Check ownership first
            $checkStmt = $this->db->prepare("SELECT user_id FROM ads WHERE id = :ad_id");
            $checkStmt->bindParam(':ad_id', $adId, PDO::PARAM_INT);
            $checkStmt->execute();
            $ownerId = $checkStmt->fetchColumn();

            if (!$ownerId || $ownerId != $userId) {
                 error_log("Update Ad failed: Ad ID {$adId} not found or user {$userId} is not owner.");
                return false; // Ad not found or doesn't belong to user
            }

            // Update the ad - reset status to draft/pending on content change?
            $stmt = $this->db->prepare("
                UPDATE ads
                SET title = :title,
                    target_url = :target_url,
                    content_quill_delta = :delta,
                    updated_at = NOW()
                    -- status = 'draft' -- Optional: Reset status on update?
                WHERE id = :ad_id AND user_id = :user_id
            ");

            $stmt->bindParam(':title', $data['name']);
            $stmt->bindParam(':target_url', $data['target_url']);
            $stmt->bindParam(':delta', $deltaJson);
            $stmt->bindParam(':ad_id', $adId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
             if (!$success) {
                 error_log("Update Ad DB Error: " . implode(', ', $stmt->errorInfo()));
             }
             return $success;

        } catch (Exception $e) {
            error_log("Update Ad Exception for Ad ID {$adId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a specific ad by ID, ensuring it belongs to the user.
     *
     * @param int $adId The ID of the ad.
     * @param int $userId The ID of the user requesting the ad.
     * @return array|null Ad data or null if not found/not owned.
     */
    public function getAdByIdForUser(int $adId, int $userId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, user_id, title, target_url, content_quill_delta, status, start_datetime, end_datetime, created_at, updated_at
                FROM ads
                WHERE id = :ad_id AND user_id = :user_id
            ");
            $stmt->bindParam(':ad_id', $adId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $ad = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($ad && isset($ad['content_quill_delta'])) {
                // Attempt to decode delta JSON for consistency before returning
                $deltaObject = json_decode($ad['content_quill_delta']);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $ad['content_quill_delta'] = $deltaObject; // Return as object
                } else {
                    // Handle invalid JSON in DB? Log error.
                     error_log("Warning: Invalid JSON found in content_quill_delta for Ad ID: {$adId}");
                    $ad['content_quill_delta'] = null; // Or return error / empty delta
                }
            }

            return $ad ?: null;

        } catch (Exception $e) {
            error_log("Get Ad By ID Exception for Ad ID {$adId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves a list of ads for a specific user.
     *
     * @param int $userId The ID of the user whose ads to retrieve.
     * @param int $page Current page number for pagination.
     * @param int $limit Number of items per page.
     * @param string $status Optional filter by ad status.
     * @return array Contains 'ads' list and 'total' count.
     */
    public function getAdsForUser(int $userId, int $page = 1, int $limit = 20, ?string $status = null): array
    {
        $offset = ($page - 1) * $limit;
        $ads = [];
        $total = 0;

        try {
            // Base query
            $countSql = "SELECT COUNT(*) FROM ads WHERE user_id = :user_id";
            $selectSql = "SELECT id, title, status, start_datetime, end_datetime, created_at, updated_at 
                          FROM ads 
                          WHERE user_id = :user_id";
            
            // Add status filter if provided
            $params = [':user_id' => $userId];
            if ($status) {
                 $countSql .= " AND status = :status";
                 $selectSql .= " AND status = :status";
                 $params[':status'] = $status;
            }

            // Get total count
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Get paginated ads
            $selectSql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $selectStmt = $this->db->prepare($selectSql);
            // Bind common params
            foreach ($params as $key => &$val) {
                 $selectStmt->bindParam($key, $val); // Use existing type detection or specify if needed
            }
            $selectStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $selectStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $selectStmt->execute();
            $ads = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Ads For User Exception (User ID: {$userId}): " . $e->getMessage());
            // Return empty result on error
        }

        return [
            'ads' => $ads,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * Deletes an ad, ensuring ownership.
     *
     * @param int $adId The ID of the ad to delete.
     * @param int $userId The ID of the user attempting deletion.
     * @return bool True on success, false on failure (not found, not owner, db error).
     */
    public function deleteAd(int $adId, int $userId): bool
    {
         try {
            // Use DELETE with WHERE clause for atomicity (check ownership and delete)
            $stmt = $this->db->prepare("DELETE FROM ads WHERE id = :ad_id AND user_id = :user_id");
            $stmt->bindParam(':ad_id', $adId, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            
            // Check if any row was actually deleted
            if ($success && $stmt->rowCount() > 0) {
                return true;
            } else {
                // Log if deletion failed or ad didn't belong to user
                if ($stmt->rowCount() === 0) {
                     error_log("Delete Ad failed: Ad ID {$adId} not found or user {$userId} is not owner.");
                }
                return false;
            }
        } catch (Exception $e) {
            error_log("Delete Ad Exception (Ad ID: {$adId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Approves an ad.
     * Sets status to 'approved' and potentially calculates start/end dates.
     *
     * @param int $adId The ID of the ad to approve.
     * @return bool True on success, false on failure.
     */
    public function approveAd(int $adId): bool
    {
        try {
            // Fetch the ad to check current status and purchased duration
            $stmtCheck = $this->db->prepare("SELECT status, purchased_duration_days FROM ads WHERE id = :id");
            $stmtCheck->bindParam(':id', $adId, PDO::PARAM_INT);
            $stmtCheck->execute();
            $ad = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$ad) {
                error_log("Approve Ad failed: Ad ID {$adId} not found.");
                return false;
            }
            // Optional: Only allow approval from certain statuses like 'pending' or 'draft'
            // if (!in_array($ad['status'], ['pending', 'draft'])) {
            //     error_log("Approve Ad failed: Ad ID {$adId} has invalid status: {$ad['status']}.");
            //     return false;
            // }

            // Prepare update fields
            $updateFields = ["status = 'approved'"];
            $params = [':id' => $adId];

            // If duration was purchased, set start/end dates upon approval
            if (isset($ad['purchased_duration_days']) && $ad['purchased_duration_days'] > 0) {
                $updateFields[] = "start_datetime = NOW()";
                $updateFields[] = "end_datetime = DATE_ADD(NOW(), INTERVAL :duration DAY)";
                $params[':duration'] = $ad['purchased_duration_days'];
            }
            $updateFields[] = "updated_at = NOW()";

            $sql = "UPDATE ads SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            $success = $stmt->execute($params);
             if (!$success) {
                 error_log("Approve Ad DB Error (Ad ID: {$adId}): " . implode(', ', $stmt->errorInfo()));
             }
             return $success;

        } catch (Exception $e) {
            error_log("Approve Ad Exception (Ad ID: {$adId}): " . $e->getMessage());
            return false;
        }
    }

     /**
     * Rejects an ad.
     * Sets status to 'rejected'.
     *
     * @param int $adId The ID of the ad to reject.
     * @return bool True on success, false on failure.
     */
    public function rejectAd(int $adId): bool
    {
         try {
            // Fetch the ad to check current status 
            $stmtCheck = $this->db->prepare("SELECT status FROM ads WHERE id = :id");
            $stmtCheck->bindParam(':id', $adId, PDO::PARAM_INT);
            $stmtCheck->execute();
            $ad = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$ad) {
                error_log("Reject Ad failed: Ad ID {$adId} not found.");
                return false;
            }
             // Optional: Only allow rejection from certain statuses like 'pending'
            // if ($ad['status'] !== 'pending') { ... }
            
            $sql = "UPDATE ads SET status = 'rejected', start_datetime = NULL, end_datetime = NULL, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $adId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
             if (!$success) {
                 error_log("Reject Ad DB Error (Ad ID: {$adId}): " . implode(', ', $stmt->errorInfo()));
             }
             return $success;

        } catch (Exception $e) {
            error_log("Reject Ad Exception (Ad ID: {$adId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a paginated list of ads, optionally filtered.
     * Suitable for admin listing.
     *
     * @param int $page
     * @param int $limit
     * @param string|null $status Filter by status (e.g., 'pending', 'approved', 'rejected', 'draft')
     * @param int|null $userId Filter by user ID (optional)
     * @return array
     */
    public function getAds(int $page = 1, int $limit = 20, ?string $status = null, ?int $userId = null): array
    {
        $offset = ($page - 1) * $limit;
        $ads = [];
        $total = 0;

        // Basic validation for status enum if needed
        $allowedStatuses = ['pending', 'approved', 'rejected', 'draft', 'paused']; // Example statuses
        if ($status !== null && !in_array($status, $allowedStatuses)) {
            $status = null; // Ignore invalid status
        }

        try {
            $countSql = "SELECT COUNT(*) FROM ads";
            // Include user_id and target_url for admin review
            $selectSql = "SELECT id, user_id, title, status, target_url, start_datetime, end_datetime, created_at, updated_at, content_quill_delta 
                          FROM ads"; 
            
            $whereClauses = [];
            $params = [];

            if ($status) {
                $whereClauses[] = "status = :status";
                $params[':status'] = $status;
            }
            if ($userId) {
                 $whereClauses[] = "user_id = :user_id";
                 $params[':user_id'] = $userId;
            }
            
            if (!empty($whereClauses)) {
                $countSql .= " WHERE " . implode(' AND ', $whereClauses);
                $selectSql .= " WHERE " . implode(' AND ', $whereClauses);
            }

            // Get total count
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Get paginated ads
            $selectSql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $selectStmt = $this->db->prepare($selectSql);
             // Bind common params
            foreach ($params as $key => &$val) {
                 $selectStmt->bindParam($key, $val); // PDO type detection usually works
            }
            $selectStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $selectStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $selectStmt->execute();
            $ads = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Ads Exception: " . $e->getMessage());
            // Return empty result on error
        }

        return [
            'ads' => $ads,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    // TODO: Add getAdsForUser (list ads), deleteAd methods

} 