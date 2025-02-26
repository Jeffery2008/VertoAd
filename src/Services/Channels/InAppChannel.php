<?php
namespace VertoAD\Core\Services\Channels;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Cache;

class InAppChannel extends BaseNotificationChannel {
    private $db;
    private $cache;
    
    /**
     * Constructor
     * @param array $config
     */
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
    }
    
    /**
     * Get channel type
     * @return string
     */
    public function getType(): string {
        return 'in_app';
    }
    
    /**
     * Check if channel is available
     * @return bool
     */
    public function isAvailable(): bool {
        // In-app notifications are always available as they don't depend on external services
        return true;
    }
    
    /**
     * Send notification
     * @param array $notification
     * @return bool
     */
    public function send(array $notification): bool {
        if (!$this->validate($notification)) {
            return false;
        }
        
        try {
            // Insert notification into database
            $success = $this->saveNotification($notification);
            if (!$success) {
                return false;
            }
            
            // Clear user's notification cache
            $this->clearUserCache($notification['user_id']);
            
            // Trigger real-time notification if WebSocket is enabled
            if ($this->getConfig('websocket_enabled', false)) {
                $this->triggerRealtimeNotification($notification);
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->logError("Failed to send in-app notification: " . $e->getMessage(), [
                'user_id' => $notification['user_id'],
                'template_id' => $notification['template_id']
            ]);
            return false;
        }
    }
    
    /**
     * Save notification to database
     * @param array $notification
     * @return bool
     */
    private function saveNotification(array $notification): bool {
        $sql = "INSERT INTO notifications (
                    template_id, user_id, channel_type, title, content, 
                    variables, status, created_at
                ) VALUES (
                    :template_id, :user_id, :channel_type, :title, :content,
                    :variables, 'pending', NOW()
                )";
        
        $params = [
            ':template_id' => $notification['template_id'],
            ':user_id' => $notification['user_id'],
            ':channel_type' => $this->getType(),
            ':title' => $notification['title'],
            ':content' => $notification['content'],
            ':variables' => json_encode($notification['variables'])
        ];
        
        try {
            $success = $this->db->execute($sql, $params);
            
            if ($success) {
                // Update notification status to sent
                $notificationId = $this->db->lastInsertId();
                $this->updateNotificationStatus($notificationId, 'sent');
            }
            
            return $success;
            
        } catch (\Exception $e) {
            $this->logError("Database error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update notification status
     * @param int $notificationId
     * @param string $status
     * @return bool
     */
    private function updateNotificationStatus(int $notificationId, string $status): bool {
        $sql = "UPDATE notifications SET status = :status, sent_at = NOW() 
                WHERE id = :id AND channel_type = :channel_type";
        
        $params = [
            ':id' => $notificationId,
            ':status' => $status,
            ':channel_type' => $this->getType()
        ];
        
        try {
            return $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            $this->logError("Failed to update notification status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear user's notification cache
     * @param int $userId
     */
    private function clearUserCache(int $userId): void {
        $keys = [
            "user_notifications:{$userId}",
            "user_unread_count:{$userId}"
        ];
        
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }
    }
    
    /**
     * Trigger real-time notification via WebSocket
     * @param array $notification
     */
    private function triggerRealtimeNotification(array $notification): void {
        try {
            // Get WebSocket server instance
            $server = $this->getWebSocketServer();
            if (!$server) {
                return;
            }
            
            // Prepare notification data
            $data = [
                'type' => 'notification',
                'title' => $notification['title'],
                'content' => $notification['content'],
                'timestamp' => time()
            ];
            
            // Send to specific user
            $server->sendToUser($notification['user_id'], json_encode($data));
            
        } catch (\Exception $e) {
            $this->logError("WebSocket error: " . $e->getMessage());
        }
    }
    
    /**
     * Get WebSocket server instance
     * @return mixed|null
     */
    private function getWebSocketServer() {
        // TODO: Implement WebSocket server integration
        return null;
    }
} 