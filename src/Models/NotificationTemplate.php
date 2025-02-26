<?php
namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Database;

class NotificationTemplate {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 获取所有通知模板
     * @return array
     */
    public function getAllTemplates(): array {
        $sql = "SELECT * FROM notification_templates ORDER BY id DESC";
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * 获取活动的通知模板
     * @return array
     */
    public function getActiveTemplates(): array {
        $sql = "SELECT * FROM notification_templates WHERE status = 'active' ORDER BY id DESC";
        return $this->db->query($sql)->fetchAll();
    }
    
    /**
     * 根据ID获取模板
     * @param int $id 模板ID
     * @return array|null
     */
    public function getTemplateById(int $id): ?array {
        $sql = "SELECT * FROM notification_templates WHERE id = :id";
        $params = [':id' => $id];
        $result = $this->db->query($sql, $params)->fetch();
        
        if ($result) {
            $result['variables'] = json_decode($result['variables'], true);
            $result['supported_channels'] = json_decode($result['supported_channels'], true);
            return $result;
        }
        
        return null;
    }
    
    /**
     * 根据代码获取模板
     * @param string $code 模板代码
     * @return array|null
     */
    public function getTemplateByCode(string $code): ?array {
        $sql = "SELECT * FROM notification_templates WHERE code = :code";
        $params = [':code' => $code];
        $result = $this->db->query($sql, $params)->fetch();
        
        if ($result) {
            $result['variables'] = json_decode($result['variables'], true);
            $result['supported_channels'] = json_decode($result['supported_channels'], true);
            return $result;
        }
        
        return null;
    }
    
    /**
     * 创建通知模板
     * @param array $data 模板数据
     * @return int|false 成功返回模板ID，失败返回false
     */
    public function createTemplate(array $data) {
        // 验证必填字段
        $required = ['name', 'code', 'title', 'content', 'variables', 'supported_channels'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }
        
        // 检查代码是否已存在
        if ($this->getTemplateByCode($data['code'])) {
            return false;
        }
        
        $sql = "INSERT INTO notification_templates (name, code, title, content, variables, supported_channels, status) 
                VALUES (:name, :code, :title, :content, :variables, :supported_channels, :status)";
        
        $params = [
            ':name' => $data['name'],
            ':code' => $data['code'],
            ':title' => $data['title'],
            ':content' => $data['content'],
            ':variables' => json_encode($data['variables']),
            ':supported_channels' => json_encode($data['supported_channels']),
            ':status' => $data['status'] ?? 'active'
        ];
        
        return $this->db->execute($sql, $params) ? $this->db->lastInsertId() : false;
    }
    
    /**
     * 更新通知模板
     * @param int $id 模板ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateTemplate(int $id, array $data): bool {
        $template = $this->getTemplateById($id);
        if (!$template) {
            return false;
        }
        
        // 构建更新字段
        $updates = [];
        $params = [':id' => $id];
        
        if (isset($data['name'])) {
            $updates[] = "name = :name";
            $params[':name'] = $data['name'];
        }
        
        if (isset($data['title'])) {
            $updates[] = "title = :title";
            $params[':title'] = $data['title'];
        }
        
        if (isset($data['content'])) {
            $updates[] = "content = :content";
            $params[':content'] = $data['content'];
        }
        
        if (isset($data['variables'])) {
            $updates[] = "variables = :variables";
            $params[':variables'] = json_encode($data['variables']);
        }
        
        if (isset($data['supported_channels'])) {
            $updates[] = "supported_channels = :supported_channels";
            $params[':supported_channels'] = json_encode($data['supported_channels']);
        }
        
        if (isset($data['status'])) {
            $updates[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "UPDATE notification_templates SET " . implode(", ", $updates) . " WHERE id = :id";
        return $this->db->execute($sql, $params);
    }
    
    /**
     * 删除通知模板
     * @param int $id 模板ID
     * @return bool
     */
    public function deleteTemplate(int $id): bool {
        // 检查是否有关联的通知记录
        $sql = "SELECT COUNT(*) as count FROM notifications WHERE template_id = :id";
        $params = [':id' => $id];
        $result = $this->db->query($sql, $params)->fetch();
        
        if ($result['count'] > 0) {
            // 如果有关联记录，则只标记为inactive
            return $this->updateTemplate($id, ['status' => 'inactive']);
        }
        
        // 如果没有关联记录，则物理删除
        $sql = "DELETE FROM notification_templates WHERE id = :id";
        return $this->db->execute($sql, $params);
    }
    
    /**
     * 验证模板变量
     * @param array $templateVars 模板定义的变量
     * @param array $inputVars 输入的变量
     * @return bool
     */
    public function validateVariables(array $templateVars, array $inputVars): bool {
        foreach ($templateVars as $var) {
            if (!isset($inputVars[$var['name']])) {
                return false;
            }
            
            // 如果定义了验证规则，则进行验证
            if (isset($var['validation'])) {
                $value = $inputVars[$var['name']];
                $pattern = $var['validation'];
                
                if (!preg_match($pattern, $value)) {
                    return false;
                }
            }
        }
        
        return true;
    }
} 