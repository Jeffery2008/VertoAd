<?php
namespace VertoAD\Core\Services\Queue;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Cache;
use VertoAD\Core\Models\NotificationChannel;
use VertoAD\Core\Services\NotificationService;

class NotificationQueueHandler {
    private $db;
    private $cache;
    private $notificationService;
    private $maxRetries = 3;
    private $retryDelays = [300, 900, 3600]; // 5分钟, 15分钟, 1小时
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->notificationService = new NotificationService();
    }
    
    /**
     * 添加通知到队列
     * @param array $notification 通知数据
     * @return bool
     */
    public function enqueue(array $notification): bool {
        $sql = "INSERT INTO notification_queue (
                    notification_id, template_id, user_id, channel_type,
                    title, content, variables, priority, status,
                    retry_count, next_retry_at, created_at
                ) VALUES (
                    :notification_id, :template_id, :user_id, :channel_type,
                    :title, :content, :variables, :priority, 'pending',
                    0, NOW(), NOW()
                )";
        
        $params = [
            ':notification_id' => $notification['id'],
            ':template_id' => $notification['template_id'],
            ':user_id' => $notification['user_id'],
            ':channel_type' => $notification['channel_type'],
            ':title' => $notification['title'],
            ':content' => $notification['content'],
            ':variables' => json_encode($notification['variables'] ?? []),
            ':priority' => $notification['priority'] ?? 0
        ];
        
        try {
            return $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            $this->logError('Failed to enqueue notification', [
                'error' => $e->getMessage(),
                'notification' => $notification
            ]);
            return false;
        }
    }
    
    /**
     * 处理队列中的通知
     * @param int $limit 每次处理的最大数量
     * @return int 成功处理的数量
     */
    public function processQueue(int $limit = 50): int {
        $processed = 0;
        
        // 获取待处理的通知
        $sql = "SELECT * FROM notification_queue 
                WHERE status IN ('pending', 'failed') 
                AND next_retry_at <= NOW() 
                ORDER BY priority DESC, created_at ASC 
                LIMIT :limit";
        
        $notifications = $this->db->query($sql, [':limit' => $limit])->fetchAll();
        
        foreach ($notifications as $notification) {
            if ($this->processNotification($notification)) {
                $processed++;
            }
        }
        
        return $processed;
    }
    
    /**
     * 处理单个通知
     * @param array $queueItem 队列项
     * @return bool
     */
    private function processNotification(array $queueItem): bool {
        try {
            // 尝试发送通知
            $success = $this->notificationService->send(
                $queueItem['template_id'],
                json_decode($queueItem['variables'], true),
                $queueItem['user_id'],
                ['channel_type' => $queueItem['channel_type']]
            );
            
            if ($success) {
                // 更新为发送成功
                return $this->markAsCompleted($queueItem['id']);
            }
            
            // 处理失败情况
            return $this->handleFailure($queueItem);
            
        } catch (\Exception $e) {
            $this->logError('Error processing notification', [
                'error' => $e->getMessage(),
                'queue_item' => $queueItem
            ]);
            return $this->handleFailure($queueItem);
        }
    }
    
    /**
     * 处理发送失败的情况
     * @param array $queueItem 队列项
     * @return bool
     */
    private function handleFailure(array $queueItem): bool {
        $retryCount = $queueItem['retry_count'] + 1;
        
        if ($retryCount > $this->maxRetries) {
            // 超过最大重试次数，标记为失败
            return $this->markAsFailed($queueItem['id']);
        }
        
        // 计算下次重试时间
        $delay = $this->retryDelays[min($retryCount - 1, count($this->retryDelays) - 1)];
        $nextRetry = date('Y-m-d H:i:s', time() + $delay);
        
        // 更新重试信息
        $sql = "UPDATE notification_queue 
                SET retry_count = :retry_count,
                    next_retry_at = :next_retry_at,
                    updated_at = NOW()
                WHERE id = :id";
        
        $params = [
            ':id' => $queueItem['id'],
            ':retry_count' => $retryCount,
            ':next_retry_at' => $nextRetry
        ];
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * 标记通知为已完成
     * @param int $queueId 队列ID
     * @return bool
     */
    private function markAsCompleted(int $queueId): bool {
        $sql = "UPDATE notification_queue 
                SET status = 'completed',
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id";
        
        return $this->db->execute($sql, [':id' => $queueId]);
    }
    
    /**
     * 标记通知为失败
     * @param int $queueId 队列ID
     * @return bool
     */
    private function markAsFailed(int $queueId): bool {
        $sql = "UPDATE notification_queue 
                SET status = 'failed',
                    updated_at = NOW()
                WHERE id = :id";
        
        return $this->db->execute($sql, [':id' => $queueId]);
    }
    
    /**
     * 记录错误日志
     * @param string $message 错误信息
     * @param array $context 上下文信息
     */
    private function logError(string $message, array $context = []): void {
        // TODO: 实现错误日志记录
    }
} 