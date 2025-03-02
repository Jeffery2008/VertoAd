<?php
namespace App\Models;

use CodeIgniter\Model;

class TransactionModel extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    
    protected $allowedFields = [
        'user_id', 'type', 'amount', 'balance_after', 'description',
        'reference_id', 'status', 'created_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = null;
    
    protected $validationRules = [
        'user_id' => 'required|integer',
        'type' => 'required|in_list[deposit,withdrawal,income,expense,refund]',
        'amount' => 'required|numeric',
        'balance_after' => 'required|numeric',
        'status' => 'required|in_list[pending,completed,failed,cancelled]'
    ];
    
    protected $validationMessages = [];
    protected $skipValidation = false;
    
    /**
     * 记录交易
     * 
     * @param int $userId 用户ID
     * @param string $type 交易类型
     * @param float $amount 金额
     * @param string $description 描述
     * @param int $referenceId 引用ID
     * @return int|false 成功返回交易ID，失败返回false
     */
    public function recordTransaction($userId, $type, $amount, $description, $referenceId = null)
    {
        $userModel = new UserModel();
        $user = $userModel->find($userId);
        
        if (!$user) {
            return false;
        }
        
        // 计算交易后余额
        $balanceAfter = $user['balance'];
        if (in_array($type, ['deposit', 'income', 'refund'])) {
            $balanceAfter += $amount;
        } elseif (in_array($type, ['withdrawal', 'expense'])) {
            $balanceAfter -= $amount;
        }
        
        // 更新用户余额
        $userModel->update($userId, ['balance' => $balanceAfter]);
        
        // 记录交易
        $data = [
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'reference_id' => $referenceId,
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($data);
    }
    
    /**
     * 获取用户交易历史
     * 
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 交易历史
     */
    public function getUserTransactions($userId, $limit = 20, $offset = 0)
    {
        return $this->where('user_id', $userId)
                   ->orderBy('id', 'DESC')
                   ->findAll($limit, $offset);
    }
    
    /**
     * 获取24小时内的收入
     * 
     * @return float 24小时内的收入总和
     */
    public function getLast24HoursIncome()
    {
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $result = $this->where('type', 'income')
                      ->where('status', 'completed')
                      ->where('created_at >=', $oneDayAgo)
                      ->selectSum('amount')
                      ->first();
        
        return $result ? $result['amount'] : 0;
    }
} 