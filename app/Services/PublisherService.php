<?php

namespace App\Services;

use PDO;
use Exception;

class PublisherService
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieves all ad zones for a specific publisher.
     *
     * @param int $publisherId The ID of the publisher.
     * @return array List of zones.
     */
    public function getZonesForPublisher(int $publisherId): array
    {
        try {
            $sql = "SELECT id, name, description, width, height, status, created_at 
                    FROM zones 
                    WHERE publisher_id = :publisher_id AND status != 'deleted' 
                    ORDER BY created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':publisher_id', $publisherId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get Zones For Publisher Exception (Publisher ID: {$publisherId}): " . $e->getMessage());
            return [];
        }
    }

    /**
     * Creates a new ad zone for a publisher.
     *
     * @param int $publisherId
     * @param string $name
     * @param string|null $description
     * @param int $width
     * @param int $height
     * @return int|false The new zone ID or false on failure.
     */
    public function createZone(int $publisherId, string $name, ?string $description, int $width, int $height): int|false
    {
        if (empty($name) || $width <= 0 || $height <= 0) {
            error_log("Create Zone failed: Invalid parameters.");
            return false;
        }

        try {
            $sql = "INSERT INTO zones (publisher_id, name, description, width, height, status, created_at, updated_at)
                    VALUES (:publisher_id, :name, :description, :width, :height, 'active', NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':publisher_id', $publisherId, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':width', $width, PDO::PARAM_INT);
            $stmt->bindParam(':height', $height, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            } else {
                error_log("Create Zone DB Error: " . implode(', ', $stmt->errorInfo()));
                return false;
            }
        } catch (Exception $e) {
            error_log("Create Zone Exception: " . $e->getMessage());
            return false;
        }
    }

} 