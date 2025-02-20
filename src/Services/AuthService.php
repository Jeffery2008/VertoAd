<?php
namespace Services;

use Utils\Logger;

class AuthService {
    private static $instance = null;
    private $secretKey;
    private $powDifficulty = 5; // Number of leading zeros required
    private $powTimeout = 300; // 5 minutes timeout
    
    private function __construct() {
        $this->secretKey = defined('JWT_SECRET') ? JWT_SECRET : 'default_secret_key';
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate login challenge with Proof of Work
     */
    public function generateLoginChallenge($username) {
        $nonce = bin2hex(random_bytes(16));
        $timestamp = time();
        $challenge = [
            'username' => $username,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
            'difficulty' => $this->powDifficulty
        ];
        
        $_SESSION['pow_challenge'] = $challenge;
        return $challenge;
    }

    /**
     * Validate proof of work solution
     */
    public function validatePoW($username, $nonce, $solution) {
        $challenge = $_SESSION['pow_challenge'] ?? null;
        if (!$challenge) {
            return false;
        }
        
        // Check challenge timeout
        if (time() - $challenge['timestamp'] > $this->powTimeout) {
            unset($_SESSION['pow_challenge']);
            return false;
        }
        
        // Verify challenge matches
        if ($challenge['username'] !== $username || $challenge['nonce'] !== $nonce) {
            return false;
        }
        
        // Verify solution
        $hash = hash('sha256', $username . $nonce . $solution);
        $leadingZeros = substr($hash, 0, $this->powDifficulty);
        if (str_repeat('0', $this->powDifficulty) !== $leadingZeros) {
            return false;
        }
        
        // Clear used challenge
        unset($_SESSION['pow_challenge']);
        return true;
    }

    /**
     * Generate CSRF token for forms
     */
    public function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate JWT token with unique identifier
     */
    public function generateToken($userId, $userType = 'advertiser', $expiry = null) {
        if ($expiry === null) {
            $expiry = time() + (defined('TOKEN_EXPIRY') ? TOKEN_EXPIRY : 86400);
        }

        $payload = [
            'sub' => $userId,
            'type' => $userType,
            'iat' => time(),
            'exp' => $expiry,
            'jti' => bin2hex(random_bytes(16)) // Unique token ID
        ];

        return $this->encodeToken($payload);
    }

    /**
     * Validate JWT token
     */
    public function validateToken($token) {
        try {
            $payload = $this->decodeToken($token);
            
            if ($payload === null) {
                return false;
            }

            // Check if token has expired
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                Logger::debug('Token expired', ['token' => $token]);
                return false;
            }

            // Check if token has been revoked
            if (isset($payload['jti']) && $this->isTokenRevoked($payload['jti'])) {
                Logger::debug('Token revoked', ['jti' => $payload['jti']]);
                return false;
            }

            return $payload;
        } catch (\Exception $e) {
            Logger::error('Token validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if token has been revoked
     */
    private function isTokenRevoked($jti) {
        global $db;
        try {
            $stmt = $db->prepare("SELECT 1 FROM revoked_tokens WHERE token_id = ? AND revoked_at <= ?");
            $stmt->execute([$jti, time()]);
            return (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            Logger::error('Token revocation check error: ' . $e->getMessage());
            return true; // Fail safe - treat as revoked if check fails
        }
    }

    /**
     * Revoke a specific token
     */
    public function revokeToken($jti) {
        global $db;
        try {
            $stmt = $db->prepare("INSERT INTO revoked_tokens (token_id, revoked_at) VALUES (?, ?)");
            return $stmt->execute([$jti, time()]);
        } catch (\Exception $e) {
            Logger::error('Token revocation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up expired revoked tokens
     */
    public function cleanupRevokedTokens() {
        global $db;
        try {
            // Remove tokens that have been revoked for more than 30 days
            $stmt = $db->prepare("DELETE FROM revoked_tokens WHERE revoked_at < ?");
            return $stmt->execute([time() - (30 * 86400)]);
        } catch (\Exception $e) {
            Logger::error('Revoked tokens cleanup error: ' . $e->getMessage());
            return false;
        }
    }

    private function encodeToken($payload) {
        // Header
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);

        // Encode Header
        $base64UrlHeader = $this->base64UrlEncode($header);

        // Encode Payload
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));

        // Create Signature
        $signature = hash_hmac('sha256', 
            $base64UrlHeader . "." . $base64UrlPayload, 
            $this->secretKey, 
            true
        );
        $base64UrlSignature = $this->base64UrlEncode($signature);

        // Create JWT
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    private function decodeToken($jwt) {
        $tokenParts = explode('.', $jwt);
        
        if (count($tokenParts) != 3) {
            return null;
        }

        $header = $this->base64UrlDecode($tokenParts[0]);
        $payload = $this->base64UrlDecode($tokenParts[1]);
        $signature = $tokenParts[2];

        // Verify signature
        $base64UrlHeader = $tokenParts[0];
        $base64UrlPayload = $tokenParts[1];
        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', 
                $base64UrlHeader . "." . $base64UrlPayload, 
                $this->secretKey, 
                true
            )
        );

        if ($signature !== $expectedSignature) {
            Logger::error('Invalid token signature');
            return null;
        }

        return json_decode($payload, true);
    }

    private function base64UrlEncode($data) {
        $base64 = base64_encode($data);
        $base64Url = strtr($base64, '+/', '-_');
        return rtrim($base64Url, '=');
    }

    private function base64UrlDecode($data) {
        $base64 = strtr($data, '-_', '+/');
        $padded = str_pad($base64, strlen($base64) % 4, '=', STR_PAD_RIGHT);
        return base64_decode($padded);
    }

    public function validateApiKey($apiKey) {
        global $db;
        
        try {
            $stmt = $db->prepare("SELECT advertiser_id, permissions FROM api_keys WHERE api_key = ? AND status = 'active'");
            $stmt->execute([$apiKey]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error('API key validation error: ' . $e->getMessage());
            return false;
        }
    }

    public function hashPassword($password) {
        return password_hash($password . (defined('PASSWORD_SALT') ? PASSWORD_SALT : ''), PASSWORD_BCRYPT);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password . (defined('PASSWORD_SALT') ? PASSWORD_SALT : ''), $hash);
    }
}
