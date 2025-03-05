<?php

namespace App\Models;

use CodeIgniter\Model;

class KeyModel extends Model
{
    protected $table = 'activation_keys';
    protected $primaryKey = 'id';
    protected $allowedFields = ['code', 'amount', 'prefix', 'used', 'used_at', 'used_by', 'created_at'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * 生成激活码
     */
    public function generateKeys($amount, $quantity, $prefix = '')
    {
        $keys = [];
        for ($i = 0; $i < $quantity; $i++) {
            $code = $this->generateUniqueKey($prefix);
            $data = [
                'code' => $code,
                'amount' => $amount,
                'prefix' => $prefix,
                'used' => false,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->insert($data);
            $keys[] = $data;
        }
        
        return $keys;
    }

    /**
     * 生成唯一的激活码
     */
    private function generateUniqueKey($prefix = '')
    {
        do {
            // 生成20位随机字符（不包括前缀）
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = '';
            for ($i = 0; $i < 20; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            
            // 添加前缀
            $fullCode = $prefix . $code;
            
            // 检查是否已存在
            $exists = $this->where('code', $fullCode)->first();
        } while ($exists);
        
        return $fullCode;
    }

    /**
     * 获取最近生成的激活码
     */
    public function getRecentKeys($limit = 50)
    {
        return $this->orderBy('created_at', 'DESC')
                    ->limit($limit)
                    ->find();
    }

    /**
     * 获取所有激活码
     */
    public function getAllKeys()
    {
        return $this->orderBy('created_at', 'DESC')->find();
    }

    /**
     * 获取统计信息
     */
    public function getStats()
    {
        $today = date('Y-m-d');
        
        // 今日生成数量
        $todayGenerated = $this->where('DATE(created_at)', $today)->countAllResults();
        
        // 今日使用数量
        $todayUsed = $this->where('DATE(used_at)', $today)
                         ->where('used', true)
                         ->countAllResults();
        
        // 未使用数量
        $unusedCount = $this->where('used', false)->countAllResults();
        
        // 未使用总金额
        $unusedAmount = $this->selectSum('amount')
                            ->where('used', false)
                            ->get()
                            ->getRow()
                            ->amount ?? 0;
        
        return [
            'today_generated' => $todayGenerated,
            'today_used' => $todayUsed,
            'unused_count' => $unusedCount,
            'unused_amount' => $unusedAmount
        ];
    }

    /**
     * 使用激活码
     */
    public function useKey($code, $userId)
    {
        $key = $this->where('code', $code)
                    ->where('used', false)
                    ->first();
        
        if (!$key) {
            throw new \Exception('激活码无效或已被使用');
        }
        
        $this->update($key['id'], [
            'used' => true,
            'used_at' => date('Y-m-d H:i:s'),
            'used_by' => $userId
        ]);
        
        return $key;
    }
} 