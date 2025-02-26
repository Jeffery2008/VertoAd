<?php
namespace VertoAD\Core\Controllers;

use VertoAD\Core\Services\Queue\QueueMonitor;

class NotificationQueueController extends BaseController {
    private $queueMonitor;
    
    public function __construct() {
        parent::__construct();
        $this->queueMonitor = new QueueMonitor();
    }
    
    /**
     * 显示队列监控页面
     */
    public function index() {
        // 检查管理员权限
        if (!$this->isAdmin()) {
            $this->redirect('/admin/login');
            return;
        }
        
        $stats = $this->queueMonitor->getQueueStats();
        $health = $this->queueMonitor->getQueueHealth();
        
        $this->render('admin/notification/queue', [
            'stats' => $stats,
            'health' => $health,
            'title' => '通知队列监控'
        ]);
    }
    
    /**
     * 刷新队列统计信息
     */
    public function refreshStats() {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['success' => false, 'message' => '无权限']);
            return;
        }
        
        $this->queueMonitor->clearStatsCache();
        $stats = $this->queueMonitor->getQueueStats();
        $health = $this->queueMonitor->getQueueHealth();
        
        $this->jsonResponse([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'health' => $health
            ]
        ]);
    }
    
    /**
     * 获取队列健康状态
     */
    public function getHealth() {
        if (!$this->isAdmin()) {
            $this->jsonResponse(['success' => false, 'message' => '无权限']);
            return;
        }
        
        $health = $this->queueMonitor->getQueueHealth();
        
        $this->jsonResponse([
            'success' => true,
            'data' => $health
        ]);
    }
} 