<?php
namespace VertoAD\Core\Controllers;

use VertoAD\Core\Models\NotificationChannel;

class NotificationChannelController extends BaseController {
    private $notificationChannel;
    
    public function __construct() {
        parent::__construct();
        $this->notificationChannel = new NotificationChannel();
    }
    
    /**
     * 显示通知渠道管理页面
     */
    public function index() {
        // 获取所有通知渠道
        $channels = $this->notificationChannel->getAllChannels();
        
        // 渲染视图
        $this->render('admin/notification/channels', [
            'channels' => $channels,
            'title' => '通知渠道管理'
        ]);
    }
    
    /**
     * 更新通知渠道状态
     */
    public function updateStatus() {
        // 检查请求方法
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        // 验证参数
        $channelType = $_POST['channel_type'] ?? '';
        $isEnabled = isset($_POST['is_enabled']) ? (bool)$_POST['is_enabled'] : null;
        
        if (empty($channelType) || $isEnabled === null) {
            $this->jsonResponse(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        // 站内信不允许禁用
        if ($channelType === 'in_app' && !$isEnabled) {
            $this->jsonResponse(['success' => false, 'message' => '站内信不能禁用']);
            return;
        }
        
        // 更新状态
        $success = $this->notificationChannel->updateChannelStatus($channelType, $isEnabled);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '更新成功' : '更新失败'
        ]);
    }
    
    /**
     * 更新通知渠道配置
     */
    public function updateConfig() {
        // 检查请求方法
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        // 验证参数
        $channelType = $_POST['channel_type'] ?? '';
        $config = $_POST['config'] ?? '';
        
        if (empty($channelType) || empty($config)) {
            $this->jsonResponse(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        // 解析配置
        $configArray = json_decode($config, true);
        if (!$configArray) {
            $this->jsonResponse(['success' => false, 'message' => '配置格式错误']);
            return;
        }
        
        // 验证配置
        if (!$this->validateConfig($channelType, $configArray)) {
            $this->jsonResponse(['success' => false, 'message' => '配置验证失败']);
            return;
        }
        
        // 更新配置
        $success = $this->notificationChannel->updateChannelConfig($channelType, $configArray);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '更新成功' : '更新失败'
        ]);
    }
    
    /**
     * 验证渠道配置
     * @param string $channelType 渠道类型
     * @param array $config 配置信息
     * @return bool
     */
    private function validateConfig(string $channelType, array $config): bool {
        switch ($channelType) {
            case 'email':
                return $this->validateEmailConfig($config);
            case 'sms':
                return $this->validateSmsConfig($config);
            case 'in_app':
                return $this->validateInAppConfig($config);
            default:
                return false;
        }
    }
    
    /**
     * 验证邮件配置
     * @param array $config 配置信息
     * @return bool
     */
    private function validateEmailConfig(array $config): bool {
        $required = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'from_email', 'from_name'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 验证短信配置
     * @param array $config 配置信息
     * @return bool
     */
    private function validateSmsConfig(array $config): bool {
        $required = ['api_url', 'api_key', 'api_secret'];
        foreach ($required as $field) {
            if (!isset($config[$field]) || empty($config[$field])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * 验证站内信配置
     * @param array $config 配置信息
     * @return bool
     */
    private function validateInAppConfig(array $config): bool {
        return isset($config['queue']) && !empty($config['queue']);
    }
} 