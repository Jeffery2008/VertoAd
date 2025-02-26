<?php
namespace VertoAD\Core\Services\Queue;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Cache;

class QueueMonitor {
    private $db;
    private $cache;
    private $statsCache = 'notification_queue_stats';
    private $statsCacheTTL = 300; // 5分钟
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
    }
    
    /**
     * 获取队列统计信息
     * @return array
     */
    public function getQueueStats(): array {
        $stats = $this->cache->get($this->statsCache);
        
        if ($stats === false) {
            $stats = $this->calculateQueueStats();
            $this->cache->set($this->statsCache, $stats, $this->statsCacheTTL);
        }
        
        return $stats;
    }
    
    /**
     * 计算队列统计信息
     * @return array
     */
    private function calculateQueueStats(): array {
        // 获取总体统计
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(CASE WHEN status = 'completed' 
                        THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) 
                        ELSE NULL END) as avg_processing_time
                FROM notification_queue";
        
        $overall = $this->db->query($sql)->fetch();
        
        // 获取按渠道统计
        $sql = "SELECT 
                    channel_type,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    AVG(CASE WHEN status = 'completed' 
                        THEN TIMESTAMPDIFF(SECOND, created_at, completed_at) 
                        ELSE NULL END) as avg_processing_time
                FROM notification_queue
                GROUP BY channel_type";
        
        $byChannel = $this->db->query($sql)->fetchAll();
        
        // 获取最近24小时的统计
        $sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM notification_queue
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY hour
                ORDER BY hour DESC";
        
        $hourly = $this->db->query($sql)->fetchAll();
        
        // 获取失败率最高的模板
        $sql = "SELECT 
                    t.name as template_name,
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN nq.status = 'failed' THEN 1 ELSE 0 END) as failed_attempts,
                    (SUM(CASE WHEN nq.status = 'failed' THEN 1 ELSE 0 END) / COUNT(*) * 100) as failure_rate
                FROM notification_queue nq
                JOIN notification_templates t ON nq.template_id = t.id
                GROUP BY t.id
                HAVING failure_rate > 0
                ORDER BY failure_rate DESC
                LIMIT 5";
        
        $problemTemplates = $this->db->query($sql)->fetchAll();
        
        return [
            'overall' => $overall,
            'by_channel' => $byChannel,
            'hourly' => $hourly,
            'problem_templates' => $problemTemplates,
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 获取队列健康状态
     * @return array
     */
    public function getQueueHealth(): array {
        $stats = $this->getQueueStats();
        
        $health = [
            'status' => 'healthy',
            'issues' => []
        ];
        
        // 检查积压情况
        if ($stats['overall']['pending'] > 1000) {
            $health['status'] = 'warning';
            $health['issues'][] = [
                'type' => 'backlog',
                'message' => "Large backlog: {$stats['overall']['pending']} pending notifications"
            ];
        }
        
        // 检查失败率
        $failureRate = ($stats['overall']['failed'] / $stats['overall']['total']) * 100;
        if ($failureRate > 10) {
            $health['status'] = 'critical';
            $health['issues'][] = [
                'type' => 'failure_rate',
                'message' => "High failure rate: " . number_format($failureRate, 2) . "%"
            ];
        }
        
        // 检查处理时间
        if ($stats['overall']['avg_processing_time'] > 30) {
            $health['status'] = 'warning';
            $health['issues'][] = [
                'type' => 'processing_time',
                'message' => "High processing time: " . number_format($stats['overall']['avg_processing_time'], 2) . " seconds"
            ];
        }
        
        return $health;
    }
    
    /**
     * 清除统计缓存
     */
    public function clearStatsCache(): void {
        $this->cache->delete($this->statsCache);
    }
} 