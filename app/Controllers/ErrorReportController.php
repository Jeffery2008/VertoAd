<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class ErrorReportController extends Controller
{
    private $db;
    private $request;
    private $response;
    
    public function __construct()
    {
        parent::__construct();
        $this->db = new Database();
        $this->request = new Request();
        $this->response = new Response();
    }
    
    /**
     * 错误报告大屏
     */
    public function dashboard()
    {
        $this->requireRole('admin');
        
        // 获取最近的错误总数
        $totalErrors = $this->db->query(
            "SELECT COUNT(*) as count FROM errors"
        )->fetch()['count'];
        
        // 获取未解决的错误总数
        $unresolvedErrors = $this->db->query(
            "SELECT COUNT(*) as count FROM errors WHERE status = 'new' OR status = 'in_progress'"
        )->fetch()['count'];
        
        // 获取按类型分组的错误数
        $errorsByType = $this->db->query(
            "SELECT type, COUNT(*) as count FROM errors GROUP BY type ORDER BY count DESC LIMIT 10"
        )->fetchAll();
        
        // 获取24小时内的错误数
        $last24HoursErrors = $this->db->query(
            "SELECT COUNT(*) as count FROM errors WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        )->fetch()['count'];
        
        // 获取最近7天的每日错误统计
        $daily = $this->db->query(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM errors 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
             GROUP BY DATE(created_at) 
             ORDER BY date"
        )->fetchAll();
        
        // 获取最常见的错误消息
        $commonMessages = $this->db->query(
            "SELECT message, COUNT(*) as count 
             FROM errors 
             GROUP BY message 
             ORDER BY count DESC 
             LIMIT 10"
        )->fetchAll();
        
        // 获取最近的10个错误
        $recentErrors = $this->db->query(
            "SELECT id, type, message, file, line, created_at, status
             FROM errors
             ORDER BY created_at DESC
             LIMIT 10"
        )->fetchAll();
        
        $this->response->view('admin/error_dashboard', [
            'totalErrors' => $totalErrors,
            'unresolvedErrors' => $unresolvedErrors,
            'errorsByType' => $errorsByType,
            'last24HoursErrors' => $last24HoursErrors,
            'daily' => $daily,
            'commonMessages' => $commonMessages,
            'recentErrors' => $recentErrors
        ]);
    }
    
    /**
     * 错误列表页面
     */
    public function list()
    {
        $this->requireRole('admin');
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        
        $where = [];
        $params = [];
        
        if ($status) {
            $where[] = "status = ?";
            $params[] = $status;
        }
        
        if ($type) {
            $where[] = "type = ?";
            $params[] = $type;
        }
        
        if ($search) {
            $where[] = "(message LIKE ? OR file LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // 获取总记录数
        $totalQuery = "SELECT COUNT(*) as count FROM errors $whereClause";
        $total = $this->db->query($totalQuery, $params)->fetch()['count'];
        
        // 获取分页后的记录
        $query = "SELECT id, type, message, file, line, created_at, status 
                  FROM errors 
                  $whereClause 
                  ORDER BY created_at DESC 
                  LIMIT $offset, $perPage";
        
        $errors = $this->db->query($query, $params)->fetchAll();
        
        // 获取所有错误类型，用于过滤
        $types = $this->db->query(
            "SELECT DISTINCT type FROM errors ORDER BY type"
        )->fetchAll();
        
        $this->response->view('admin/error_list', [
            'errors' => $errors,
            'types' => $types,
            'status' => $status,
            'type' => $type,
            'search' => $search,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => ceil($total / $perPage)
        ]);
    }
    
    /**
     * 错误详情页面
     */
    public function view($id)
    {
        $this->requireRole('admin');
        
        $error = $this->db->query(
            "SELECT * FROM errors WHERE id = ?", 
            [$id]
        )->fetch();
        
        if (!$error) {
            $this->response->redirect('/admin/errors');
            return;
        }
        
        $this->response->view('admin/error_detail', [
            'error' => $error
        ]);
    }
    
    /**
     * 更新错误状态
     */
    public function updateStatus($id)
    {
        $this->requireRole('admin');
        
        if ($this->request->getMethod() !== 'POST') {
            $this->response->redirect('/admin/errors/view/' . $id);
            return;
        }
        
        $body = $this->request->getBody();
        $status = $body['status'] ?? 'new';
        $notes = $body['notes'] ?? '';
        
        $this->db->query(
            "UPDATE errors SET status = ?, notes = ? WHERE id = ?",
            [$status, $notes, $id]
        );
        
        $this->response->redirect('/admin/errors/view/' . $id);
    }
    
    /**
     * 获取错误统计数据API (JSON)
     */
    public function getStats()
    {
        $this->requireRole('admin');
        
        // 获取24小时内每小时的错误数
        $hourly = $this->db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour, COUNT(*) as count 
             FROM errors 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             GROUP BY hour 
             ORDER BY hour"
        )->fetchAll();
        
        // 获取各个状态的错误数
        $byStatus = $this->db->query(
            "SELECT status, COUNT(*) as count FROM errors GROUP BY status"
        )->fetchAll();
        
        $this->response->json([
            'hourly' => $hourly,
            'byStatus' => $byStatus
        ]);
    }
    
    /**
     * 批量更新错误状态
     */
    public function bulkUpdate()
    {
        $this->requireRole('admin');
        
        if ($this->request->getMethod() !== 'POST') {
            $this->response->redirect('/admin/errors');
            return;
        }
        
        $body = $this->request->getBody();
        $ids = $body['ids'] ?? [];
        $status = $body['status'] ?? 'new';
        
        if (empty($ids) || !is_array($ids)) {
            $this->response->redirect('/admin/errors');
            return;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $this->db->query(
            "UPDATE errors SET status = ? WHERE id IN ($placeholders)",
            array_merge([$status], $ids)
        );
        
        $this->response->redirect('/admin/errors');
    }
} 