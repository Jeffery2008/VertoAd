<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Logger;


class AuthService {
    private $db;
    private $logger;
    private $securityService;
    private $apiKeyService;

    public function __construct() {
        $this->db = Database::getConnection(); // Updated to getConnection()
        $this->logger = new Logger('AuthService');
        $this->securityService = new SecurityService();
        $this->apiKeyService = new ApiKeyService();
    }

    /**
     * Check if current user is an admin
     * (basic session-based check for now)
     */
    public function isAdmin() {
        return isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0; // Check if admin_id session is set
    }


    /**
     * Validate request authentication
     */
    public function validateRequest() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$authHeader) {
            return null;
        }

        list($type, $credentials) = explode(' ', $authHeader, 2);

        switch (strtolower($type)) {
            case 'bearer':
                return $this->validateBearerToken($credentials);
            case 'apikey':
                return $this->validateApiKey($credentials);
            default:
                return null;
        }
    }

    /**
     * Generate OAuth2 authorization URL
     */
    public function getAuthorizationUrl($clientId, $redirectUri, $state = null) {
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'read write',
            'state' => $state ?? bin2hex(random_bytes(16))
        ];

        return CONFIG['oauth']['authorize_url'] . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeAuthorizationCode($code, $clientId, $clientSecret, $redirectUri) {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri
        ];

        $response = $this->makeTokenRequest($params);
        return $this->processTokenResponse($response);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshAccessToken($refreshToken, $clientId, $clientSecret) {
        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret
        ];

        $response = $this->makeTokenRequest($params);
        return $this->processTokenResponse($response);
    }

    /**
     * Generate API key
     * @deprecated Use ApiKeyService::generateKey() instead
     */
    public function generateApiKey($userId, $permissions = [], $expiresAt = null) {
        return $this->apiKeyService->generateKey($userId, 'Default Key', $permissions);
    }

    /**
     * Validate API key
     * 
     * @param string $apiKey The API key to validate
     * @return array|false User data if valid, or false
     */
    public function validateApiKey($apiKey) {
        // Use the ApiKeyService to validate the key
        $keyData = $this->apiKeyService->validateKey($apiKey);
        
        if (!$keyData) {
            return false;
        }
        
        // Get user information
        $stmt = $this->db->prepare("
            SELECT id, username, role, status 
            FROM users 
            WHERE id = ? AND status = 'active'
        ");
        
        $stmt->execute([$keyData['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        return [
            'user_id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'permissions' => $keyData['permissions']
        ];
    }

    /**
     * Check if user's API key has specific permission
     */
    public function hasApiPermission($apiKey, $permission) {
        return $this->apiKeyService->hasPermission($apiKey, $permission);
    }

    /**
     * Validate OAuth2 bearer token
     */
    public function validateBearerToken($token) {
        $stmt = $this->db->prepare("
            SELECT at.*, u.role, u.status
            FROM access_tokens at
            JOIN users u ON at.user_id = u.id
            WHERE at.token_hash = ?
            AND at.expires_at > NOW()
            AND at.is_active = 1
            AND u.status = 'active'
        ");

        $tokenHash = hash('sha256', $token);
        $stmt->execute([$tokenHash]);
        $accessToken = $stmt->fetch();

        if (!$accessToken) {
            return false;
        }

        // Log token usage
        $this->logTokenUsage($accessToken['id']);

        return [
            'user_id' => $accessToken['user_id'],
            'role' => $accessToken['role'],
            'scope' => $accessToken['scope']
        ];
    }

    /**
     * Log token usage
     * 
     * @param int $tokenId The token ID
     */
    private function logTokenUsage($tokenId) {
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Update last used timestamp
        $stmt = $this->db->prepare("
            UPDATE access_tokens
            SET last_used_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$tokenId]);
        
        // Log the usage for audit purposes
        $this->securityService->logSecurityEvent(
            'token_used',
            'access_token',
            $tokenId,
            [
                'endpoint' => $endpoint,
                'ip_address' => $ipAddress
            ]
        );
    }

    /**
     * Make OAuth2 token request
     */
    private function makeTokenRequest($params) {
        $ch = curl_init(CONFIG['oauth']['token_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Token request failed: $error");
        }

        return json_decode($response, true);
    }

    /**
     * Process OAuth2 token response
     */
    private function processTokenResponse($response) {
        if (isset($response['error'])) {
            throw new \Exception("Token error: " . $response['error_description'] ?? $response['error']);
        }

        // Store access token
        $stmt = $this->db->prepare("
            INSERT INTO access_tokens (
                user_id,
                token_hash,
                scope,
                expires_at,
                refresh_token_hash,
                created_at
            ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), ?, NOW())
        ");

        $stmt->execute([
            $response['user_id'],
            hash('sha256', $response['access_token']),
            $response['scope'],
            $response['expires_in'],
            isset($response['refresh_token']) ? hash('sha256', $response['refresh_token']) : null
        ]);

        // Log token creation
        $tokenId = $this->db->lastInsertId();
        $this->securityService->logSecurityEvent(
            'token_created',
            'access_token',
            $tokenId,
            [
                'user_id' => $response['user_id'],
                'scope' => $response['scope'],
                'expires_in' => $response['expires_in']
            ]
        );

        return $response;
    }

    /**
     * Verify CSRF token
     * @deprecated Use SecurityService::verifyCsrfToken() instead
     */
    public function verifyCsrfToken($token, $pageId = 'default') {
        return $this->securityService->verifyCsrfToken($token, $pageId);
    }

    /**
     * Generate CSRF token
     * @deprecated Use SecurityService::generateCsrfToken() instead
     */
    public function generateCsrfToken($pageId = 'default') {
        $userId = $_SESSION['user_id'] ?? null;
        return $this->securityService->generateCsrfToken($pageId, $userId);
    }

    /**
     * Verify Proof of Work
     * @deprecated Use SecurityService::verifyPowSolution() instead
     */
    public function verifyProofOfWork($challenge, $solution, $difficulty = 5) {
        return $this->securityService->verifyPowSolution($challenge, $solution, $difficulty);
    }

    /**
     * Generate Proof of Work challenge
     */
    public function generatePowChallenge($difficulty = 5) {
        return $this->securityService->generatePowChallenge($difficulty);
    }

    /**
     * Authenticate a user using username and password
     * 
     * @param string $username The username
     * @param string $password The password
     * @return array|false User data if successful, or false
     */
    public function authenticate($username, $password) {
        // Check if account is locked
        $lockStatus = $this->securityService->isAccountLocked($username);
        if ($lockStatus !== false) {
            $this->logger->warning('Login attempt on locked account', [
                'username' => $username,
                'lock_time_remaining' => $lockStatus
            ]);
            return false;
        }
        
        // Get user by username
        $stmt = $this->db->prepare("
            SELECT id, username, password, role, status
            FROM users
            WHERE username = ?
        ");
        
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || $user['status'] !== 'active') {
            // Log failed login attempt
            $this->securityService->trackAuthAttempt($username, false);
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Log failed login attempt
            $this->securityService->trackAuthAttempt($username, false);
            return false;
        }
        
        // Log successful authentication
        $this->securityService->trackAuthAttempt($username, true);
        
        // If password needs rehashing (due to algorithm or cost changes)
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->updatePasswordHash($user['id'], $password);
        }
        
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role']
        ];
    }
    
    /**
     * Update a user's password hash
     * 
     * @param int $userId The user ID
     * @param string $password The plain text password
     * @return bool Whether the update was successful
     */
    private function updatePasswordHash($userId, $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            UPDATE users
            SET password = ?
            WHERE id = ?
        ");
        
        return $stmt->execute([$hash, $userId]);
    }
    
    /**
     * Create a session for an authenticated user
     * 
     * @param array $user The user data
     * @return string The session token
     */
    public function createSession($user) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = time() + (86400 * 14); // 14 days
        
        // Store session in database
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions (
                user_id,
                token,
                ip_address,
                user_agent,
                expires_at,
                created_at
            ) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
        ");
        
        $stmt->execute([
            $user['id'],
            hash('sha256', $token),
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        
        // Set session token cookie
        setcookie('session_token', $token, [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        return $token;
    }
    
    /**
     * Validate a session token
     * 
     * @param string $token The session token
     * @return array|false User data if valid, or false
     */
    public function validateSession($token) {
        if (!$token) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT s.*, u.username, u.role, u.status
            FROM user_sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.token = ?
            AND s.expires_at > NOW()
            AND u.status = 'active'
        ");
        
        $stmt->execute([hash('sha256', $token)]);
        $session = $stmt->fetch();
        
        if (!$session) {
            return false;
        }
        
        // Optionally validate IP and user agent for added security
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $currentAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if ($session['ip_address'] !== $currentIp || $session['user_agent'] !== $currentAgent) {
            $this->logger->warning('Session validation: IP or user agent mismatch', [
                'session_id' => $session['id'],
                'user_id' => $session['user_id'],
                'stored_ip' => $session['ip_address'],
                'current_ip' => $currentIp
            ]);
            
            // For suspicious mismatch, we might invalidate the session
            // But here we'll just log and continue
        }
        
        // Update last activity
        $this->updateSessionActivity($session['id']);
        
        return [
            'id' => $session['user_id'],
            'username' => $session['username'],
            'role' => $session['role']
        ];
    }
    
    /**
     * Update session last activity timestamp
     * 
     * @param int $sessionId The session ID
     */
    private function updateSessionActivity($sessionId) {
        $stmt = $this->db->prepare("
            UPDATE user_sessions
            SET last_activity = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$sessionId]);
    }
    
    /**
     * Destroy a user session
     * 
     * @param string $token The session token
     * @return bool Whether the logout was successful
     */
    public function destroySession($token = null) {
        // If no token provided, try to get it from cookie
        if (!$token) {
            $token = $_COOKIE['session_token'] ?? null;
        }
        
        if ($token) {
            // Remove from database
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions
                WHERE token = ?
            ");
            
            $stmt->execute([hash('sha256', $token)]);
        }
        
        // Clear session variables
        $_SESSION = [];
        
        // Clear the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        
        // Destroy the session
        session_destroy();
        
        // Clear the token cookie
        setcookie('session_token', '', time() - 42000, '/', '', isset($_SERVER['HTTPS']), true);
        
        return true;
    }
    
    /**
     * Create a new OAuth client for a user
     * 
     * @param int $userId The user ID
     * @param string $name The client name
     * @param string $redirectUri The redirect URI
     * @return array The client details
     */
    public function createOAuthClient($userId, $name, $redirectUri) {
        $clientId = bin2hex(random_bytes(16));
        $clientSecret = bin2hex(random_bytes(32));
        $clientSecretHash = password_hash($clientSecret, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO oauth_clients (
                client_id,
                client_secret_hash,
                user_id,
                name,
                redirect_uri,
                is_active,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $clientId,
            $clientSecretHash,
            $userId,
            $name,
            $redirectUri
        ]);
        
        // Log the client creation
        $clientDbId = $this->db->lastInsertId();
        $this->securityService->logSecurityEvent(
            'oauth_client_created',
            'oauth_client',
            $clientDbId,
            [
                'user_id' => $userId,
                'name' => $name,
                'client_id' => $clientId
            ],
            $userId
        );
        
        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret, // Only shown once
            'name' => $name,
            'redirect_uri' => $redirectUri
        ];
    }
    
    /**
     * Revoke all active sessions and tokens for a user
     * 
     * @param int $userId The user ID
     * @return bool Whether the revocation was successful
     */
    public function revokeAllUserSessions($userId) {
        // Revoke sessions
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        
        // Revoke tokens
        $stmt = $this->db->prepare("
            UPDATE access_tokens
            SET is_active = 0
            WHERE user_id = ?
        ");
        
        $stmt->execute([$userId]);
        
        // Log the revocation
        $this->securityService->logSecurityEvent(
            'user_sessions_revoked',
            'user',
            $userId,
            [],
            $userId
        );
        
        return true;
    }
}
