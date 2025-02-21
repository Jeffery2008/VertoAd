<?php

class AuthService {
    private $db;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
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
     */
    public function generateApiKey($userId, $permissions = [], $expiresAt = null) {
        $apiKey = bin2hex(random_bytes(32));
        $hash = password_hash($apiKey, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO api_keys (
                user_id,
                key_hash,
                permissions,
                expires_at,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $hash,
            json_encode($permissions),
            $expiresAt
        ]);

        return $apiKey;
    }

    /**
     * Validate API key
     */
    private function validateApiKey($apiKey) {
        $stmt = $this->db->prepare("
            SELECT ak.*, u.role
            FROM api_keys ak
            JOIN users u ON ak.user_id = u.id
            WHERE ak.key_hash = ? 
            AND (ak.expires_at IS NULL OR ak.expires_at > NOW())
            AND ak.is_active = 1
        ");

        $stmt->execute([$apiKey]);
        $key = $stmt->fetch();

        if (!$key) {
            return null;
        }

        // Record API key usage
        $this->recordApiKeyUsage($key['id']);

        return [
            'user_id' => $key['user_id'],
            'role' => $key['role'],
            'permissions' => json_decode($key['permissions'], true)
        ];
    }

    /**
     * Record API key usage for rate limiting
     */
    private function recordApiKeyUsage($keyId) {
        $stmt = $this->db->prepare("
            INSERT INTO api_key_usage (
                api_key_id,
                endpoint,
                ip_address,
                timestamp
            ) VALUES (?, ?, ?, NOW())
        ");

        $stmt->execute([
            $keyId,
            $_SERVER['REQUEST_URI'],
            $_SERVER['REMOTE_ADDR']
        ]);
    }

    /**
     * Check if rate limit is exceeded
     */
    public function checkRateLimit($keyId, $window = 3600, $limit = 1000) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM api_key_usage
            WHERE api_key_id = ?
            AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");

        $stmt->execute([$keyId, $window]);
        $result = $stmt->fetch();

        return $result['count'] > $limit;
    }

    /**
     * Validate OAuth2 bearer token
     */
    private function validateBearerToken($token) {
        $stmt = $this->db->prepare("
            SELECT at.*, u.role
            FROM access_tokens at
            JOIN users u ON at.user_id = u.id
            WHERE at.token_hash = ?
            AND at.expires_at > NOW()
            AND at.is_active = 1
        ");

        $stmt->execute([hash('sha256', $token)]);
        $accessToken = $stmt->fetch();

        if (!$accessToken) {
            return null;
        }

        return [
            'user_id' => $accessToken['user_id'],
            'role' => $accessToken['role'],
            'scope' => $accessToken['scope']
        ];
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
            throw new Exception("Token request failed: $error");
        }

        return json_decode($response, true);
    }

    /**
     * Process OAuth2 token response
     */
    private function processTokenResponse($response) {
        if (isset($response['error'])) {
            throw new Exception("Token error: " . $response['error_description'] ?? $response['error']);
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

        return $response;
    }

    /**
     * Verify CSRF token
     */
    public function verifyCsrfToken($token) {
        if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
            return false;
        }

        // Generate new token after use
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return true;
    }

    /**
     * Verify Proof of Work
     */
    public function verifyProofOfWork($challenge, $solution, $difficulty = 5) {
        $hash = hash('sha256', $challenge . $solution);
        return substr($hash, 0, $difficulty) === str_repeat('0', $difficulty);
    }
}
