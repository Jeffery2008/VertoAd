<?php
namespace VertoAD\Core\Controllers;

use VertoAD\Core\Models\NotificationPreference;
use VertoAD\Core\Models\NotificationTemplate;
use VertoAD\Core\Models\NotificationChannel;

class NotificationPreferenceController extends BaseController {
    private $preference;
    private $template;
    private $channel;
    
    public function __construct() {
        parent::__construct();
        $this->preference = new NotificationPreference();
        $this->template = new NotificationTemplate();
        $this->channel = new NotificationChannel();
    }
    
    /**
     * 显示用户通知偏好设置页面
     */
    public function index() {
        // 获取当前用户ID
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->redirect('/login');
            return;
        }
        
        // 获取所有活动的通知模板
        $templates = $this->template->getActiveTemplates();
        
        // 获取用户的偏好设置
        $preferences = $this->preference->getUserPreferences($userId);
        
        // 获取可用的通知渠道
        $channels = $this->channel->getEnabledChannels();
        
        // 整理数据
        $data = [];
        foreach ($templates as $template) {
            $templateId = $template['id'];
            $supportedChannels = json_decode($template['supported_channels'], true);
            
            $data[$templateId] = [
                'name' => $template['name'],
                'code' => $template['code'],
                'channels' => []
            ];
            
            foreach ($supportedChannels as $channelType) {
                $isEnabled = false;
                
                // 查找用户设置
                foreach ($preferences as $pref) {
                    if ($pref['template_id'] == $templateId && $pref['channel_type'] == $channelType) {
                        $isEnabled = (bool)$pref['is_enabled'];
                        break;
                    }
                }
                
                // 如果没有找到设置，使用默认值
                if (!isset($pref)) {
                    $isEnabled = $channelType === 'in_app';
                }
                
                $data[$templateId]['channels'][$channelType] = $isEnabled;
            }
        }
        
        $this->render('user/notification_preferences', [
            'templates' => $data,
            'channels' => $channels,
            'title' => '通知偏好设置'
        ]);
    }
    
    /**
     * 更新通知偏好设置
     */
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $templateId = $_POST['template_id'] ?? 0;
        $channels = $_POST['channels'] ?? [];
        
        if (!$templateId || empty($channels)) {
            $this->jsonResponse(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        // 验证模板是否存在且处于活动状态
        $template = $this->template->getTemplateById($templateId);
        if (!$template || $template['status'] !== 'active') {
            $this->jsonResponse(['success' => false, 'message' => '无效的通知模板']);
            return;
        }
        
        // 验证渠道
        $supportedChannels = json_decode($template['supported_channels'], true);
        foreach ($channels as $channel => $enabled) {
            if (!in_array($channel, $supportedChannels)) {
                $this->jsonResponse(['success' => false, 'message' => '无效的通知渠道']);
                return;
            }
            
            // 站内信不允许禁用
            if ($channel === 'in_app' && !$enabled) {
                $this->jsonResponse(['success' => false, 'message' => '站内信不能禁用']);
                return;
            }
        }
        
        // 更新设置
        $success = $this->preference->updatePreferences($userId, $templateId, $channels);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '更新成功' : '更新失败'
        ]);
    }
    
    /**
     * 批量更新通知偏好设置
     */
    public function bulkUpdate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $preferences = $_POST['preferences'] ?? [];
        if (empty($preferences)) {
            $this->jsonResponse(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        // 验证数据
        foreach ($preferences as $templateId => $channels) {
            $template = $this->template->getTemplateById($templateId);
            if (!$template || $template['status'] !== 'active') {
                $this->jsonResponse(['success' => false, 'message' => '无效的通知模板']);
                return;
            }
            
            $supportedChannels = json_decode($template['supported_channels'], true);
            foreach ($channels as $channel => $enabled) {
                if (!in_array($channel, $supportedChannels)) {
                    $this->jsonResponse(['success' => false, 'message' => '无效的通知渠道']);
                    return;
                }
                
                if ($channel === 'in_app' && !$enabled) {
                    $this->jsonResponse(['success' => false, 'message' => '站内信不能禁用']);
                    return;
                }
            }
        }
        
        // 批量更新
        $success = $this->preference->setBulkPreferences($userId, $preferences);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '更新成功' : '更新失败'
        ]);
    }
    
    /**
     * 重置为默认设置
     */
    public function resetToDefault() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $templateId = $_POST['template_id'] ?? 0;
        if (!$templateId) {
            $this->jsonResponse(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        // 获取默认设置
        $defaultPreferences = $this->preference->getDefaultPreferences($templateId);
        if (empty($defaultPreferences)) {
            $this->jsonResponse(['success' => false, 'message' => '无效的通知模板']);
            return;
        }
        
        // 更新设置
        $success = $this->preference->updatePreferences($userId, $templateId, $defaultPreferences);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '重置成功' : '重置失败'
        ]);
    }
} 