<?php

namespace App\Services;

use App\Utils\Database;
use App\Utils\Logger;

/**
 * SecurityService - Centralized service for security features
 */
class SecurityService
{
    /**
     * @var Database $db Database connection
     */
    private $db;
    
    /**
     * @var Logger $logger Logger instance
     */
    private $logger;
    
    /**
     * @var int $csrfTokenExpiration CSRF token expiration in seconds
     */
    private $csrfTokenExpiration = 3600; // 1 hour
    
    /**
     * @var array $rateLimits Default rate limits for different types
     */
    private $rateLimits = [
        'ip' => ['window' => 60, 'limit' => 60], // 60 requests per minute by IP
        'api_key' => ['window' => 60, 'limit' => 120], // 120 requests per minute by API key
        'user' => ['window' => 60, 'limit' => 100] // 100 requests per minute by user
    ];
    
    /**
     * @var string $encryptionMethod Default encryption method
     */
    private $encryptionMethod = 'aes-256-cbc';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->logger = new Logger('SecurityService');
    }
    
    /**
     * Generate a CSRF token for a specific page
     * 
     * @param string $pageId Identifier for the page requiring CSRF protection
     * @param int|null $userId Current user ID or null for guests
     * @return string The generated CSRF token
     */
    public function generateCsrfToken($pageId, $userId = null)
    {
        // Create a unique token
        $token = bin2hex(random_bytes(32));
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $expiresAt = date('Y-m-d H:i:s', time() + $this->csrfTokenExpiration);
        
        // First, try to update an existing token for this session and page
        $stmt = $this->db->prepare("
            UPDATE csrf_tokens 
            SET token = ?, 
                ip_address = ?, 
                user_agent = ?, 
                user_id = ?, 
                expires_at = ?,
                created_at = NOW()
            WHERE session_id = ? AND page_id = ?
        ");
        
        $result = $stmt->execute([
            $token,
            $ipAddress,
            $userAgent,
            $userId,
            $expiresAt,
            $sessionId,
            $pageId
        ]);
        
        // If no existing token was updated, insert a new one
        if ($stmt->rowCount() === 0) {
            $stmt = $this->db->prepare("
                INSERT INTO csrf_tokens (
                    token, 
                    session_id, 
                    page_id, 
                    user_id, 
                    ip_address, 
                    user_agent, 
                    expires_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $token,
                $sessionId,
                $pageId,
                $userId,
                $ipAddress,
                $userAgent,
                $expiresAt
            ]);
        }
        
        // Clean up expired tokens occasionally (1 in 10 chance)
        if (mt_rand(1, 10) === 1) {
            $this->cleanExpiredCsrfTokens();
        }
        
        return $token;
    }
    
    /**
     * Verify a CSRF token for a specific page
     * 
     * @param string $token The token to verify
     * @param string $pageId The page identifier
     * @return bool Whether the token is valid
     */
    public function verifyCsrfToken($token, $pageId)
    {
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt = $this->db->prepare("
            SELECT * FROM csrf_tokens 
            WHERE token = ? 
            AND session_id = ? 
            AND page_id = ? 
            AND expires_at > NOW()
        ");
        
        $stmt->execute([$token, $sessionId, $pageId]);
        $tokenRow = $stmt->fetch();
        
        if (!$tokenRow) {
            $this->logger->warning("Invalid CSRF token attempt", [
                'token' => $token,
                'page_id' => $pageId,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress
            ]);
            return false;
        }
        
        // Optionally validate IP address for added security
        // This is a bit strict and might cause issues for users with dynamic IPs
        // or going through proxies, so we log but don't reject
        if ($tokenRow['ip_address'] !== $ipAddress) {
            $this->logger->warning("CSRF token IP mismatch", [
                'token_ip' => $tokenRow['ip_address'],
                'request_ip' => $ipAddress,
                'page_id' => $pageId
            ]);
        }
        
        return true;
    }
    
    /**
     * Clean up expired CSRF tokens
     */
    private function cleanExpiredCsrfTokens()
    {
        $stmt = $this->db->prepare("DELETE FROM csrf_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $this->logger->info("Cleaned up {$stmt->rowCount()} expired CSRF tokens");
        }
    }
    
    /**
     * Generate a Proof of Work challenge
     * 
     * @param int $difficulty The difficulty level (default: 5)
     * @return array The challenge data
     */
    public function generatePowChallenge($difficulty = 5)
    {
        $challenge = bin2hex(random_bytes(16));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes expiration
        
        $stmt = $this->db->prepare("
            INSERT INTO pow_challenges (
                challenge, 
                ip_address, 
                session_id, 
                difficulty, 
                expires_at
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $challenge,
            $ipAddress,
            $sessionId,
            $difficulty,
            $expiresAt
        ]);
        
        return [
            'challenge' => $challenge,
            'difficulty' => $difficulty
        ];
    }
    
    /**
     * Verify a Proof of Work solution
     * 
     * @param string $challenge The challenge string
     * @param string $solution The solution (nonce)
     * @param int $difficulty The expected difficulty level
     * @return bool Whether the solution is valid
     */
    public function verifyPowSolution($challenge, $solution, $difficulty = 5)
    {
        // First, check if the challenge exists and is not expired
        $stmt = $this->db->prepare("
            SELECT * FROM pow_challenges 
            WHERE challenge = ? 
            AND is_solved = 0 
            AND expires_at > NOW()
        ");
        
        $stmt->execute([$challenge]);
        $challengeRow = $stmt->fetch();
        
        if (!$challengeRow) {
            return false;
        }
        
        // Verify the solution
        $hash = hash('sha256', $challenge . $solution);
        $isValid = substr($hash, 0, $difficulty) === str_repeat('0', $difficulty);
        
        if ($isValid) {
            // Mark the challenge as solved
            $stmt = $this->db->prepare("
                UPDATE pow_challenges 
                SET is_solved = 1, 
                    solved_at = NOW() 
                WHERE id = ?
            ");
            
            $stmt->execute([$challengeRow['id']]);
        }
        
        return $isValid;
    }
    
    /**
     * Apply rate limiting to a request
     * 
     * @param string $identifier The identifier (IP, API key, or user ID)
     * @param string $type The type of identifier ('ip', 'api_key', or 'user')
     * @param string $endpoint The API endpoint or route
     * @return array Status of the rate limit check
     */
    public function applyRateLimit($identifier, $type = 'ip', $endpoint = '*')
    {
        // Get the rate limit settings for this type
        $window = $this->rateLimits[$type]['window'] ?? 60;
        $limit = $this->rateLimits[$type]['limit'] ?? 60;
        
        // Check if endpoint-specific limits exist in config
        // This would be implemented by checking a configuration table
        
        // First, try to update an existing rate limit record
        $stmt = $this->db->prepare("
            SELECT * FROM rate_limits
            WHERE identifier = ? 
            AND type = ? 
            AND endpoint = ?
        ");
        
        $stmt->execute([$identifier, $type, $endpoint]);
        $rateLimit = $stmt->fetch();
        
        if ($rateLimit) {
            // Record exists, check if it's within the current window
            $firstRequestTime = strtotime($rateLimit['first_request_at']);
            $currentTime = time();
            
            if (($currentTime - $firstRequestTime) > $window) {
                // Window has expired, reset the count
                $stmt = $this->db->prepare("
                    UPDATE rate_limits
                    SET request_count = 1,
                        first_request_at = NOW(),
                        last_request_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$rateLimit['id']]);
                
                return [
                    'limited' => false,
                    'remaining' => $limit - 1,
                    'reset' => $currentTime + $window
                ];
            } else {
                // Still within window, increment count
                $newCount = $rateLimit['request_count'] + 1;
                
                $stmt = $this->db->prepare("
                    UPDATE rate_limits
                    SET request_count = ?,
                        last_request_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$newCount, $rateLimit['id']]);
                
                $limited = $newCount > $limit;
                
                if ($limited) {
                    $this->logger->warning("Rate limit exceeded", [
                        'identifier' => $identifier,
                        'type' => $type,
                        'endpoint' => $endpoint,
                        'count' => $newCount,
                        'limit' => $limit
                    ]);
                }
                
                return [
                    'limited' => $limited,
                    'remaining' => max(0, $limit - $newCount),
                    'reset' => $firstRequestTime + $window
                ];
            }
        } else {
            // No record exists, create a new one
            $stmt = $this->db->prepare("
                INSERT INTO rate_limits (
                    identifier,
                    type,
                    endpoint,
                    request_count,
                    first_request_at,
                    last_request_at
                ) VALUES (?, ?, ?, 1, NOW(), NOW())
            ");
            
            $stmt->execute([$identifier, $type, $endpoint]);
            
            return [
                'limited' => false,
                'remaining' => $limit - 1,
                'reset' => time() + $window
            ];
        }
    }
    
    /**
     * Encrypt sensitive data
     * 
     * @param string $data The data to encrypt
     * @param string $keyIdentifier The key identifier (optional)
     * @return string The encrypted data
     */
    public function encryptData($data, $keyIdentifier = 'default')
    {
        // Get or create the encryption key
        $key = $this->getEncryptionKey($keyIdentifier);
        
        // Generate a random IV
        $ivSize = openssl_cipher_iv_length($this->encryptionMethod);
        $iv = openssl_random_pseudo_bytes($ivSize);
        
        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            $this->encryptionMethod,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            throw new \Exception('Encryption failed: ' . openssl_error_string());
        }
        
        // Combine IV and encrypted data
        $combined = $iv . $encrypted;
        
        // Return base64 encoded string
        return base64_encode($combined);
    }
    
    /**
     * Decrypt sensitive data
     * 
     * @param string $encryptedData The encrypted data
     * @param string $keyIdentifier The key identifier
     * @return string The decrypted data
     */
    public function decryptData($encryptedData, $keyIdentifier = 'default')
    {
        // Get the encryption key
        $key = $this->getEncryptionKey($keyIdentifier);
        
        // Decode the base64 string
        $combined = base64_decode($encryptedData);
        
        // Extract IV and encrypted data
        $ivSize = openssl_cipher_iv_length($this->encryptionMethod);
        $iv = substr($combined, 0, $ivSize);
        $encrypted = substr($combined, $ivSize);
        
        // Decrypt the data
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->encryptionMethod,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed: ' . openssl_error_string());
        }
        
        return $decrypted;
    }
    
    /**
     * Get or create an encryption key
     * 
     * @param string $keyIdentifier The key identifier
     * @return string The encryption key
     */
    private function getEncryptionKey($keyIdentifier)
    {
        // Try to get the existing key from the database
        $stmt = $this->db->prepare("
            SELECT * FROM encryption_keys
            WHERE key_identifier = ?
            AND is_active = 1
        ");
        
        $stmt->execute([$keyIdentifier]);
        $keyRow = $stmt->fetch();
        
        if ($keyRow) {
            // Key exists, decrypt it
            $masterKey = $this->getMasterKey();
            $encryptedKey = base64_decode($keyRow['encrypted_key']);
            return $this->decryptWithMasterKey($encryptedKey, $masterKey);
        } else {
            // Create a new key
            $newKey = bin2hex(random_bytes(32));
            $masterKey = $this->getMasterKey();
            $encryptedKey = $this->encryptWithMasterKey($newKey, $masterKey);
            
            $stmt = $this->db->prepare("
                INSERT INTO encryption_keys (
                    key_identifier,
                    encrypted_key,
                    algorithm,
                    is_active
                ) VALUES (?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $keyIdentifier,
                base64_encode($encryptedKey),
                $this->encryptionMethod
            ]);
            
            return $newKey;
        }
    }
    
    /**
     * Get the master encryption key from environment or config
     * 
     * @return string The master key
     */
    private function getMasterKey()
    {
        // In production, this should be in a secure environment variable
        // For this example, we'll use a config value
        $masterKey = $_ENV['MASTER_ENCRYPTION_KEY'] ?? CONFIG['security']['master_key'] ?? null;
        
        if (!$masterKey) {
            // If no master key is set, generate one and recommend storing it
            $masterKey = bin2hex(random_bytes(32));
            $this->logger->warning('No master encryption key found. Generated a temporary one, but this should be stored securely.');
        }
        
        return $masterKey;
    }
    
    /**
     * Encrypt data with the master key
     * 
     * @param string $data The data to encrypt
     * @param string $masterKey The master key
     * @return string The encrypted data
     */
    private function encryptWithMasterKey($data, $masterKey)
    {
        $ivSize = openssl_cipher_iv_length($this->encryptionMethod);
        $iv = openssl_random_pseudo_bytes($ivSize);
        
        $encrypted = openssl_encrypt(
            $data,
            $this->encryptionMethod,
            $masterKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $iv . $encrypted;
    }
    
    /**
     * Decrypt data with the master key
     * 
     * @param string $encryptedData The encrypted data
     * @param string $masterKey The master key
     * @return string The decrypted data
     */
    private function decryptWithMasterKey($encryptedData, $masterKey)
    {
        $ivSize = openssl_cipher_iv_length($this->encryptionMethod);
        $iv = substr($encryptedData, 0, $ivSize);
        $encrypted = substr($encryptedData, $ivSize);
        
        return openssl_decrypt(
            $encrypted,
            $this->encryptionMethod,
            $masterKey,
            OPENSSL_RAW_DATA,
            $iv
        );
    }
    
    /**
     * Log a security-related event for auditing
     * 
     * @param string $action The action being performed
     * @param string $entityType The type of entity being acted upon (optional)
     * @param int $entityId The ID of the entity (optional)
     * @param array $details Additional details about the action (optional)
     * @param int $userId The ID of the user performing the action (optional)
     * @return int|false The ID of the new audit log entry or false on failure
     */
    public function logSecurityEvent($action, $entityType = null, $entityId = null, $details = [], $userId = null)
    {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->db->prepare("
            INSERT INTO security_audit_log (
                user_id,
                action,
                entity_type,
                entity_id,
                ip_address,
                user_agent,
                details
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $userId,
            $action,
            $entityType,
            $entityId,
            $ipAddress,
            $userAgent,
            json_encode($details)
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Track an authentication attempt (successful or failed)
     * 
     * @param string $username The username used in the attempt
     * @param bool $isSuccessful Whether the attempt was successful
     * @return bool Whether the tracking was successful
     */
    public function trackAuthAttempt($username, $isSuccessful)
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->db->prepare("
            INSERT INTO auth_attempts (
                username,
                ip_address,
                user_agent,
                is_successful
            ) VALUES (?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $username,
            $ipAddress,
            $userAgent,
            $isSuccessful ? 1 : 0
        ]);
        
        // If this was a failed attempt, update the failed_login_attempts counter
        if (!$isSuccessful) {
            $this->updateFailedLoginAttempts($username);
        } else {
            // Reset failed attempts on successful login
            $this->resetFailedLoginAttempts($username);
        }
        
        return $result;
    }
    
    /**
     * Update failed login attempts for a user
     * 
     * @param string $username The username
     */
    private function updateFailedLoginAttempts($username)
    {
        // Get current failed attempts
        $stmt = $this->db->prepare("
            SELECT failed_login_attempts FROM users
            WHERE username = ?
        ");
        
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            $attempts = $user['failed_login_attempts'] + 1;
            $lockAccount = false;
            $lockedUntil = null;
            
            // Apply lockout policy based on number of failed attempts
            if ($attempts >= 10) {
                $lockAccount = true;
                $lockedUntil = date('Y-m-d H:i:s', time() + 3600); // 1 hour lockout
            } else if ($attempts >= 5) {
                $lockAccount = true;
                $lockedUntil = date('Y-m-d H:i:s', time() + 300); // 5 minute lockout
            }
            
            $stmt = $this->db->prepare("
                UPDATE users
                SET failed_login_attempts = ?,
                    locked_until = ?
                WHERE username = ?
            ");
            
            $stmt->execute([$attempts, $lockedUntil, $username]);
            
            if ($lockAccount) {
                $this->logger->warning("Account locked due to failed login attempts", [
                    'username' => $username,
                    'attempts' => $attempts,
                    'locked_until' => $lockedUntil
                ]);
            }
        }
    }
    
    /**
     * Reset failed login attempts for a user
     * 
     * @param string $username The username
     */
    private function resetFailedLoginAttempts($username)
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_login_attempts = 0,
                locked_until = NULL,
                last_login_at = NOW(),
                last_login_ip = ?
            WHERE username = ?
        ");
        
        $stmt->execute([
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $username
        ]);
    }
    
    /**
     * Check if an account is locked
     * 
     * @param string $username The username
     * @return bool|string False if not locked, otherwise the time until unlock
     */
    public function isAccountLocked($username)
    {
        $stmt = $this->db->prepare("
            SELECT locked_until FROM users
            WHERE username = ?
        ");
        
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['locked_until']) {
            $lockedUntil = strtotime($user['locked_until']);
            $now = time();
            
            if ($lockedUntil > $now) {
                // Account is locked
                $remainingTime = $lockedUntil - $now;
                $minutes = floor($remainingTime / 60);
                $seconds = $remainingTime % 60;
                
                return sprintf('%d minutes and %d seconds', $minutes, $seconds);
            } else {
                // Lock has expired, reset it
                $this->resetFailedLoginAttempts($username);
                return false;
            }
        }
        
        return false;
    }
} 