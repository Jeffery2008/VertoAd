<?php
namespace Utils;

class Logger {
    private static $logFile = __DIR__ . '/../../logs/app.log';
    private static $errorFile = __DIR__ . '/../../logs/error.log';
    
    public static function init() {
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function log($message, $level = 'INFO') {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp][$level] $message" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logMessage, FILE_APPEND);
    }

    public static function error($message, $context = []) {
        self::init();
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $errorMessage = "[$timestamp][ERROR] $message $contextStr" . PHP_EOL;
        
        file_put_contents(self::$errorFile, $errorMessage, FILE_APPEND);
        
        // Also log to PHP error log for critical errors
        error_log($message);
    }

    public static function debug($message, $context = []) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            self::log($message . ' ' . json_encode($context), 'DEBUG');
        }
    }

    public static function audit($userId, $action, $details = '') {
        global $db;
        
        $sql = "INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $action, $details, $ip]);
        } catch (\Exception $e) {
            self::error("Failed to write audit log: " . $e->getMessage());
        }
    }

    public static function getRecentLogs($limit = 100) {
        if (file_exists(self::$logFile)) {
            $logs = array_slice(file(self::$logFile), -$limit);
            return array_map('trim', $logs);
        }
        return [];
    }

    public static function getRecentErrors($limit = 100) {
        if (file_exists(self::$errorFile)) {
            $errors = array_slice(file(self::$errorFile), -$limit);
            return array_map('trim', $errors);
        }
        return [];
    }

    public static function clearLogs() {
        if (file_exists(self::$logFile)) {
            unlink(self::$logFile);
        }
        if (file_exists(self::$errorFile)) {
            unlink(self::$errorFile);
        }
        self::init();
    }
}
