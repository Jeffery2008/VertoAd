<?php
namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    
    protected $allowedFields = [
        'username', 'email', 'password', 'role', 'balance',
        'status', 'last_login_at', 'created_at', 'updated_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|max_length[100]|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[6]',
        'role' => 'required|in_list[admin,advertiser,publisher]',
        'status' => 'required|in_list[active,inactive,suspended]'
    ];
    
    protected $validationMessages = [];
    protected $skipValidation = false;
    
    /**
     * 用户登录
     * 
     * @param string $username 用户名或邮箱
     * @param string $password 密码
     * @return array|false 成功返回用户数据，失败返回false
     */
    public function login($username, $password)
    {
        $user = $this->where('username', $username)
                    ->orWhere('email', $username)
                    ->where('status', 'active')
                    ->first();
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        // 更新最后登录时间
        $this->update($user['id'], [
            'last_login_at' => date('Y-m-d H:i:s')
        ]);
        
        // 去除敏感信息
        unset($user['password']);
        
        return $user;
    }
    
    /**
     * 创建新用户
     * 
     * @param string $username 用户名
     * @param string $email 邮箱
     * @param string $password 密码
     * @param string $role 角色
     * @return int|false 成功返回用户ID，失败返回false
     */
    public function register($username, $email, $password, $role = 'advertiser')
    {
        $data = [
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'balance' => 0,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($data);
    }
} 