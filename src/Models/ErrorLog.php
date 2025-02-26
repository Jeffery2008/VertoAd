<?php

namespace VertoAD\Core\Models;

/**
 * Model for error logging and management
 */
class ErrorLog extends BaseModel
{
    /**
     * Table name
     * 
     * @var string
     */
    protected $table = 'error_logs';
    
    /**
     * Allowed severity levels
     */
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';
    
    /**
     * Error status values
     */
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_IGNORED = 'ignored';
    
    /**
     * Create a new error log entry
     * 
     * @param string $errorType Error type (e.g., PHP, JavaScript, Application)
     * @param string $errorMessage Error message
     * @param array $options Additional options for the error log
     * @return int|bool ID of the created log or false on failure
     */
    public function createLog($errorType, $errorMessage, array $options = [])
    {
        $data = array_merge([
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'error_code' => null,
            'error_file' => null,
            'error_line' => null,
            'stack_trace' => null,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null,
            'request_params' => $this->getRequestParams(),
            'user_id' => $this->getCurrentUserId(),
            'user_type' => $this->getCurrentUserType(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null,
            'severity' => self::SEVERITY_MEDIUM,
            'status' => self::STATUS_NEW,
            'created_at' => date('Y-m-d H:i:s')
        ], $options);
        
        try {
            $query = "INSERT INTO {$this->table} 
                     (error_type, error_code, error_message, error_file, error_line, stack_trace, 
                      request_uri, request_method, request_params, user_id, user_type, ip_address, 
                      user_agent, referer, severity, status, created_at) 
                     VALUES 
                     (:error_type, :error_code, :error_message, :error_file, :error_line, :stack_trace, 
                      :request_uri, :request_method, :request_params, :user_id, :user_type, :ip_address, 
                      :user_agent, :referer, :severity, :status, :created_at)";
            
            $id = $this->db->execute($query, $data);
            
            // Check if we should send notifications for this error
            if ($id) {
                $this->processNotifications($id, $data);
                $this->categorizeError($id, $data);
            }
            
            return $id;
        } catch (\Exception $e) {
            // Log to file as a fallback since we can't log to database
            $this->logToFile("[ERROR LOG FAILED] " . $e->getMessage() . ": " . $errorMessage);
            return false;
        }
    }
    
    /**
     * Get error logs with filtering options
     * 
     * @param array $filters Filter criteria
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array Error logs
     */
    public function getLogs(array $filters = [], $limit = 50, $offset = 0)
    {
        try {
            $whereClause = $this->buildWhereClause($filters);
            $params = $filters;
            
            // Add pagination
            $params['limit'] = $limit;
            $params['offset'] = $offset;
            
            $query = "SELECT * FROM {$this->table} 
                     {$whereClause} 
                     ORDER BY created_at DESC 
                     LIMIT :limit OFFSET :offset";
            
            return $this->db->fetchAll($query, $params);
        } catch (\Exception $e) {
            $this->logToFile("[GET LOGS FAILED] " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Count error logs with filtering options
     * 
     * @param array $filters Filter criteria
     * @return int Number of matching logs
     */
    public function countLogs(array $filters = [])
    {
        try {
            $whereClause = $this->buildWhereClause($filters);
            
            $query = "SELECT COUNT(*) as count FROM {$this->table} {$whereClause}";
            
            $result = $this->db->fetchOne($query, $filters);
            return isset($result['count']) ? (int)$result['count'] : 0;
        } catch (\Exception $e) {
            $this->logToFile("[COUNT LOGS FAILED] " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get a single error log by ID
     * 
     * @param int $id Error log ID
     * @return array|null Error log or null if not found
     */
    public function getLogById($id)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE id = :id";
            return $this->db->fetchOne($query, ['id' => $id]);
        } catch (\Exception $e) {
            $this->logToFile("[GET LOG BY ID FAILED] " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update an error log
     * 
     * @param int $id Error log ID
     * @param array $data Data to update
     * @return bool Success
     */
    public function updateLog($id, array $data)
    {
        try {
            // Build SET clause
            $setClauses = [];
            $params = ['id' => $id];
            
            foreach ($data as $key => $value) {
                $setClauses[] = "`{$key}` = :{$key}";
                $params[$key] = $value;
            }
            
            $setClause = implode(', ', $setClauses);
            
            $query = "UPDATE {$this->table} SET {$setClause} WHERE id = :id";
            
            return $this->db->execute($query, $params) !== false;
        } catch (\Exception $e) {
            $this->logToFile("[UPDATE LOG FAILED] " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mark an error log as resolved
     * 
     * @param int $id Error log ID
     * @param int $userId User ID who resolved the error
     * @param string $notes Resolution notes
     * @return bool Success
     */
    public function resolveLog($id, $userId, $notes = null)
    {
        $data = [
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $userId,
            'resolved_at' => date('Y-m-d H:i:s')
        ];
        
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        
        return $this->updateLog($id, $data);
    }
    
    /**
     * Mark an error log as ignored
     * 
     * @param int $id Error log ID
     * @param int $userId User ID who ignored the error
     * @param string $notes Ignore reason
     * @return bool Success
     */
    public function ignoreLog($id, $userId, $notes = null)
    {
        $data = [
            'status' => self::STATUS_IGNORED,
            'resolved_by' => $userId,
            'resolved_at' => date('Y-m-d H:i:s')
        ];
        
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        
        return $this->updateLog($id, $data);
    }
    
    /**
     * Get error logs grouped by type with counts
     * 
     * @param int $days Number of days to look back
     * @return array Error types with counts
     */
    public function getErrorTypeStats($days = 30)
    {
        try {
            $query = "SELECT error_type, COUNT(*) as count 
                     FROM {$this->table} 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) 
                     GROUP BY error_type 
                     ORDER BY count DESC";
            
            return $this->db->fetchAll($query, ['days' => $days]);
        } catch (\Exception $e) {
            $this->logToFile("[GET ERROR TYPE STATS FAILED] " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get error logs grouped by day
     * 
     * @param int $days Number of days to look back
     * @return array Daily error counts
     */
    public function getDailyErrorStats($days = 30)
    {
        try {
            $query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                     FROM {$this->table} 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) 
                     GROUP BY DATE(created_at) 
                     ORDER BY date ASC";
            
            return $this->db->fetchAll($query, ['days' => $days]);
        } catch (\Exception $e) {
            $this->logToFile("[GET DAILY ERROR STATS FAILED] " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get error logs grouped by severity
     * 
     * @param int $days Number of days to look back
     * @return array Severity counts
     */
    public function getSeverityStats($days = 30)
    {
        try {
            $query = "SELECT severity, COUNT(*) as count 
                     FROM {$this->table} 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY) 
                     GROUP BY severity 
                     ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')";
            
            return $this->db->fetchAll($query, ['days' => $days]);
        } catch (\Exception $e) {
            $this->logToFile("[GET SEVERITY STATS FAILED] " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Process notifications for a new error log
     * 
     * @param int $errorLogId Error log ID
     * @param array $errorData Error log data
     */
    private function processNotifications($errorLogId, array $errorData)
    {
        try {
            // Get subscriptions that match this error severity
            $query = "SELECT * FROM error_notification_subscriptions 
                     WHERE is_active = 1 
                     AND (min_severity = :severity 
                          OR (min_severity = 'critical' AND :severity = 'critical')
                          OR (min_severity = 'high' AND :severity IN ('high', 'critical'))
                          OR (min_severity = 'medium' AND :severity IN ('medium', 'high', 'critical')))";
            
            $subscriptions = $this->db->fetchAll($query, ['severity' => $errorData['severity']]);
            
            foreach ($subscriptions as $subscription) {
                // Check if error type matches subscription (if specified)
                if (!empty($subscription['error_types'])) {
                    $types = explode(',', $subscription['error_types']);
                    if (!in_array($errorData['error_type'], $types)) {
                        continue;
                    }
                }
                
                // Create notification
                $notificationContent = $this->formatNotificationContent($errorData);
                
                $notificationData = [
                    'error_log_id' => $errorLogId,
                    'subscription_id' => $subscription['id'],
                    'notification_method' => $subscription['notification_method'],
                    'notification_target' => $subscription['notification_target'],
                    'notification_content' => $notificationContent,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $query = "INSERT INTO error_notifications 
                         (error_log_id, subscription_id, notification_method, notification_target, 
                          notification_content, status, created_at) 
                         VALUES 
                         (:error_log_id, :subscription_id, :notification_method, :notification_target, 
                          :notification_content, :status, :created_at)";
                
                $this->db->execute($query, $notificationData);
            }
        } catch (\Exception $e) {
            $this->logToFile("[PROCESS NOTIFICATIONS FAILED] " . $e->getMessage());
        }
    }
    
    /**
     * Categorize an error based on patterns
     * 
     * @param int $errorLogId Error log ID
     * @param array $errorData Error log data
     */
    private function categorizeError($errorLogId, array $errorData)
    {
        try {
            // Get all categories
            $query = "SELECT * FROM error_categories";
            $categories = $this->db->fetchAll($query);
            
            foreach ($categories as $category) {
                if (empty($category['auto_assign_patterns'])) {
                    continue;
                }
                
                $patterns = json_decode($category['auto_assign_patterns'], true);
                if (!$patterns) {
                    continue;
                }
                
                // Check if error matches pattern
                $matches = false;
                foreach ($patterns as $field => $pattern) {
                    if (isset($errorData[$field])) {
                        if (is_array($pattern)) {
                            // Match against array of keywords
                            foreach ($pattern as $keyword) {
                                if (stripos($errorData[$field], $keyword) !== false) {
                                    $matches = true;
                                    break 2; // Break both loops
                                }
                            }
                        } else {
                            // Direct match
                            if ($errorData[$field] == $pattern) {
                                $matches = true;
                                break;
                            }
                        }
                    }
                }
                
                if ($matches) {
                    // Update error with category
                    $this->updateLog($errorLogId, [
                        'category_id' => $category['id'],
                        'severity' => $category['default_severity']
                    ]);
                    break;
                }
            }
        } catch (\Exception $e) {
            $this->logToFile("[CATEGORIZE ERROR FAILED] " . $e->getMessage());
        }
    }
    
    /**
     * Format notification content
     * 
     * @param array $errorData Error log data
     * @return string Formatted notification content
     */
    private function formatNotificationContent(array $errorData)
    {
        $content = "Error: {$errorData['error_message']}\n";
        $content .= "Type: {$errorData['error_type']}\n";
        $content .= "Severity: {$errorData['severity']}\n";
        
        if (!empty($errorData['error_file'])) {
            $content .= "File: {$errorData['error_file']}";
            if (!empty($errorData['error_line'])) {
                $content .= " (line {$errorData['error_line']})";
            }
            $content .= "\n";
        }
        
        if (!empty($errorData['request_uri'])) {
            $content .= "URL: {$errorData['request_uri']}\n";
        }
        
        return $content;
    }
    
    /**
     * Build SQL WHERE clause from filters
     * 
     * @param array $filters Filter criteria
     * @return string WHERE clause
     */
    private function buildWhereClause(array &$filters)
    {
        if (empty($filters)) {
            return '';
        }
        
        $conditions = [];
        
        // Handle date range
        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $conditions[] = "created_at BETWEEN :date_from AND :date_to";
        } elseif (isset($filters['date_from'])) {
            $conditions[] = "created_at >= :date_from";
        } elseif (isset($filters['date_to'])) {
            $conditions[] = "created_at <= :date_to";
        }
        
        // Handle simple equality filters
        $equalityFields = ['error_type', 'severity', 'status', 'user_id'];
        foreach ($equalityFields as $field) {
            if (isset($filters[$field])) {
                $conditions[] = "`{$field}` = :{$field}";
            }
        }
        
        // Handle search term
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchFields = ['error_message', 'error_file', 'request_uri', 'user_agent'];
            $searchConditions = [];
            
            foreach ($searchFields as $index => $field) {
                $paramName = "search_{$index}";
                $searchConditions[] = "`{$field}` LIKE :{$paramName}";
                $filters[$paramName] = "%{$filters['search']}%";
            }
            
            if (!empty($searchConditions)) {
                $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
            
            // Remove the original search parameter
            unset($filters['search']);
        }
        
        if (empty($conditions)) {
            return '';
        }
        
        return 'WHERE ' . implode(' AND ', $conditions);
    }
    
    /**
     * Get request parameters as JSON
     * 
     * @return string|null JSON-encoded request parameters or null
     */
    private function getRequestParams()
    {
        $params = [];
        
        // GET parameters
        if (!empty($_GET)) {
            $params['GET'] = $_GET;
        }
        
        // POST parameters (excluding file uploads and sensitive data)
        if (!empty($_POST)) {
            $filteredPost = $_POST;
            
            // Remove sensitive fields
            $sensitiveFields = ['password', 'password_confirm', 'credit_card', 'token'];
            foreach ($sensitiveFields as $field) {
                if (isset($filteredPost[$field])) {
                    $filteredPost[$field] = '******';
                }
            }
            
            $params['POST'] = $filteredPost;
        }
        
        // Headers (excluding cookies and authorization)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $filteredHeaders = [];
            
            foreach ($headers as $name => $value) {
                $lowerName = strtolower($name);
                if ($lowerName !== 'cookie' && $lowerName !== 'authorization') {
                    $filteredHeaders[$name] = $value;
                }
            }
            
            if (!empty($filteredHeaders)) {
                $params['headers'] = $filteredHeaders;
            }
        }
        
        return !empty($params) ? json_encode($params) : null;
    }
    
    /**
     * Get the current user ID
     * 
     * @return int|null User ID or null if not logged in
     */
    private function getCurrentUserId()
    {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    /**
     * Get the current user type
     * 
     * @return string|null User type or null if not logged in
     */
    private function getCurrentUserType()
    {
        return isset($_SESSION['role']) ? $_SESSION['role'] : null;
    }
    
    /**
     * Get the client IP address
     * 
     * @return string|null IP address or null
     */
    private function getClientIp()
    {
        $keys = [
            'HTTP_CLIENT_IP', 
            'HTTP_X_FORWARDED_FOR', 
            'HTTP_X_FORWARDED', 
            'HTTP_X_CLUSTER_CLIENT_IP', 
            'HTTP_FORWARDED_FOR', 
            'HTTP_FORWARDED', 
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (isset($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Log to file when database logging fails
     * 
     * @param string $message Message to log
     */
    private function logToFile($message)
    {
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error_logger_fallback.log';
        $timestamp = date('Y-m-d H:i:s');
        
        file_put_contents(
            $logFile, 
            "[{$timestamp}] {$message}" . PHP_EOL, 
            FILE_APPEND
        );
    }
} 