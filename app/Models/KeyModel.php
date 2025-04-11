<?php

namespace App\Models;

use App\Core\Database;

class KeyModel
{
    protected $db;
    protected $table = 'activation_keys';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 获取激活码统计数据
     */
    public function getStats()
    {
        try {
            // 获取总数
            $total = $this->db->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
            
            // 获取未使用数量
            $unused = $this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = 'unused'")->fetchColumn();
            
            // 获取已使用数量
            $used = $this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE status = 'used'")->fetchColumn();
            
            // 获取总金额
            $totalAmount = $this->db->query("SELECT COALESCE(SUM(amount), 0) FROM {$this->table}")->fetchColumn();
            
            // 获取未使用金额
            $unusedAmount = $this->db->query("SELECT COALESCE(SUM(amount), 0) FROM {$this->table} WHERE status = 'unused'")->fetchColumn();
            
            return [
                'total' => (int)$total,
                'unused' => (int)$unused,
                'used' => (int)$used,
                'total_amount' => (float)$totalAmount,
                'unused_amount' => (float)$unusedAmount
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to get key stats: " . $e->getMessage());
        }
    }

    /**
     * 生成新的激活码
     */
    public function generate($amount, $count = 1)
    {
        try {
            $keys = [];
            $this->db->beginTransaction();

            for ($i = 0; $i < $count; $i++) {
                $keyCode = $this->generateUniqueKey();
                
                $stmt = $this->db->prepare("
                    INSERT INTO {$this->table} (key_code, amount, status, created_at) 
                    VALUES (?, ?, 'unused', NOW())
                ");
                
                $stmt->execute([$keyCode, $amount]);
                $keys[] = [
                    'id' => $this->db->lastInsertId(),
                    'key_code' => $keyCode,
                    'amount' => $amount
                ];
            }

            $this->db->commit();
            return $keys;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("Failed to generate keys: " . $e->getMessage());
        }
    }

    /**
     * 生成唯一的激活码
     */
    protected function generateUniqueKey($length = 16)
    {
        do {
            $key = strtoupper(bin2hex(random_bytes($length / 2)));
            $exists = $this->db->query(
                "SELECT COUNT(*) FROM {$this->table} WHERE key_code = ?", 
                [$key]
            )->fetchColumn();
        } while ($exists > 0);

        return $key;
    }

    /**
     * 获取激活码列表
     */
    public function getList($page = 1, $limit = 10, $status = null)
    {
        try {
            // 确保参数是整数
            $page = max(1, (int)$page);
            $limit = max(1, (int)$limit);
            $offset = ($page - 1) * $limit;

            $where = [];
            $params = [];

            if ($status) {
                $where[] = "status = ?";
                $params[] = $status;
            }

            $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

            // 获取总数
            $total = $this->db->query(
                "SELECT COUNT(*) FROM {$this->table} $whereClause",
                $params
            )->fetchColumn();

            // 获取数据
            $sql = "SELECT 
                    id,
                    key_code,
                    amount,
                    status,
                    created_at,
                    used_at,
                    used_by
                FROM {$this->table}
                $whereClause
                ORDER BY created_at DESC
                LIMIT $offset, $limit";

            $keys = $this->db->query($sql, $params)->fetchAll();

            return [
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
                'keys' => $keys
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to get key list: " . $e->getMessage());
        }
    }

    /**
     * 使用激活码
     */
    public function use($keyCode, $userId)
    {
        try {
            $this->db->beginTransaction();

            // 检查激活码是否存在且未使用
            $key = $this->db->query("
                SELECT * FROM {$this->table} 
                WHERE key_code = ? AND status = 'unused'
                FOR UPDATE
            ", [$keyCode])->fetch();

            if (!$key) {
                throw new \Exception("Invalid or used key");
            }

            // 更新激活码状态
            $this->db->query("
                UPDATE {$this->table}
                SET status = 'used', used_at = NOW(), used_by = ?
                WHERE id = ?
            ", [$userId, $key['id']]);

            // 更新用户余额
            $this->db->query("
                UPDATE users
                SET balance = balance + ?
                WHERE id = ?
            ", [$key['amount'], $userId]);

            $this->db->commit();
            return [
                'amount' => $key['amount'],
                'key_code' => $key['key_code']
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("Failed to use key: " . $e->getMessage());
        }
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