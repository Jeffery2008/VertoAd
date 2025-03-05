<?php

namespace App\Models;

use App\Core\Database;

class KeyModel
{
    private $db;
    private $table = 'activation_keys';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * 生成激活码
     */
    public function generateKey($amount, $prefix = '')
    {
        // 生成类似Windows激活码格式的密钥：XXXXX-XXXXX-XXXXX-XXXXX-XXXXX
        $segments = [];
        for ($i = 0; $i < 5; $i++) {
            $segment = '';
            for ($j = 0; $j < 5; $j++) {
                // 使用大写字母和数字
                $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 排除容易混淆的字符
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }
        $key = implode('-', $segments);
        
        if ($prefix) {
            $key = $prefix . '-' . $key;
        }

        // 插入数据库
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (key_code, amount, created_at, status)
            VALUES (?, ?, NOW(), 'unused')
        ");
        $stmt->execute([$key, $amount]);

        return $key;
    }

    /**
     * 获取最近生成的激活码
     */
    public function getRecentKeys($limit = 10)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * 获取统计信息
     */
    public function getKeyStats()
    {
        $stats = [
            'total' => 0,
            'used' => 0,
            'unused' => 0,
            'total_amount' => 0
        ];

        // 获取总数和状态统计
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'used' THEN 1 ELSE 0 END) as used,
                SUM(CASE WHEN status = 'unused' THEN 1 ELSE 0 END) as unused,
                SUM(amount) as total_amount
            FROM {$this->table}
        ");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            $stats = array_merge($stats, $result);
        }

        return $stats;
    }

    /**
     * 使用激活码
     */
    public function useKey($keyCode)
    {
        // 检查密钥是否存在且未使用
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table}
            WHERE key_code = ? AND status = 'unused'
            LIMIT 1
        ");
        $stmt->execute([$keyCode]);
        $key = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$key) {
            throw new \Exception('Invalid or already used key');
        }

        // 更新密钥状态
        $stmt = $this->db->prepare("
            UPDATE {$this->table}
            SET status = 'used', used_at = NOW()
            WHERE key_code = ?
        ");
        $stmt->execute([$keyCode]);

        return $key['amount'];
    }

    public function searchKeys($query, $status = null, $limit = 50) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if ($query) {
            $sql .= " AND key_code LIKE ?";
            $params[] = "%{$query}%";
        }

        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function deleteKey($keyCode) {
        $stmt = $this->db->prepare("
            DELETE FROM {$this->table}
            WHERE key_code = ? AND status = 'unused'
        ");
        return $stmt->execute([$keyCode]);
    }

    public function bulkGenerateKeys($amount, $count, $prefix = '') {
        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $keys[] = $this->generateKey($amount, $prefix);
        }
        return $keys;
    }
} 