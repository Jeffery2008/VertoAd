<?php
namespace VertoAD\Core\Controllers;

use VertoAD\Core\Models\NotificationTemplate;
use VertoAD\Core\Models\NotificationChannel;

class NotificationTemplateController extends BaseController {
    private $notificationTemplate;
    private $notificationChannel;
    
    public function __construct() {
        parent::__construct();
        $this->notificationTemplate = new NotificationTemplate();
        $this->notificationChannel = new NotificationChannel();
    }
    
    /**
     * 显示模板列表页面
     */
    public function index() {
        $templates = $this->notificationTemplate->getAllTemplates();
        $channels = $this->notificationChannel->getAllChannels();
        
        $this->render('admin/notification/templates', [
            'templates' => $templates,
            'channels' => $channels,
            'title' => '通知模板管理'
        ]);
    }
    
    /**
     * 显示创建模板页面
     */
    public function create() {
        $channels = $this->notificationChannel->getAllChannels();
        
        $this->render('admin/notification/template_form', [
            'channels' => $channels,
            'title' => '创建通知模板',
            'template' => null
        ]);
    }
    
    /**
     * 显示编辑模板页面
     */
    public function edit() {
        $id = $_GET['id'] ?? 0;
        $template = $this->notificationTemplate->getTemplateById((int)$id);
        
        if (!$template) {
            $this->redirect('/admin/notification/templates');
            return;
        }
        
        $channels = $this->notificationChannel->getAllChannels();
        
        $this->render('admin/notification/template_form', [
            'template' => $template,
            'channels' => $channels,
            'title' => '编辑通知模板'
        ]);
    }
    
    /**
     * 保存模板（创建或更新）
     */
    public function save() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $id = $_POST['id'] ?? 0;
        $data = [
            'name' => $_POST['name'] ?? '',
            'code' => $_POST['code'] ?? '',
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'variables' => json_decode($_POST['variables'] ?? '[]', true),
            'supported_channels' => $_POST['supported_channels'] ?? [],
            'status' => $_POST['status'] ?? 'active'
        ];
        
        // 验证数据
        if (!$this->validateTemplateData($data)) {
            $this->jsonResponse(['success' => false, 'message' => '数据验证失败']);
            return;
        }
        
        // 保存数据
        if ($id) {
            // 更新
            $success = $this->notificationTemplate->updateTemplate((int)$id, $data);
            $message = $success ? '更新成功' : '更新失败';
        } else {
            // 创建
            $success = $this->notificationTemplate->createTemplate($data);
            $message = $success ? '创建成功' : '创建失败';
        }
        
        $this->jsonResponse([
            'success' => (bool)$success,
            'message' => $message
        ]);
    }
    
    /**
     * 删除模板
     */
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $id = $_POST['id'] ?? 0;
        if (!$id) {
            $this->jsonResponse(['success' => false, 'message' => '参数错误']);
            return;
        }
        
        $success = $this->notificationTemplate->deleteTemplate((int)$id);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '删除成功' : '删除失败'
        ]);
    }
    
    /**
     * 验证模板数据
     * @param array $data 模板数据
     * @return bool
     */
    private function validateTemplateData(array $data): bool {
        // 检查必填字段
        $required = ['name', 'code', 'title', 'content'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        // 验证代码格式（只允许字母、数字和下划线）
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['code'])) {
            return false;
        }
        
        // 验证变量格式
        if (!is_array($data['variables'])) {
            return false;
        }
        
        foreach ($data['variables'] as $var) {
            if (!isset($var['name']) || !isset($var['description'])) {
                return false;
            }
        }
        
        // 验证支持的渠道
        if (empty($data['supported_channels'])) {
            return false;
        }
        
        foreach ($data['supported_channels'] as $channel) {
            if (!in_array($channel, ['email', 'sms', 'in_app'])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 预览模板
     */
    public function preview() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $templateId = $_POST['template_id'] ?? 0;
        $variables = json_decode($_POST['variables'] ?? '[]', true);
        
        $template = $this->notificationTemplate->getTemplateById((int)$templateId);
        if (!$template) {
            $this->jsonResponse(['success' => false, 'message' => '模板不存在']);
            return;
        }
        
        // 验证变量
        if (!$this->notificationTemplate->validateVariables($template['variables'], $variables)) {
            $this->jsonResponse(['success' => false, 'message' => '变量验证失败']);
            return;
        }
        
        // 替换变量
        $content = $template['content'];
        foreach ($variables as $key => $value) {
            $content = str_replace('{'.$key.'}', $value, $content);
        }
        
        $this->jsonResponse([
            'success' => true,
            'data' => [
                'title' => $template['title'],
                'content' => $content
            ]
        ]);
    }
} 