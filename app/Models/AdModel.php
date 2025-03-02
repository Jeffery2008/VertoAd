<?php
namespace App\Models;

use CodeIgniter\Model;

class AdModel extends Model
{
    protected $table = 'ads';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    
    protected $allowedFields = [
        'user_id', 'title', 'description', 'url', 'image_url',
        'budget', 'daily_budget', 'bid', 'status', 'target_audience',
        'start_date', 'end_date', 'created_at', 'updated_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    protected $validationRules = [
        'user_id' => 'required|integer',
        'title' => 'required|max_length[100]',
        'description' => 'required|max_length[200]',
        'url' => 'required|valid_url|max_length[255]',
        'budget' => 'required|numeric',
        'daily_budget' => 'required|numeric',
        'bid' => 'required|numeric',
        'status' => 'required|in_list[pending,active,paused,rejected,completed]'
    ];
    
    protected $validationMessages = [];
    protected $skipValidation = false;
    
    /**
     * 获取广告主的广告
     * 
     * @param int $userId 广告主ID
     * @return array 广告列表
     */
    public function getAdvertiserAds($userId)
    {
        return $this->where('user_id', $userId)
                   ->orderBy('id', 'DESC')
                   ->findAll();
    }
    
    /**
     * 获取活跃广告
     * 
     * @return array 活跃广告列表
     */
    public function getActiveAds()
    {
        return $this->where('status', 'active')
                   ->where('start_date <=', date('Y-m-d'))
                   ->where('end_date >=', date('Y-m-d'))
                   ->findAll();
    }
} 