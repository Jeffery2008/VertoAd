<?php
namespace App\Models;

use CodeIgniter\Model;

class ErrorReportModel extends Model
{
    protected $table = 'error_reports';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    
    protected $allowedFields = [
        'type', 'message', 'file', 'line', 'trace', 'request_data',
        'user_id', 'status', 'notes', 'ip_address', 'user_agent',
        'created_at', 'updated_at'
    ];
    
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    protected $validationRules = [
        'type' => 'required|max_length[50]',
        'message' => 'required',
        'file' => 'required|max_length[255]',
        'line' => 'required|integer',
        'status' => 'required|in_list[new,in_progress,resolved,ignored]'
    ];
    
    protected $validationMessages = [];
    protected $skipValidation = false;
    
    /**
     * 记录错误
     * 
     * @param string $type 错误类型
     * @param string $message 错误消息
     * @param string $file 文件路径
     * @param int $line 行号
     * @param string $trace 堆栈跟踪
     * @param array $requestData 请求数据
     * @return bool|int 成功返回插入ID，失败返回false
     */
    public function logError($type, $message, $file, $line, $trace = null, $requestData = null)
    {
        $request = \Config\Services::request();
        $session = session();
        
        $data = [
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace' => $trace,
            'request_data' => is_array($requestData) ? json_encode($requestData) : $requestData,
            'user_id' => $session->has('user_id') ? $session->get('user_id') : null,
            'status' => 'new',
            'ip_address' => $request->getIPAddress(),
            'user_agent' => $request->getUserAgent()->getAgentString(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->insert($data);
    }
    
    /**
     * 获取错误统计
     * 
     * @return array 错误统计数据
     */
    public function getStatistics()
    {
        $db = \Config\Database::connect();
        
        // 总错误数
        $totalErrors = $this->countAllResults();
        
        // 未解决错误数
        $unresolvedErrors = $this->whereIn('status', ['new', 'in_progress'])->countAllResults();
        
        // 24小时内的错误数
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $last24HoursErrors = $this->where('created_at >=', $oneDayAgo)->countAllResults();
        
        // 按状态分组
        $query = $db->query("
            SELECT status, COUNT(*) as count
            FROM {$this->table}
            GROUP BY status
        ");
        $statusCounts = [];
        foreach ($query->getResult() as $row) {
            $statusCounts[$row->status] = $row->count;
        }
        
        // 按类型分组
        $query = $db->query("
            SELECT type, COUNT(*) as count
            FROM {$this->table}
            GROUP BY type
            ORDER BY count DESC
            LIMIT 10
        ");
        $typeCounts = [];
        foreach ($query->getResult() as $row) {
            $typeCounts[$row->type] = $row->count;
        }
        
        return [
            'totalErrors' => $totalErrors,
            'unresolvedErrors' => $unresolvedErrors,
            'last24HoursErrors' => $last24HoursErrors,
            'statusCounts' => $statusCounts,
            'typeCounts' => $typeCounts
        ];
    }
} 