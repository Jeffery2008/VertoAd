<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Utils\Logger;

class KeyGenerationService {
    private $logger;
    private $db; // Add database connection

    private const KEY_LENGTH = 25;
    private const KEY_SEGMENTS = 5;
    private const SEGMENT_LENGTH = 5;
    private const VALID_CHARS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Excluding similar looking characters

    public function __construct(Logger $logger, \PDO $db) {
        $this->logger = $logger;
        $this->db = $db; // Initialize database connection
    }

    /**
     * Generate a batch of unique product keys
     * 
     * @param int $count Number of keys to generate
     * @param float $value Value denomination for the keys
     * @return array Array of generated keys
     */
    public function generateKeyBatch(int $count, float $value): array {
        $keys = [];
        $attempts = 0;
        $maxAttempts = $count * 2; // Allow some retry buffer

        while (count($keys) < $count && $attempts < $maxAttempts) {
            $key = $this->generateSingleKey($value);
            
            // Ensure uniqueness
            if (!in_array($key, $keys) && $this->isKeyUnique($key)) {
                $keys[] = $key;
                
                // Log successful generation
                $this->logger->info('Key generated', [
                    'key_hash' => hash('sha256', $key),
                    'value' => $value
                ]);
            }
            
            $attempts++;
        }

        if (count($keys) < $count) {
            $this->logger->error('Failed to generate requested number of keys', [
                'requested' => $count,
                'generated' => count($keys)
            ]);
        }

        return $keys;
    }

    /**
     * Generate a single product key
     * 
     * @param float $value Value denomination for the key
     * @return string Generated key in XXXXX-XXXXX-XXXXX-XXXXX-XXXXX format
     */
    private function generateSingleKey(float $value): string {
        $segments = [];
        $rawKey = '';

        // Generate random segments
        for ($i = 0; $i < self::KEY_SEGMENTS - 1; $i++) {
            $segment = $this->generateSegment();
            $segments[] = $segment;
            $rawKey .= $segment;
        }

        // Generate checksum segment
        $checksum = $this->generateChecksumSegment($rawKey, $value);
        $segments[] = $checksum;

        // Format key with hyphens
        return implode('-', $segments);
    }

    /**
     * Generate a random segment of the key
     * 
     * @return string 5-character segment
     */
    private function generateSegment(): string {
        $segment = '';
        $charCount = strlen(self::VALID_CHARS);

        for ($i = 0; $i < self::SEGMENT_LENGTH; $i++) {
            $segment .= self::VALID_CHARS[random_int(0, $charCount - 1)];
        }

        return $segment;
    }

    /**
     * Generate checksum segment based on previous segments and value
     * 
     * @param string $rawKey Concatenated previous segments
     * @param float $value Key denomination value
     * @return string Checksum segment
     */
    private function generateChecksumSegment(string $rawKey, float $value): string {
        // Create checksum base from key and value
        $checksumBase = $rawKey . strval($value);
        $hash = hash('sha256', $checksumBase);
        
        // Use first 20 bits of hash (5 characters * 4 bits)
        $checksumValue = hexdec(substr($hash, 0, 5));
        
        // Convert to valid characters
        $segment = '';
        $charCount = strlen(self::VALID_CHARS);
        
        for ($i = 0; $i < self::SEGMENT_LENGTH; $i++) {
            $index = $checksumValue % $charCount;
            $segment .= self::VALID_CHARS[$index];
            $checksumValue = intdiv($checksumValue, $charCount);
        }
        
        return $segment;
    }

    /**
     * Validate a product key format and checksum, and check if blacklisted
     * 
     * @param string $key Key to validate
     * @param float $expectedValue Expected value denomination
     * @return bool Whether key is valid and not blacklisted
     */
    public function validateKey(string $key, float $expectedValue): bool {
        // Check if blacklisted first
        if ($this->isKeyBlacklisted($key)) {
            return false; // Blacklisted keys are invalid
        }

        // Check format and checksum
        if (!preg_match('/^[' . self::VALID_CHARS . ']{5}(-[' . self::VALID_CHARS . ']{5}){4}$/', $key)) {
            return false;
        }

        // Split into segments
        $segments = explode('-', $key);
        
        // Reconstruct raw key without checksum
        $rawKey = implode('', array_slice($segments, 0, -1));
        
        // Generate expected checksum
        $expectedChecksum = $this->generateChecksumSegment($rawKey, $expectedValue);
        
        // Compare with actual checksum
        return $segments[4] === $expectedChecksum;
    }

