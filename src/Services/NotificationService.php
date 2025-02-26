<?php
namespace VertoAD\Core\Services;

use VertoAD\Core\Models\NotificationTemplate;
use VertoAD\Core\Models\NotificationChannel;
use VertoAD\Core\Services\Channels\NotificationChannelInterface;
use VertoAD\Core\Utils\Cache;
use VertoAD\Core\Utils\Logger;

class NotificationService {
    private $template;
    private $channel;
    private $cache;
    private $logger;
    private $channels = [];
    private $lastError;
    
    /**
     * 构造函数
     */
    public function __construct() {
        $this->template = new NotificationTemplate();
        $this->channel = new NotificationChannel();
        $this->cache = Cache::getInstance();
        $this->logger = new Logger('notification');
    }
    
    /**
     * 注册通知渠道
     * @param NotificationChannelInterface $channel 通知渠道实例
     * @return self
     */
    public function registerChannel(NotificationChannelInterface $channel): self {
        $this->channels[$channel->getType()] = $channel;
        return $this;
    }
    
    /**
     * 发送通知
     * @param string $templateCode 模板代码
     * @param array $variables 模板变量
     * @param int $userId 用户ID
     * @param array $options 额外选项
     * @return bool
     */
    public function send(string $templateCode, array $variables, int $userId, array $options = []): bool {
        try {
            // 获取模板
            $template = $this->getTemplate($templateCode);
            if (!$template) {
                $this->lastError = "Template not found: {$templateCode}";
                return false;
            }
            
            // 验证变量
            if (!$this->template->validateVariables($template['variables'], $variables)) {
                $this->lastError = "Invalid template variables";
                return false;
            }
            
            // 获取支持的渠道
            $supportedChannels = json_decode($template['supported_channels'], true);
            
            // 获取用户偏好设置
            $userPreferences = $this->getUserPreferences($userId, $templateCode);
            
            $success = false;
            foreach ($supportedChannels as $channelType) {
                // 检查用户是否启用了该渠道
                if (!isset($userPreferences[$channelType]) || !$userPreferences[$channelType]) {
                    continue;
                }
                
                // 检查渠道是否可用
                if (!isset($this->channels[$channelType]) || !$this->channels[$channelType]->isAvailable()) {
                    continue;
                }
                
                // 准备通知数据
                $notification = $this->prepareNotification($template, $variables, $userId, $channelType, $options);
                
                // 验证通知数据
                if (!$this->channels[$channelType]->validate($notification)) {
                    $this->logger->warning("Invalid notification data for channel: {$channelType}");
                    continue;
                }
                
                // 发送通知
                try {
                    if ($this->channels[$channelType]->send($notification)) {
                        $success = true;
                        $this->logSuccess($notification, $channelType);
                    } else {
                        $this->logError($notification, $channelType, $this->channels[$channelType]->getLastError());
                    }
                } catch (\Exception $e) {
                    $this->logError($notification, $channelType, $e->getMessage());
                }
            }
            
            return $success;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            $this->logger->error("Failed to send notification: {$e->getMessage()}", [
                'template_code' => $templateCode,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * 获取最后一次错误信息
     * @return string|null
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }
    
    /**
     * 获取通知模板
     * @param string $code 模板代码
     * @return array|null
     */
    private function getTemplate(string $code): ?array {
        // 尝试从缓存获取
        $cacheKey = "notification_template:{$code}";
        $template = $this->cache->get($cacheKey);
        
        if ($template === false) {
            // 从数据库获取
            $template = $this->template->getTemplateByCode($code);
            if ($template) {
                // 缓存模板
                $this->cache->set($cacheKey, $template, 3600); // 缓存1小时
            }
        }
        
        return $template;
    }
    
    /**
     * 获取用户通知偏好设置
     * @param int $userId 用户ID
     * @param string $templateCode 模板代码
     * @return array
     */
    private function getUserPreferences(int $userId, string $templateCode): array {
        // TODO: 实现从数据库获取用户偏好设置
        // 临时返回默认值：所有渠道都启用
        return [
            'email' => true,
            'sms' => true,
            'in_app' => true
        ];
    }
    
    /**
     * 准备通知数据
     * @param array $template 模板数据
     * @param array $variables 变量数据
     * @param int $userId 用户ID
     * @param string $channelType 渠道类型
     * @param array $options 额外选项
     * @return array
     */
    private function prepareNotification(array $template, array $variables, int $userId, string $channelType, array $options): array {
        // 替换变量
        $title = $template['title'];
        $content = $template['content'];
        
        foreach ($variables as $key => $value) {
            $title = str_replace('{'.$key.'}', $value, $title);
            $content = str_replace('{'.$key.'}', $value, $content);
        }
        
        return [
            'template_id' => $template['id'],
            'user_id' => $userId,
            'channel_type' => $channelType,
            'title' => $title,
            'content' => $content,
            'variables' => $variables,
            'options' => $options
        ];
    }
    
    /**
     * 记录成功发送日志
     * @param array $notification 通知数据
     * @param string $channelType 渠道类型
     */
    private function logSuccess(array $notification, string $channelType): void {
        $this->logger->info("Notification sent successfully", [
            'template_id' => $notification['template_id'],
            'user_id' => $notification['user_id'],
            'channel_type' => $channelType
        ]);
    }
    
    /**
     * 记录发送错误日志
     * @param array $notification 通知数据
     * @param string $channelType 渠道类型
     * @param string $error 错误信息
     */
    private function logError(array $notification, string $channelType, string $error): void {
        $this->logger->error("Failed to send notification", [
            'template_id' => $notification['template_id'],
            'user_id' => $notification['user_id'],
            'channel_type' => $channelType,
            'error' => $error
        ]);
    }
} 