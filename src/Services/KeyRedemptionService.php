<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Utils\Logger;
use VertoAD\Core\Services\KeyGenerationService;
use VertoAD\Core\Services\AccountService;

class KeyRedemptionService {
    private $logger;
    private $keyGenerationService;
    private $accountService;
    private $db;

    public function __construct(
        Logger $logger,
        KeyGenerationService $keyGenerationService,
        AccountService $accountService,
        \PDO $db
    ) {
        $this->logger = $logger;
        $this->keyGenerationService = $keyGenerationService;
        $this->accountService = $accountService;
        $this->db = $db;
    }

    /**
     * Activate a product key for a user
     * 
     * @param string $key Product key to activate
     * @param int $userId ID of the user activating the key
     * @param string $ipAddress IP address of the user
     * @param string $userAgent User agent string
     * @return array Activation result with status and message
     * @throws \Exception If activation fails
     */
    public function activateKey(string $key, int $userId, string $ipAddress, string $userAgent): array {
        try {
            $this->db->beginTransaction();

            // Get key details
            $stmt = $this->db->prepare("
                SELECT id, amount, status
                FROM product_keys
                WHERE key_value = ?
                FOR UPDATE
            ");
            $stmt->execute([$key]);
            $keyData = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$keyData) {
                throw new \Exception("Invalid product key");
            }

            if ($keyData['status'] !== 'active') {
                throw new \Exception("This key has already been used or is revoked");
            }

            // Check if key is blacklisted
            if ($this->keyGenerationService->isKeyBlacklisted($key)) {
                throw new \Exception("This key has been revoked and cannot be used");
            }

            // Validate key format and checksum
            if (!$this->keyGenerationService->validateKey($key, $keyData['amount'])) {
                throw new \Exception("Invalid key format or checksum");
            }

            // Get current user balance
            $stmt = $this->db->prepare("
                SELECT balance 
                FROM users 
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$userData) {
                throw new \Exception("User not found");
            }

            $balanceBefore = $userData['balance'];
            $balanceAfter = $balanceBefore + $keyData['amount'];

            // Update key status
            $stmt = $this->db->prepare("
                UPDATE product_keys
                SET status = 'used',
                    used_at = CURRENT_TIMESTAMP,
                    used_by = ?
                WHERE id = ?
                AND status = 'active'
            ");
            $stmt->execute([$userId, $keyData['id']]);

            if ($stmt->rowCount() === 0) {
                throw new \Exception("Failed to update key status");
            }

            // Update user balance
            $stmt = $this->db->prepare("
                UPDATE users
                SET balance = balance + ?
                WHERE id = ?
            ");
            $stmt->execute([$keyData['amount'], $userId]);

            if ($stmt->rowCount() === 0) {
                throw new \Exception("Failed to update user balance");
            }

            // Log the activation
            $stmt = $this->db->prepare("
                INSERT INTO key_activation_log
                (key_id, user_id, amount, balance_before, balance_after, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $keyData['id'],
                $userId,
                $keyData['amount'],
                $balanceBefore,
                $balanceAfter,
                $ipAddress,
                $userAgent
            ]);

            $this->db->commit();

            $this->logger->info('Key activated successfully', [
                'key_id' => $keyData['id'],
                'user_id' => $userId,
                'amount' => $keyData['amount']
            ]);

            return [
                'success' => true,
                'message' => 'Key activated successfully',
                'amount' => $keyData['amount'],
                'new_balance' => $balanceAfter
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();

            $this->logger->error('Key activation failed', [
                'error' => $e->getMessage(),
                'key' => $key,
                'user_id' => $userId
            ]);

            throw $e;
        }
    }

    /**
     * Get activation history for a user
     * 
     * @param int $userId User ID
     * @param int $limit Maximum number of records to return
     * @param int $offset Offset for pagination
     * @return array Activation history records
     */
    public function getUserActivationHistory(int $userId, int $limit = 10, int $offset = 0): array {
        $stmt = $this->db->prepare("
            SELECT 
                kal.created_at,
                kal.amount,
                kal.balance_before,
                kal.balance_after,
                kal.ip_address,
                pk.key_value,
                kb.batch_name
            FROM key_activation_log kal
            JOIN product_keys pk ON pk.id = kal.key_id
            JOIN key_batches kb ON kb.id = pk.batch_id
            WHERE kal.user_id = ?
            ORDER BY kal.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get total number of activations for a user
     * 
     * @param int $userId User ID
     * @return int Total number of activations
     */
    public function getUserActivationCount(int $userId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM key_activation_log 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);

        return (int)$stmt->fetchColumn();
    }
}
