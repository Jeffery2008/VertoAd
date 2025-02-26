<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Logger;

/**
 * ApiKeyService - Service for managing API keys
 */
class ApiKeyService
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
     * Constructor
     */
    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->logger = new Logger('ApiKeyService');
    }
    
    /**
     * Generate a new API key for a user
     * 
     * @param int $userId The user ID
     * @param string $name A friendly name for the key
     * @param array $permissions Permissions for this key
     * @return array|false The created API key or false on failure
     */
    public function generateKey($userId, $name, $permissions = [])
    {
        // Generate a secure random key
        $keyValue = bin2hex(random_bytes(32)); // 64 character hex string
        $keyHash = password_hash($keyValue, PASSWORD_DEFAULT);
        $permissionsJson = json_encode($permissions);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO api_keys (
                    user_id, 
                    key_hash, 
                    name, 
                    permissions, 
                    is_active, 
                    created_at
                ) VALUES (?, ?, ?, ?, 1, NOW())
            ");
            
            $result = $stmt->execute([
                $userId,
                $keyHash,
                $name,
                $permissionsJson
            ]);
            
            if ($result) {
                $keyId = $this->db->lastInsertId();
                
                // Log key creation
                $this->logger->info('API key created', [
                    'user_id' => $userId,
                    'key_id' => $keyId,
                    'name' => $name
                ]);
                
                $this->logSecurityEvent(
                    $userId,
                    'api_key_created',
                    'api_key',
                    $keyId,
                    ['name' => $name]
                );
                
                // Return the key details to be shown to the user
                // This is the ONLY time the plain key will be available
                return [
                    'id' => $keyId,
                    'key' => $keyValue,
                    'name' => $name,
                    'permissions' => $permissions,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error creating API key', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Validate an API key
     * 
     * @param string $keyValue The API key to validate
     * @return array|false The key data if valid, or false
     */
    public function validateKey($keyValue)
    {
        // Get all active keys
        $stmt = $this->db->prepare("
            SELECT * FROM api_keys 
            WHERE is_active = 1
        ");
        
        $stmt->execute();
        $keys = $stmt->fetchAll();
        
        foreach ($keys as $key) {
            // Verify the key hash
            if (password_verify($keyValue, $key['key_hash'])) {
                // Log the key usage
                $this->logKeyUsage($key['id']);
                
                return [
                    'id' => $key['id'],
                    'user_id' => $key['user_id'],
                    'name' => $key['name'],
                    'permissions' => json_decode($key['permissions'], true),
                    'created_at' => $key['created_at']
                ];
            }
        }
        
        return false;
    }
    
    /**
     * Check if a key has a specific permission
     * 
     * @param string $keyValue The API key
     * @param string $permission The permission to check
     * @return bool Whether the key has the permission
     */
    public function hasPermission($keyValue, $permission)
    {
        $keyData = $this->validateKey($keyValue);
        
        if (!$keyData) {
            return false;
        }
        
        // If the key has * permission, it has access to everything
        if (in_array('*', $keyData['permissions'])) {
            return true;
        }
        
        return in_array($permission, $keyData['permissions']);
    }
    
    /**
     * Get all API keys for a user
     * 
     * @param int $userId The user ID
     * @return array The user's API keys
     */
    public function getUserKeys($userId)
    {
        $stmt = $this->db->prepare("
            SELECT 
                id, 
                name, 
                permissions, 
                is_active, 
                created_at, 
                last_used_at 
            FROM api_keys 
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        
        $stmt->execute([$userId]);
        $keys = $stmt->fetchAll();
        
        // Process permissions
        foreach ($keys as &$key) {
            $key['permissions'] = json_decode($key['permissions'], true);
        }
        
        return $keys;
    }
    
    /**
     * Update an API key
     * 
     * @param int $keyId The API key ID
     * @param int $userId The user ID (for verification)
     * @param array $data The data to update
     * @return bool Whether the update was successful
     */
    public function updateKey($keyId, $userId, $data)
    {
        // First, verify the key belongs to the user
        $stmt = $this->db->prepare("
            SELECT * FROM api_keys 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch();
        
        if (!$key) {
            $this->logger->warning('Attempted to update API key belonging to another user', [
                'key_id' => $keyId,
                'user_id' => $userId
            ]);
            return false;
        }
        
        $updateFields = [];
        $params = [];
        
        // Update name if provided
        if (isset($data['name'])) {
            $updateFields[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        // Update permissions if provided
        if (isset($data['permissions'])) {
            $updateFields[] = 'permissions = ?';
            $params[] = json_encode($data['permissions']);
        }
        
        // Update active status if provided
        if (isset($data['is_active'])) {
            $updateFields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }
        
        if (empty($updateFields)) {
            return true; // Nothing to update
        }
        
        // Add key ID and user ID to params
        $params[] = $keyId;
        $params[] = $userId;
        
        $sql = "UPDATE api_keys SET " . implode(', ', $updateFields) . " WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $this->logSecurityEvent(
                $userId,
                'api_key_updated',
                'api_key',
                $keyId,
                $data
            );
        }
        
        return $result;
    }
    
    /**
     * Revoke an API key
     * 
     * @param int $keyId The API key ID
     * @param int $userId The user ID (for verification)
     * @return bool Whether the revocation was successful
     */
    public function revokeKey($keyId, $userId)
    {
        return $this->updateKey($keyId, $userId, ['is_active' => false]);
    }
    
    /**
     * Delete an API key
     * 
     * @param int $keyId The API key ID
     * @param int $userId The user ID (for verification)
     * @return bool Whether the deletion was successful
     */
    public function deleteKey($keyId, $userId)
    {
        // First, verify the key belongs to the user
        $stmt = $this->db->prepare("
            SELECT * FROM api_keys 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch();
        
        if (!$key) {
            $this->logger->warning('Attempted to delete API key belonging to another user', [
                'key_id' => $keyId,
                'user_id' => $userId
            ]);
            return false;
        }
        
        $stmt = $this->db->prepare("
            DELETE FROM api_keys 
            WHERE id = ? AND user_id = ?
        ");
        
        $result = $stmt->execute([$keyId, $userId]);
        
        if ($result) {
            $this->logSecurityEvent(
                $userId,
                'api_key_deleted',
                'api_key',
                $keyId,
                ['name' => $key['name']]
            );
        }
        
        return $result;
    }
    
    /**
     * Get usage statistics for an API key
     * 
     * @param int $keyId The API key ID
     * @param int $userId The user ID (for verification)
     * @param string $period The period to get stats for (day, week, month)
     * @return array The usage statistics
     */
    public function getKeyUsageStats($keyId, $userId, $period = 'month')
    {
        // First, verify the key belongs to the user
        $stmt = $this->db->prepare("
            SELECT * FROM api_keys 
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$keyId, $userId]);
        $key = $stmt->fetch();
        
        if (!$key) {
            return [];
        }
        
        // Determine the date range based on period
        $dateFrom = date('Y-m-d H:i:s');
        switch ($period) {
            case 'day':
                $dateFrom = date('Y-m-d H:i:s', strtotime('-1 day'));
                break;
            case 'week':
                $dateFrom = date('Y-m-d H:i:s', strtotime('-1 week'));
                break;
            case 'month':
                $dateFrom = date('Y-m-d H:i:s', strtotime('-1 month'));
                break;
            case 'year':
                $dateFrom = date('Y-m-d H:i:s', strtotime('-1 year'));
                break;
        }
        
        // Get usage by endpoint
        $stmt = $this->db->prepare("
            SELECT 
                endpoint, 
                COUNT(*) as count 
            FROM api_key_usage 
            WHERE api_key_id = ? 
            AND created_at >= ?
            GROUP BY endpoint
            ORDER BY count DESC
        ");
        
        $stmt->execute([$keyId, $dateFrom]);
        $endpointStats = $stmt->fetchAll();
        
        // Get usage by day
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date, 
                COUNT(*) as count 
            FROM api_key_usage 
            WHERE api_key_id = ? 
            AND created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        $stmt->execute([$keyId, $dateFrom]);
        $dateStats = $stmt->fetchAll();
        
        // Get usage by hour (last 24 hours)
        $hourDateFrom = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $stmt = $this->db->prepare("
            SELECT 
                HOUR(created_at) as hour, 
                COUNT(*) as count 
            FROM api_key_usage 
            WHERE api_key_id = ? 
            AND created_at >= ?
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ");
        
        $stmt->execute([$keyId, $hourDateFrom]);
        $hourStats = $stmt->fetchAll();
        
        // Get total usage count
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM api_key_usage 
            WHERE api_key_id = ? 
            AND created_at >= ?
        ");
        
        $stmt->execute([$keyId, $dateFrom]);
        $totalUsage = $stmt->fetch()['total'];
        
        return [
            'total' => $totalUsage,
            'endpoints' => $endpointStats,
            'dates' => $dateStats,
            'hours' => $hourStats,
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Log API key usage
     * 
     * @param int $keyId The API key ID
     * @return bool Whether the log was successful
     */
    private function logKeyUsage($keyId)
    {
        $endpoint = $_SERVER['REQUEST_URI'] ?? '/';
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt = $this->db->prepare("
            INSERT INTO api_key_usage (
                api_key_id, 
                endpoint, 
                ip_address, 
                created_at
            ) VALUES (?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $keyId,
            $endpoint,
            $ipAddress
        ]);
        
        if ($result) {
            // Update the last_used_at timestamp for the key
            $this->db->prepare("
                UPDATE api_keys 
                SET last_used_at = NOW() 
                WHERE id = ?
            ")->execute([$keyId]);
        }
        
        return $result;
    }
    
    /**
     * Log a security event
     * 
     * @param int $userId The user ID
     * @param string $action The action performed
     * @param string $entityType The entity type
     * @param int $entityId The entity ID
     * @param array $details Additional details
     * @return bool Whether the log was successful
     */
    private function logSecurityEvent($userId, $action, $entityType, $entityId, $details = [])
    {
        $securityService = new SecurityService();
        return $securityService->logSecurityEvent(
            $action,
            $entityType,
            $entityId,
            $details,
            $userId
        );
    }
} 