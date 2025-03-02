<?php

namespace App\Models;
use App\Core\Database;
use Exception;

class ActivationKey
{
    private $db;
    public function __construct()
    {
        $this->db = new Database();
    }
    
    public function create($key, $amount, $createdBy)
    {
        try {
            $stmt = $this->db->query('INSERT INTO activation_keys (`key`, amount, created_by, used_at) VALUES (?, ?, ?, NULL)', [
                $key, $amount, $createdBy
            ]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            // 捕获唯一键约束冲突
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception('Key already exists');
            }
            throw $e;
        }
    }
    
    public function getById($id)
    {
        $stmt = $this->db->query('SELECT * FROM activation_keys WHERE id = ?', [$id]);
        return $stmt->fetch();
    }
    
    public function getByKey($key)
    {
        $stmt = $this->db->query('SELECT * FROM activation_keys WHERE `key` = ?', [$key]);
        return $stmt->fetch();
    }
    
    public function useKey($key, $userId)
    {
        // 获取密钥信息
        $keyData = $this->getByKey($key);
        
        if (!$keyData) {
            throw new Exception('Invalid key');
        }
        
        if ($keyData['used_by'] !== null) {
            throw new Exception('Key has already been used');
        }
        
        // 开始事务
        $this->db->beginTransaction();
        
        try {
            // 更新密钥状态
            $stmt = $this->db->query(
                'UPDATE activation_keys SET used_by = ?, used_at = NOW() WHERE id = ?',
                [$userId, $keyData['id']]
            );
            
            // 更新用户余额
            $user = new User();
            $user->addBalance($userId, $keyData['amount']);
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function getKeysByAdmin($adminId)
    {
        $stmt = $this->db->query(
            'SELECT * FROM activation_keys WHERE created_by = ? ORDER BY created_at DESC',
            [$adminId]
        );
        return $stmt->fetchAll();
    }
} 