<?php

namespace App\Services;

use PDO;
use Exception;

class TrackingService
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Records an ad impression.
     *
     * @param int $adId The ID of the ad that was displayed.
     * @param int $zoneId The ID of the zone where the ad was displayed (maps to placement_id in schema).
     * @param string $ipAddress The viewer's IP address.
     * @param string $userAgent The viewer's user agent string.
     *
     * @return bool True on success, false on failure.
     */
    public function recordImpression(int $adId, int $zoneId, string $ipAddress, string $userAgent): bool
    {
        // Use the `impressions` table based on ad_system.sql
        // Note: The schema uses `placement_id`. We might need to map `zoneId` to `placement_id`
        // or assume they are the same for now. Using zoneId as placement_id placeholder.
        $placementId = $zoneId; 
        
        // Basic validation
        if (empty($adId) || empty($placementId) || empty($ipAddress)) {
            error_log("Record Impression failed: Missing required parameters.");
            return false;
        }

        try {
            $sql = "INSERT INTO impressions (ad_id, placement_id, ip_address, user_agent, timestamp) 
                    VALUES (:ad_id, :placement_id, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':ad_id', $adId, PDO::PARAM_INT);
            $stmt->bindParam(':placement_id', $placementId, PDO::PARAM_INT); // Using zoneId as placeholder
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);

            return $stmt->execute();

        } catch (Exception $e) {
            // Avoid failing the ad serving request if tracking fails, just log it.
            error_log("Impression Tracking Exception: " . $e->getMessage());
            return false;
        }
    }

    // TODO: Add methods for click tracking if needed
    // public function recordClick(...) { ... }
} 