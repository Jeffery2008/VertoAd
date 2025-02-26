<?php
namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Cache;

class NotificationPreference {
    private $db;
    private $cache;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
    }
    
    /**
     * 获取用户的通知偏好设置
     * @param int $userId 用户ID
     * @param string|null $templateCode 模板代码（可选）
     * @return array
     */
    public function getUserPreferences(int $userId, ?string $templateCode = null): array {
        $cacheKey = "user_preferences:{$userId}";
        $preferences = $this->cache->get($cacheKey);
        
        if ($preferences === false) {
            $sql = "SELECT unp.*, nt.code as template_code 
                   FROM user_notification_preferences unp 
                   JOIN notification_templates nt ON unp.template_id = nt.id 
                   WHERE unp.user_id = :user_id";
            $params = [':user_id' => $userId];
            
            $preferences = $this->db->query($sql, $params)->fetchAll();
            $this->cache->set($cacheKey, $preferences, 3600); // 缓存1小时
        }
        
        if ($templateCode !== null) {
            return array_filter($preferences, function($pref) use ($templateCode) {
                return $pref['template_code'] === $templateCode;
            });
        }
        
        return $preferences;
    }
    
    /**
     * 更新用户的通知偏好设置
     * @param int $userId 用户ID
     * @param int $templateId 模板ID
     * @param array $channels 渠道设置
     * @return bool
     */
    public function updatePreferences(int $userId, int $templateId, array $channels): bool {
        try {
            $this->db->beginTransaction();
            
            // 删除现有设置
            $sql = "DELETE FROM user_notification_preferences 
                   WHERE user_id = :user_id AND template_id = :template_id";
            $params = [
                ':user_id' => $userId,
                ':template_id' => $templateId
            ];
            $this->db->execute($sql, $params);
            
            // 插入新设置
            $sql = "INSERT INTO user_notification_preferences 
                   (user_id, template_id, channel_type, is_enabled) 
                   VALUES (:user_id, :template_id, :channel_type, :is_enabled)";
            
            foreach ($channels as $channel => $enabled) {
                $params = [
                    ':user_id' => $userId,
                    ':template_id' => $templateId,
                    ':channel_type' => $channel,
                    ':is_enabled' => $enabled
                ];
                $this->db->execute($sql, $params);
            }
            
            $this->db->commit();
            
            // 清除缓存
            $this->clearUserCache($userId);
            
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * 获取默认的通知偏好设置
     * @param int $templateId 模板ID
     * @return array
     */
    public function getDefaultPreferences(int $templateId): array {
        $sql = "SELECT supported_channels FROM notification_templates WHERE id = :id";
        $params = [':id' => $templateId];
        $result = $this->db->query($sql, $params)->fetch();
        
        if (!$result) {
            return [];
        }
        
        $supportedChannels = json_decode($result['supported_channels'], true);
        $preferences = [];
        
        foreach ($supportedChannels as $channel) {
            // 站内信默认启用，其他渠道默认禁用
            $preferences[$channel] = ($channel === 'in_app');
        }
        
        return $preferences;
    }
    
    /**
     * 批量设置用户的通知偏好
     * @param int $userId 用户ID
     * @param array $preferences 偏好设置
     * @return bool
     */
    public function setBulkPreferences(int $userId, array $preferences): bool {
        try {
            $this->db->beginTransaction();
            
            foreach ($preferences as $templateId => $channels) {
                $this->updatePreferences($userId, $templateId, $channels);
            }
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * 检查用户是否启用了指定模板的指定渠道
     * @param int $userId 用户ID
     * @param string $templateCode 模板代码
     * @param string $channelType 渠道类型
     * @return bool
     */
    public function isChannelEnabled(int $userId, string $templateCode, string $channelType): bool {
        $preferences = $this->getUserPreferences($userId, $templateCode);
        
        foreach ($preferences as $pref) {
            if ($pref['channel_type'] === $channelType) {
                return (bool)$pref['is_enabled'];
            }
        }
        
        // 如果没有找到设置，返回默认值
        return $channelType === 'in_app';
    }
    
    /**
     * 清除用户的缓存
     * @param int $userId 用户ID
     */
    private function clearUserCache(int $userId): void {
        $this->cache->delete("user_preferences:{$userId}");
    }
} 