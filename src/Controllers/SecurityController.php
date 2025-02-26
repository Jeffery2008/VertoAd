<?php

namespace App\Controllers;

use App\Services\SecurityService;
use App\Services\ApiKeyService;
use App\Services\AuthService;
use App\Utils\Logger;

/**
 * SecurityController - Controller for managing security features
 */
class SecurityController extends BaseController
{
    /**
     * @var SecurityService $securityService Security service
     */
    private $securityService;
    
    /**
     * @var ApiKeyService $apiKeyService API key service
     */
    private $apiKeyService;
    
    /**
     * @var AuthService $authService Auth service
     */
    private $authService;
    
    /**
     * @var Logger $logger Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->securityService = new SecurityService();
        $this->apiKeyService = new ApiKeyService();
        $this->authService = new AuthService();
        $this->logger = new Logger('SecurityController');
    }
    
    /**
     * Display API keys management page
     */
    public function showApiKeysPage()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->redirect('/login?redirect=' . urlencode('/settings/api-keys'));
            return;
        }
        
        $user = $this->auth->getUser();
        $apiKeys = $this->apiKeyService->getUserKeys($user['id']);
        
        $this->render('user/api-keys', [
            'user' => $user,
            'apiKeys' => $apiKeys
        ]);
    }
    
    /**
     * Create a new API key
     */
    public function createApiKey()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        $data = $this->getRequestData();
        
        // Validate input
        if (!isset($data['name']) || empty(trim($data['name']))) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'API key name is required'
            ], 400);
            return;
        }
        
        // Default permissions if not set
        $permissions = $data['permissions'] ?? ['read'];
        
        // Create the API key
        $keyData = $this->apiKeyService->generateKey($user['id'], $data['name'], $permissions);
        
        if (!$keyData) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to create API key'
            ], 500);
            return;
        }
        
        // Return the key (this is the only time the plain key will be available)
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'API key created successfully',
            'data' => $keyData
        ]);
    }
    
    /**
     * Update an API key
     * 
     * @param int $keyId The API key ID
     */
    public function updateApiKey($keyId)
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        $data = $this->getRequestData();
        
        // Update the key
        $result = $this->apiKeyService->updateKey($keyId, $user['id'], $data);
        
        if (!$result) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to update API key'
            ], 500);
            return;
        }
        
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'API key updated successfully'
        ]);
    }
    
    /**
     * Revoke an API key
     * 
     * @param int $keyId The API key ID
     */
    public function revokeApiKey($keyId)
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        
        // Revoke the key
        $result = $this->apiKeyService->revokeKey($keyId, $user['id']);
        
        if (!$result) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to revoke API key'
            ], 500);
            return;
        }
        
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'API key revoked successfully'
        ]);
    }
    
    /**
     * Delete an API key
     * 
     * @param int $keyId The API key ID
     */
    public function deleteApiKey($keyId)
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        
        // Delete the key
        $result = $this->apiKeyService->deleteKey($keyId, $user['id']);
        
        if (!$result) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to delete API key'
            ], 500);
            return;
        }
        
        $this->jsonResponse([
            'status' => 'success',
            'message' => 'API key deleted successfully'
        ]);
    }
    
    /**
     * Get API key usage statistics
     * 
     * @param int $keyId The API key ID
     */
    public function getApiKeyStats($keyId)
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        $period = $_GET['period'] ?? 'month';
        
        // Get usage stats
        $stats = $this->apiKeyService->getKeyUsageStats($keyId, $user['id'], $period);
        
        $this->jsonResponse([
            'status' => 'success',
            'data' => $stats
        ]);
    }
    
    /**
     * Display the security audit log page
     */
    public function showAuditLogPage()
    {
        // Check if user is authenticated and is admin
        if (!$this->auth->check() || !$this->auth->isAdmin()) {
            $this->redirect('/login?redirect=' . urlencode('/admin/security/audit-log'));
            return;
        }
        
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        $filters = [
            'action' => $_GET['action'] ?? null,
            'user_id' => $_GET['user_id'] ?? null,
            'entity_type' => $_GET['entity_type'] ?? null,
            'date_from' => $_GET['date_from'] ?? null,
            'date_to' => $_GET['date_to'] ?? null
        ];
        
        // Get audit logs with pagination
        $logs = $this->getAuditLogs($page, $perPage, $filters);
        
        $this->render('admin/security/audit-log', [
            'logs' => $logs['logs'],
            'totalPages' => $logs['totalPages'],
            'currentPage' => $page,
            'filters' => $filters
        ]);
    }
    
    /**
     * Get audit logs with pagination
     * 
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param array $filters Filters
     * @return array Logs and pagination info
     */
    private function getAuditLogs($page, $perPage, $filters)
    {
        $query = "
            SELECT sal.*, u.username 
            FROM security_audit_log sal
            LEFT JOIN users u ON sal.user_id = u.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Apply filters
        if (!empty($filters['action'])) {
            $query .= " AND sal.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['user_id'])) {
            $query .= " AND sal.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['entity_type'])) {
            $query .= " AND sal.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $query .= " AND sal.created_at >= ?";
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if (!empty($filters['date_to'])) {
            $query .= " AND sal.created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Count total records
        $countQuery = str_replace("sal.*, u.username", "COUNT(*) as total", $query);
        $stmt = $this->db->prepare($countQuery);
        $stmt->execute($params);
        $totalCount = $stmt->fetch()['total'];
        $totalPages = ceil($totalCount / $perPage);
        
        // Get paginated results
        $query .= " ORDER BY sal.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
        
        // Parse JSON details
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        return [
            'logs' => $logs,
            'totalPages' => $totalPages
        ];
    }
    
    /**
     * Generate a CSRF token for the given page ID
     * 
     * @param string $pageId Page ID (optional)
     */
    public function generateCsrfToken($pageId = 'default')
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        $token = $this->securityService->generateCsrfToken($pageId, $user['id']);
        
        $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'page_id' => $pageId
            ]
        ]);
    }
    
    /**
     * Generate a Proof of Work challenge
     */
    public function generatePowChallenge()
    {
        $difficulty = $_GET['difficulty'] ?? 5;
        $challenge = $this->securityService->generatePowChallenge($difficulty);
        
        $this->jsonResponse([
            'status' => 'success',
            'data' => $challenge
        ]);
    }
    
    /**
     * Verify a Proof of Work solution
     */
    public function verifyPowSolution()
    {
        $data = $this->getRequestData();
        
        if (!isset($data['challenge']) || !isset($data['solution'])) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Challenge and solution are required'
            ], 400);
            return;
        }
        
        $difficulty = $data['difficulty'] ?? 5;
        $isValid = $this->securityService->verifyPowSolution(
            $data['challenge'],
            $data['solution'],
            $difficulty
        );
        
        $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'valid' => $isValid
            ]
        ]);
    }
    
    /**
     * Display the session management page
     */
    public function showSessionsPage()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->redirect('/login?redirect=' . urlencode('/settings/sessions'));
            return;
        }
        
        $user = $this->auth->getUser();
        
        // Get active sessions
        $sessions = $this->getActiveSessions($user['id']);
        
        $this->render('user/sessions', [
            'user' => $user,
            'sessions' => $sessions,
            'currentSession' => $_COOKIE['session_token'] ?? null
        ]);
    }
    
    /**
     * Get active sessions for a user
     * 
     * @param int $userId User ID
     * @return array Sessions
     */
    private function getActiveSessions($userId)
    {
        $query = "
            SELECT id, ip_address, user_agent, created_at, last_activity
            FROM user_sessions
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY last_activity DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId]);
        $sessions = $stmt->fetchAll();
        
        // Enhance with device/browser info
        foreach ($sessions as &$session) {
            $session['device_info'] = $this->parseUserAgent($session['user_agent']);
        }
        
        return $sessions;
    }
    
    /**
     * Parse user agent string to get device and browser info
     * 
     * @param string $userAgent User agent string
     * @return array Device and browser info
     */
    private function parseUserAgent($userAgent)
    {
        $browser = 'Unknown';
        $device = 'Unknown';
        $os = 'Unknown';
        
        // Simple browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false || strpos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        }
        
        // Simple OS detection
        if (strpos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $os = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false || strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        }
        
        // Simple device detection
        if (strpos($userAgent, 'Mobile') !== false) {
            $device = 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false) {
            $device = 'Tablet';
        } else {
            $device = 'Desktop';
        }
        
        return [
            'browser' => $browser,
            'os' => $os,
            'device' => $device
        ];
    }
    
    /**
     * Revoke a user session
     * 
     * @param int $sessionId Session ID
     */
    public function revokeSession($sessionId)
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        
        // Check if session belongs to user
        $query = "
            SELECT * FROM user_sessions
            WHERE id = ? AND user_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$sessionId, $user['id']]);
        $session = $stmt->fetch();
        
        if (!$session) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Session not found or does not belong to you'
            ], 404);
            return;
        }
        
        // Delete the session
        $query = "DELETE FROM user_sessions WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([$sessionId]);
        
        if ($result) {
            // Log the action
            $this->securityService->logSecurityEvent(
                'session_revoked',
                'user_session',
                $sessionId,
                ['ip_address' => $session['ip_address']],
                $user['id']
            );
            
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'Session revoked successfully'
            ]);
        } else {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to revoke session'
            ], 500);
        }
    }
    
    /**
     * Revoke all user sessions except the current one
     */
    public function revokeAllSessions()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
            return;
        }
        
        $user = $this->auth->getUser();
        $currentToken = $_COOKIE['session_token'] ?? null;
        
        if (!$currentToken) {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Current session token not found'
            ], 400);
            return;
        }
        
        // Delete all sessions except current
        $query = "
            DELETE FROM user_sessions
            WHERE user_id = ?
            AND token != ?
        ";
        
        $stmt = $this->db->prepare($query);
        $result = $stmt->execute([$user['id'], hash('sha256', $currentToken)]);
        
        if ($result) {
            // Log the action
            $this->securityService->logSecurityEvent(
                'all_sessions_revoked',
                'user',
                $user['id'],
                [],
                $user['id']
            );
            
            $this->jsonResponse([
                'status' => 'success',
                'message' => 'All other sessions revoked successfully'
            ]);
        } else {
            $this->jsonResponse([
                'status' => 'error',
                'message' => 'Failed to revoke sessions'
            ], 500);
        }
    }
    
    /**
     * Get request data (JSON or form)
     * 
     * @return array Request data
     */
    private function getRequestData()
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        
        if (strpos($contentType, 'application/json') !== false) {
            $json = file_get_contents('php://input');
            return json_decode($json, true) ?? [];
        }
        
        return $_POST;
    }
    
    /**
     * Send JSON response
     * 
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
} 