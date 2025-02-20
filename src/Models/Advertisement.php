<?php
namespace Models;

use Utils\Logger;
use PDO;

class Advertisement extends BaseModel {
    // [Previous code remains the same until updateStatus method]

    /**
     * Update advertisement status
     * @param int $adId Advertisement ID
     * @param string $status New status
     * @return bool Success status
     */
    public function updateStatus($adId, $status) {
        try {
            $validStatuses = [
                self::STATUS_PENDING,
                self::STATUS_ACTIVE,
                self::STATUS_PAUSED,
                self::STATUS_REJECTED,
                self::STATUS_COMPLETED
            ];

            if (!in_array($status, $validStatuses)) {
                throw new \Exception("Invalid status: " . $status);
            }

            return $this->update($adId, [
                'status' => $status,
                'updated_at' => date("Y-m-d H:i:s")
            ]);
        } catch (\Exception $e) {
            Logger::error("Error updating status: " . $e->getMessage(), [
                'ad_id' => $adId,
                'status' => $status
            ]);
            return false;
        }
    }

    /**
     * Save canvas-based advertisement
     * @param int $advertiserId Advertiser ID
     * @param int $positionId Position ID
     * @param string $title Ad title
     * @param array $canvasData Canvas drawing data
     * @param array $adSettings Additional ad settings
     * @return int|false New ad ID or false on error
     */
    public function saveCanvasAd($advertiserId, $positionId, $title, $canvasData, $adSettings) {
        try {
            $this->beginTransaction();
            
            // Prepare canvas content
            $content = [
                'type' => self::TYPE_CANVAS,
                'version' => '1.0',
                'canvas' => $canvasData,
                'settings' => [
                    'backgroundColor' => $adSettings['backgroundColor'] ?? '#ffffff',
                    'clickAction' => $adSettings['clickAction'] ?? 'url',
                    'targetUrl' => $adSettings['targetUrl'] ?? '',
                    'trackInteractions' => $adSettings['trackInteractions'] ?? true
                ]
            ];
            
            // Store original content for history/versioning
            $originalContent = json_encode($content);
            
            // Create ad record
            $adData = [
                'advertiser_id' => $advertiserId,
                'position_id' => $positionId,
                'title' => $title,
                'content' => json_encode($content),
                'original_content' => $originalContent,
                'start_date' => $adSettings['startDate'] ?? date('Y-m-d'),
                'end_date' => $adSettings['endDate'] ?? date('Y-m-d', strtotime('+30 days')),
                'status' => self::STATUS_PENDING,
                'priority' => $adSettings['priority'] ?? 50,
                'total_budget' => $adSettings['budget'] ?? 0,
                'remaining_budget' => $adSettings['budget'] ?? 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $adId = $this->create($adData);
            
            if (!$adId) {
                throw new \Exception("Failed to create advertisement");
            }
            
            $this->commit();
            return $adId;
        } catch (\Exception $e) {
            $this->rollback();
            Logger::error("Error saving canvas ad: " . $e->getMessage(), [
                'advertiser_id' => $advertiserId,
                'position_id' => $positionId
            ]);
            return false;
        }
    }
}
