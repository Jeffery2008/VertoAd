<?php
namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Cache;
use VertoAD\Core\Utils\ErrorLogger;

class Notification {
    private $db;
    private $cache;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cache = new Cache();
    }
    
    /**
     * 创建新通知
     * @param array $data 通知数据
     * @return int|false 成功返回通知ID，失败返回false
     */
    public function createNotification(array $data) {
        try {
            // 验证必填字段
            $required = ['template_id', 'user_id', 'channel_type', 'title', 'content', 'variables'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new \InvalidArgumentException("Missing required field: {$field}");
                }
            }
            
            $sql = "INSERT INTO notifications (template_id, user_id, channel_type, title, content, variables, status) 
                    VALUES (:template_id, :user_id, :channel_type, :title, :content, :variables, :status)";
            
            $params = [
                ':template_id' => $data['template_id'],
                ':user_id' => $data['user_id'],
                ':channel_type' => $data['channel_type'],
                ':title' => $data['title'],
                ':content' => $data['content'],
                ':variables' => json_encode($data['variables']),
                ':status' => 'pending'
            ];
            
            if ($this->db->execute($sql, $params)) {
                $notificationId = $this->db->lastInsertId();
                // 清除相关缓存
                $this->clearUserNotificationsCache($data['user_id']);
                return $notificationId;
            }
            
            return false;
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return false;
        }
    }
    
    /**
     * 批量创建通知
     * @param array $notifications 通知数据数组
     * @return array 成功创建的通知ID数组
     */
    public function createBatchNotifications(array $notifications): array {
        $successIds = [];
        $this->db->beginTransaction();
        
        try {
            foreach ($notifications as $data) {
                $notificationId = $this->createNotification($data);
                if ($notificationId) {
                    $successIds[] = $notificationId;
                }
            }
            
            $this->db->commit();
            return $successIds;
        } catch (\Exception $e) {
            $this->db->rollBack();
            ErrorLogger::log($e);
            return $successIds;
        }
    }
    
    /**
     * 更新通知状态
     * @param int $id 通知ID
     * @param string $status 新状态
     * @param string|null $errorMessage 错误信息
     * @return bool
     */
    public function updateStatus(int $id, string $status, ?string $errorMessage = null): bool {
        try {
            $sql = "UPDATE notifications SET status = :status";
            $params = [':status' => $status, ':id' => $id];
            
            if ($status === 'sent') {
                $sql .= ", sent_at = CURRENT_TIMESTAMP";
            } elseif ($status === 'read') {
                $sql .= ", read_at = CURRENT_TIMESTAMP";
            }
            
            if ($status === 'failed' && $errorMessage) {
                $sql .= ", error_message = :error_message";
                $params[':error_message'] = $errorMessage;
            }
            
            $sql .= " WHERE id = :id";
            
            if ($this->db->execute($sql, $params)) {
                // 获取通知信息以清除缓存
                $notification = $this->getNotificationById($id);
                if ($notification) {
                    $this->clearUserNotificationsCache($notification['user_id']);
                }
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return false;
        }
    }
    
    /**
     * 获取用户的通知列表
     * @param int $userId 用户ID
     * @param array $filters 过滤条件
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @return array
     */
    public function getUserNotifications(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array {
        try {
            $cacheKey = "user_notifications_{$userId}_{$page}_{$perPage}_" . md5(json_encode($filters));
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
            
            $conditions = ['user_id = :user_id'];
            $params = [':user_id' => $userId];
            
            if (isset($filters['status'])) {
                $conditions[] = 'status = :status';
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['channel_type'])) {
                $conditions[] = 'channel_type = :channel_type';
                $params[':channel_type'] = $filters['channel_type'];
            }
            
            $offset = ($page - 1) * $perPage;
            
            $sql = "SELECT * FROM notifications 
                    WHERE " . implode(' AND ', $conditions) . " 
                    ORDER BY created_at DESC 
                    LIMIT :offset, :limit";
            
            $params[':offset'] = $offset;
            $params[':limit'] = $perPage;
            
            $notifications = $this->db->query($sql, $params)->fetchAll();
            
            // 处理JSON数据
            foreach ($notifications as &$notification) {
                $notification['variables'] = json_decode($notification['variables'], true);
            }
            
            // 缓存结果
            $this->cache->set($cacheKey, $notifications, 300); // 缓存5分钟
            
            return $notifications;
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return [];
        }
    }
    
    /**
     * 获取待发送的通知列表
     * @param string $channelType 通知渠道类型
     * @param int $limit 获取数量
     * @return array
     */
    public function getPendingNotifications(string $channelType, int $limit = 50): array {
        try {
            $sql = "SELECT * FROM notifications 
                    WHERE status = 'pending' 
                    AND channel_type = :channel_type 
                    ORDER BY created_at ASC 
                    LIMIT :limit";
            
            $params = [
                ':channel_type' => $channelType,
                ':limit' => $limit
            ];
            
            $notifications = $this->db->query($sql, $params)->fetchAll();
            
            // 处理JSON数据
            foreach ($notifications as &$notification) {
                $notification['variables'] = json_decode($notification['variables'], true);
            }
            
            return $notifications;
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return [];
        }
    }
    
    /**
     * 根据ID获取通知
     * @param int $id 通知ID
     * @return array|null
     */
    public function getNotificationById(int $id): ?array {
        try {
            $sql = "SELECT * FROM notifications WHERE id = :id";
            $params = [':id' => $id];
            
            $notification = $this->db->query($sql, $params)->fetch();
            
            if ($notification) {
                $notification['variables'] = json_decode($notification['variables'], true);
                return $notification;
            }
            
            return null;
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return null;
        }
    }
    
    /**
     * 清除用户通知缓存
     * @param int $userId 用户ID
     */
    private function clearUserNotificationsCache(int $userId): void {
        $pattern = "user_notifications_{$userId}_*";
        $this->cache->deletePattern($pattern);
    }
    
    /**
     * 获取通知统计信息
     * @param int $userId 用户ID
     * @return array
     */
    public function getNotificationStats(int $userId): array {
        try {
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                    FROM notifications 
                    WHERE user_id = :user_id";
            
            $params = [':user_id' => $userId];
            
            return $this->db->query($sql, $params)->fetch();
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return [
                'total' => 0,
                'unread' => 0,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0
            ];
        }
    }
    
    /**
     * 标记通知为已读
     * @param int $id 通知ID
     * @return bool
     */
    public function markAsRead(int $id): bool {
        return $this->updateStatus($id, 'read');
    }
    
    /**
     * 批量标记通知为已读
     * @param array $ids 通知ID数组
     * @return bool
     */
    public function markMultipleAsRead(array $ids): bool {
        if (empty($ids)) {
            return false;
        }
        
        try {
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $sql = "UPDATE notifications 
                    SET status = 'read', read_at = CURRENT_TIMESTAMP 
                    WHERE id IN ($placeholders)";
            
            return $this->db->execute($sql, $ids);
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return false;
        }
    }
    
    /**
     * 删除过期通知
     * @param int $days 保留天数
     * @return bool
     */
    public function deleteExpiredNotifications(int $days = 30): bool {
        try {
            $sql = "DELETE FROM notifications 
                    WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :days DAY) 
                    AND status IN ('sent', 'read', 'failed')";
            
            return $this->db->execute($sql, [':days' => $days]);
        } catch (\Exception $e) {
            ErrorLogger::log($e);
            return false;
        }
    }
} 