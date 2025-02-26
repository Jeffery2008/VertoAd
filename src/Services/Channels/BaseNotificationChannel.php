<?php
namespace VertoAD\Core\Services\Channels;

use VertoAD\Core\Utils\Logger;

abstract class BaseNotificationChannel implements NotificationChannelInterface {
    protected $logger;
    protected $lastError;
    protected $config;
    
    /**
     * 构造函数
     * @param array $config 渠道配置
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->logger = new Logger('notification_channel_' . $this->getType());
    }
    
    /**
     * 获取最后一次错误信息
     * @return string|null
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }
    
    /**
     * 验证通知数据
     * @param array $notification 通知数据
     * @return bool
     */
    public function validate(array $notification): bool {
        $required = ['template_id', 'user_id', 'channel_type', 'title', 'content'];
        
        foreach ($required as $field) {
            if (!isset($notification[$field]) || empty($notification[$field])) {
                $this->lastError = "Missing required field: {$field}";
                return false;
            }
        }
        
        if ($notification['channel_type'] !== $this->getType()) {
            $this->lastError = "Invalid channel type";
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查渠道是否可用
     * @return bool
     */
    public function isAvailable(): bool {
        return true;
    }
    
    /**
     * 记录错误信息
     * @param string $message 错误信息
     * @param array $context 上下文信息
     */
    protected function logError(string $message, array $context = []): void {
        $this->lastError = $message;
        $this->logger->error($message, $context);
    }
    
    /**
     * 记录调试信息
     * @param string $message 调试信息
     * @param array $context 上下文信息
     */
    protected function logDebug(string $message, array $context = []): void {
        $this->logger->debug($message, $context);
    }
    
    /**
     * 获取配置值
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getConfig(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * 检查必要的配置是否存在
     * @param array $required 必要的配置键名数组
     * @return bool
     */
    protected function validateConfig(array $required): bool {
        foreach ($required as $key) {
            if (!isset($this->config[$key]) || empty($this->config[$key])) {
                $this->lastError = "Missing required config: {$key}";
                return false;
            }
        }
        return true;
    }
} 