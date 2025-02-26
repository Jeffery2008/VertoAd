<?php
namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Database;

class NotificationChannel {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取所有通知渠道
     * @return array
     */
    public function getAllChannels(): array {
        $sql = "SELECT * FROM notification_channels ORDER BY id ASC";
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * 获取已启用的通知渠道
     * @return array
     */
    public function getEnabledChannels(): array {
        $sql = "SELECT * FROM notification_channels WHERE is_enabled = TRUE ORDER BY id ASC";
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * 更新通知渠道状态
     * @param string $channelType 渠道类型
     * @param bool $isEnabled 是否启用
     * @return bool
     */
    public function updateChannelStatus(string $channelType, bool $isEnabled): bool {
        $sql = "UPDATE notification_channels SET is_enabled = :is_enabled WHERE channel_type = :channel_type";
        $params = [
            ':channel_type' => $channelType,
            ':is_enabled' => $isEnabled
        ];
        return $this->db->execute($sql, $params);
    }
    
    /**
     * 更新通知渠道配置
     * @param string $channelType 渠道类型
     * @param array $config 配置信息
     * @return bool
     */
    public function updateChannelConfig(string $channelType, array $config): bool {
        $sql = "UPDATE notification_channels SET config = :config WHERE channel_type = :channel_type";
        $params = [
            ':channel_type' => $channelType,
            ':config' => json_encode($config)
        ];
        return $this->db->execute($sql, $params);
    }
    
    /**
     * 获取指定渠道的配置
     * @param string $channelType 渠道类型
     * @return array|null
     */
    public function getChannelConfig(string $channelType): ?array {
        $sql = "SELECT * FROM notification_channels WHERE channel_type = :channel_type";
        $params = [':channel_type' => $channelType];
        $result = $this->db->query($sql, $params)->fetch();
        
        if ($result) {
            $result['config'] = json_decode($result['config'], true);
            return $result;
        }
        
        return null;
    }
    
    /**
     * 检查渠道是否启用
     * @param string $channelType 渠道类型
     * @return bool
     */
    public function isChannelEnabled(string $channelType): bool {
        $sql = "SELECT is_enabled FROM notification_channels WHERE channel_type = :channel_type";
        $params = [':channel_type' => $channelType];
        $result = $this->db->query($sql, $params)->fetch();
        
        return $result ? (bool)$result['is_enabled'] : false;
    }
} 