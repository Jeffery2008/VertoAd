<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ErrorReportModel;

class ErrorReportController extends BaseController
{
    protected $errorReportModel;
    
    public function __construct()
    {
        $this->errorReportModel = new ErrorReportModel();
    }
    
    /**
     * 确保用户是管理员
     */
    private function ensureAdmin()
    {
        $session = session();
        
        if (!$session->has('user_id') || $session->get('role') !== 'admin') {
            return $this->response->setStatusCode(403)->setJSON([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this resource'
            ]);
        }
        
        return null;
    }
    
    /**
     * 获取错误统计数据
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getErrorStats()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        // 总错误数
        $totalErrors = $this->errorReportModel->countAllResults();
        
        // 未解决错误数
        $unresolvedErrors = $this->errorReportModel
            ->whereIn('status', ['new', 'in_progress'])
            ->countAllResults();
        
        // 24小时内错误数
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $last24HoursErrors = $this->errorReportModel
            ->where('created_at >=', $oneDayAgo)
            ->countAllResults();
        
        return $this->response->setJSON([
            'totalErrors' => $totalErrors,
            'unresolvedErrors' => $unresolvedErrors,
            'last24HoursErrors' => $last24HoursErrors
        ]);
    }
    
    /**
     * 获取错误类型列表
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getErrorTypes()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $types = $this->errorReportModel
            ->select('type, COUNT(*) as count')
            ->groupBy('type')
            ->orderBy('count', 'DESC')
            ->findAll();
        
        return $this->response->setJSON($types);
    }
    
    /**
     * 获取错误列表（带分页和筛选）
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getErrors()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $page = $this->request->getGet('page') ?? 1;
        $limit = $this->request->getGet('limit') ?? 15;
        $status = $this->request->getGet('status') ?? '';
        $type = $this->request->getGet('type') ?? '';
        $search = $this->request->getGet('search') ?? '';
        
        // 基本查询
        $builder = $this->errorReportModel->builder();
        
        // 状态筛选
        if (!empty($status)) {
            $builder->where('status', $status);
        }
        
        // 类型筛选
        if (!empty($type)) {
            $builder->where('type', $type);
        }
        
        // 搜索
        if (!empty($search)) {
            $builder->groupStart()
                ->like('message', $search)
                ->orLike('file', $search)
                ->groupEnd();
        }
        
        // 排序
        $builder->orderBy('id', 'DESC');
        
        // 分页
        $errors = $this->errorReportModel->paginate($limit, 'default', $page);
        $pager = $this->errorReportModel->pager;
        
        return $this->response->setJSON([
            'errors' => $errors,
            'total' => $pager->getTotal(),
            'currentPage' => $page,
            'totalPages' => $pager->getPageCount()
        ]);
    }
    
    /**
     * 获取单个错误详情
     * 
     * @param int $id 错误ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function getError($id)
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $errorReport = $this->errorReportModel->find($id);
        
        if (!$errorReport) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Not Found',
                'message' => 'Error report not found'
            ]);
        }
        
        return $this->response->setJSON($errorReport);
    }
    
    /**
     * 更新错误状态
     * 
     * @param int $id 错误ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function updateStatus($id)
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $errorReport = $this->errorReportModel->find($id);
        
        if (!$errorReport) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => 'Not Found',
                'message' => 'Error report not found'
            ]);
        }
        
        $status = $this->request->getJSON(true)['status'] ?? '';
        $notes = $this->request->getJSON(true)['notes'] ?? '';
        
        if (!in_array($status, ['new', 'in_progress', 'resolved', 'ignored'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Bad Request',
                'message' => 'Invalid status value'
            ]);
        }
        
        $updated = $this->errorReportModel->update($id, [
            'status' => $status,
            'notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        if ($updated) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Error status updated successfully'
            ]);
        } else {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Server Error',
                'message' => 'Failed to update error status'
            ]);
        }
    }
    
    /**
     * 批量更新错误状态
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function bulkUpdateStatus()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $data = $this->request->getJSON(true);
        $ids = $data['ids'] ?? [];
        $status = $data['status'] ?? '';
        
        if (empty($ids) || !is_array($ids)) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Bad Request',
                'message' => 'No error IDs provided'
            ]);
        }
        
        if (!in_array($status, ['in_progress', 'resolved', 'ignored'])) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => 'Bad Request',
                'message' => 'Invalid status value'
            ]);
        }
        
        $updated = $this->errorReportModel->whereIn('id', $ids)
            ->set([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ])
            ->update();
        
        if ($updated) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Errors updated successfully',
                'updatedCount' => count($ids)
            ]);
        } else {
            return $this->response->setStatusCode(500)->setJSON([
                'error' => 'Server Error',
                'message' => 'Failed to update errors'
            ]);
        }
    }
    
    /**
     * 获取最近错误
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getRecentErrors()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $limit = $this->request->getGet('limit') ?? 10;
        
        $recentErrors = $this->errorReportModel
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->find();
        
        return $this->response->setJSON($recentErrors);
    }
    
    /**
     * 获取每日错误趋势
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getDailyErrors()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $db = \Config\Database::connect();
        
        // 获取最近7天的数据
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
        
        $query = $db->query("
            SELECT DATE(created_at) AS date, COUNT(*) AS count
            FROM error_reports
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", [$sevenDaysAgo]);
        
        $result = $query->getResultArray();
        
        return $this->response->setJSON($result);
    }
    
    /**
     * 获取按类型分组的错误
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getErrorsByType()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $result = $this->errorReportModel
            ->select('type, COUNT(*) as count')
            ->groupBy('type')
            ->orderBy('count', 'DESC')
            ->findAll();
        
        return $this->response->setJSON($result);
    }
    
    /**
     * 获取每小时错误分布
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getHourlyErrors()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $db = \Config\Database::connect();
        
        // 获取24小时内的数据
        $oneDayAgo = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $query = $db->query("
            SELECT HOUR(created_at) AS hour, COUNT(*) AS count
            FROM error_reports
            WHERE created_at >= ?
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ", [$oneDayAgo]);
        
        $result = $query->getResultArray();
        
        return $this->response->setJSON($result);
    }
    
    /**
     * 获取常见错误消息
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getCommonMessages()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $result = $this->errorReportModel
            ->select('message, COUNT(*) as count')
            ->groupBy('message')
            ->orderBy('count', 'DESC')
            ->limit(10)
            ->findAll();
        
        return $this->response->setJSON($result);
    }
    
    /**
     * 获取相似错误
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function getSimilarErrors()
    {
        $error = $this->ensureAdmin();
        if ($error) return $error;
        
        $type = $this->request->getGet('type') ?? '';
        $exclude = $this->request->getGet('exclude') ?? 0;
        $limit = $this->request->getGet('limit') ?? 5;
        
        if (empty($type)) {
            return $this->response->setJSON([]);
        }
        
        $similarErrors = $this->errorReportModel
            ->where('type', $type)
            ->where('id !=', $exclude)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->find();
        
        return $this->response->setJSON($similarErrors);
    }
} 