    /**
     * Check if a key already exists in the database
     * 
     * @param string $key Key to check
     * @return bool Whether key is unique
     */
    private function isKeyUnique(string $key): bool {
        // TODO: Implement database check
        return true;
    }

    /**
     * Blacklist a product key
     *
     * @param string $key Key to blacklist
     * @return bool True on success, false on failure
     */
    public function blacklistKey(string $key): bool {
        try {
            $stmt = $this->db->prepare("
                UPDATE product_keys
                SET status = 'revoked'
                WHERE key_value = ?
            ");
            $stmt->execute([$key]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            $this->logger->error('Failed to blacklist key', [
                'error' => $e->getMessage(),
                'key' => $key
            ]);
            return false;
        }
    }

    /**
     * Check if a key is blacklisted (revoked)
     *
     * @param string $key Key to check
     * @return bool True if blacklisted, false otherwise
     */
    public function isKeyBlacklisted(string $key): bool {
        $stmt = $this->db->prepare("
            SELECT status
            FROM product_keys
            WHERE key_value = ?
        ");
        $stmt->execute([$key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result && $result['status'] === 'revoked';
    }

    /**
     * Revoke all keys in a batch
     *
     * @param int $batchId Batch ID
     * @param int $adminUserId Admin user ID performing the action
     * @return bool True on success, false on failure
     */
    public function revokeBatch(int $batchId, int $adminUserId): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE product_keys
                SET status = 'revoked', revoked_at = NOW(), revoked_by = ?
                WHERE batch_id = ? AND status = 'active'
            ");
            $stmt->execute([$adminUserId, $batchId]);
            $revokedKeysCount = $stmt->rowCount();

            // Audit log for batch revocation
            $auditStmt = $this->db->prepare("
                INSERT INTO key_audit_log (batch_id, action_type, description, admin_user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $batchId,
                'revoke_batch',
                "Revoked {$revokedKeysCount} keys in batch ID {$batchId}",
                $adminUserId
            ]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to revoke batch', [
                'error' => $e->getMessage(),
                'batch_id' => $batchId,
                'admin_user_id' => $adminUserId
            ]);
            return false;
        }
    }

    /**
     * Revoke a single key
     *
     * @param int $keyId Key ID
     * @param int $adminUserId Admin user ID performing the action
     * @return bool True on success, false on failure
     */
    public function revokeKey(int $keyId, int $adminUserId): bool {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE product_keys
                SET status = 'revoked', revoked_at = NOW(), revoked_by = ?
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$adminUserId, $keyId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return false; // Key not found or not active
            }

            // Audit log for single key revocation
            $auditStmt = $this->db->prepare("
                INSERT INTO key_audit_log (key_id, action_type, description, admin_user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $keyId,
                'revoke_key',
                "Revoked key ID {$keyId}",
                $adminUserId
            ]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to revoke key', [
                'error' => $e->getMessage(),
                'key_id' => $keyId,
                'admin_user_id' => $adminUserId
            ]);
            return false;
        }
    }

    /**
     * Update status of all keys in a batch
     * 
     * @param int $batchId Batch ID
     * @param string $newStatus New status to set (unused, used, revoked, active)
     * @param int $adminUserId Admin user ID performing the action
     * @return bool True on success, false on failure
     */
    public function updateKeyBatchStatus(int $batchId, string $newStatus, int $adminUserId): bool {
        $allowedStatuses = ['unused', 'used', 'revoked', 'active'];
        if (!in_array($newStatus, $allowedStatuses)) {
            $this->logger->error('Invalid key status requested', [
                'status' => $newStatus,
                'batch_id' => $batchId,
                'admin_user_id' => $adminUserId
            ]);
            return false; // Invalid status
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE product_keys
                SET status = ?, updated_at = NOW(), updated_by = ?
                WHERE batch_id = ?
            ");
            $stmt->execute([$newStatus, $adminUserId, $batchId]);
            $updatedKeysCount = $stmt->rowCount();

            // Audit log for batch status update
            $auditStmt = $this->db->prepare("
                INSERT INTO key_audit_log (batch_id, action_type, description, admin_user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $auditStmt->execute([
                $batchId,
                'update_batch_status',
                "Updated status to '{$newStatus}' for {$updatedKeysCount} keys in batch ID {$batchId}",
                $adminUserId
            ]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to update key batch status', [
                'error' => $e->getMessage(),
                'batch_id' => $batchId,
                'status' => $newStatus,
                'admin_user_id' => $adminUserId
            ]);
            return false;
        }
    }
}
