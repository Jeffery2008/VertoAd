<?php
namespace VertoAD\Core\Commands;

use VertoAD\Core\Services\Queue\NotificationQueueHandler;
use VertoAD\Core\Utils\Logger;

class ProcessNotificationQueue {
    private $queueHandler;
    private $logger;
    private $running = true;
    private $processLimit = 50;
    private $sleepTime = 10; // 休眠10秒
    private $lockFile;
    
    public function __construct() {
        $this->queueHandler = new NotificationQueueHandler();
        $this->logger = new Logger('notification_queue');
        $this->lockFile = sys_get_temp_dir() . '/notification_queue.lock';
    }
    
    /**
     * 运行队列处理器
     */
    public function run(): void {
        // 检查是否已经有实例在运行
        if (!$this->acquireLock()) {
            echo "Another instance is already running.\n";
            return;
        }
        
        $this->logger->info("Starting notification queue processor...");
        echo "Starting notification queue processor...\n";
        
        // 注册关闭处理函数
        register_shutdown_function([$this, 'cleanup']);
        
        try {
            while ($this->running) {
                $processed = $this->queueHandler->processQueue($this->processLimit);
                
                if ($processed > 0) {
                    $message = date('Y-m-d H:i:s') . " - Processed {$processed} notifications";
                    $this->logger->info($message);
                    echo $message . "\n";
                }
                
                // 如果没有处理任何通知，休眠一段时间
                if ($processed === 0) {
                    sleep($this->sleepTime);
                }
                
                // 检查是否收到停止信号
                if (file_exists($this->lockFile . '.stop')) {
                    $this->running = false;
                    unlink($this->lockFile . '.stop');
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Queue processor error: " . $e->getMessage());
            echo "Error: " . $e->getMessage() . "\n";
        }
        
        $this->cleanup();
    }
    
    /**
     * 获取进程锁
     * @return bool
     */
    private function acquireLock(): bool {
        if (file_exists($this->lockFile)) {
            // 检查锁文件是否过期（如果超过10分钟，认为是异常退出）
            if (time() - filemtime($this->lockFile) < 600) {
                return false;
            }
            unlink($this->lockFile);
        }
        
        file_put_contents($this->lockFile, getmypid());
        return true;
    }
    
    /**
     * 清理资源
     */
    public function cleanup(): void {
        $this->running = false;
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
        $this->logger->info("Notification queue processor stopped");
        echo "Notification queue processor stopped\n";
    }
    
    /**
     * 停止处理器
     */
    public function stop(): void {
        file_put_contents($this->lockFile . '.stop', '1');
    }
}

// 如果直接运行此文件
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $processor = new ProcessNotificationQueue();
    
    // 处理命令行参数
    $options = getopt('', ['action:']);
    $action = $options['action'] ?? 'run';
    
    switch ($action) {
        case 'stop':
            $processor->stop();
            break;
        case 'run':
        default:
            $processor->run();
            break;
    }
} 