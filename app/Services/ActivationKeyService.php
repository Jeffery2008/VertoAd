<?php

namespace App\Services;

use App\Models\ActivationKey; // Assuming model exists or will be created
use App\Models\User;       // Assuming User model exists
use App\Models\Ad;         // Assuming Ad model exists
use Exception;
use PDO;

class ActivationKeyService
{
    protected $db;

    // Assuming a PDO connection is injected or available globally/via parent
    // Adjust constructor/db access as per actual application setup
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generates a specified number of activation keys.
     *
     * @param int $count The number of keys to generate.
     * @param string $valueType Type of value ('duration_days' or 'credit').
     * @param float $value The value associated with the key.
     * @param string|null $expiresAt Optional expiry date (YYYY-MM-DD HH:MM:SS).
     * @return array An array of generated key strings.
     * @throws Exception If generation fails.
     */
    public function generateKeys(int $count, string $valueType, float $value, ?string $expiresAt = null): array
    {
        if ($count <= 0) {
            throw new Exception("Number of keys must be positive.");
        }
        if (!in_array($valueType, ['duration_days', 'credit'])) {
            throw new Exception("Invalid value type specified.");
        }

        $generatedKeys = [];
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO activation_keys (key_string, value_type, value, expires_at, status, created_at)
                VALUES (:key_string, :value_type, :value, :expires_at, 'unused', NOW())
            ");

            for ($i = 0; $i < $count; $i++) {
                $keyString = $this->generateUniqueKeyString(); // Implement unique key generation
                $stmt->bindParam(':key_string', $keyString);
                $stmt->bindParam(':value_type', $valueType);
                $stmt->bindParam(':value', $value);
                $stmt->bindParam(':expires_at', $expiresAt);
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert key: " . implode(", ", $stmt->errorInfo()));
                }
                $generatedKeys[] = $keyString;
            }

            $this->db->commit();
            return $generatedKeys;
        } catch (Exception $e) {
            $this->db->rollBack();
            // Log the exception $e->getMessage()
            throw new Exception("Failed to generate keys. " . $e->getMessage());
        }
    }

    /**
     * Redeems an activation key for a specific user.
     *
     * @param string $keyString The key string to redeem.
     * @param int $userId The ID of the user redeeming the key.
     * @param int|null $applyToAdId Optional Ad ID to apply duration to directly.
     * @return array Details of the redeemed key and outcome.
     * @throws Exception If redemption fails (invalid key, expired, already used, etc.).
     */
    public function redeemKey(string $keyString, int $userId, ?int $applyToAdId = null): array
    {
        $this->db->beginTransaction();

        try {
            // 1. Find the key and lock the row for update
            $stmt = $this->db->prepare("
                SELECT id, value_type, value, status, expires_at
                FROM activation_keys
                WHERE key_string = :key_string
                FOR UPDATE
            ");
            $stmt->bindParam(':key_string', $keyString);
            $stmt->execute();
            $key = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$key) {
                throw new Exception("Activation key not found.");
            }

            if ($key['status'] === 'used') {
                throw new Exception("Activation key has already been used.");
            }

            if ($key['expires_at'] !== null && strtotime($key['expires_at']) < time()) {
                throw new Exception("Activation key has expired.");
            }

            // 2. Mark the key as used
            $updateStmt = $this->db->prepare("
                UPDATE activation_keys
                SET status = 'used',
                    used_by = :user_id,
                    used_at = NOW()
                WHERE id = :id
            ");
            $updateStmt->bindParam(':user_id', $userId);
            $updateStmt->bindParam(':id', $key['id']);
            if (!$updateStmt->execute()) {
                 throw new Exception("Failed to mark key as used.");
            }

            // 3. Apply the value based on type
            $resultMessage = "";
            if ($key['value_type'] === 'credit') {
                // Add credit to user's balance
                $updateUserStmt = $this->db->prepare("
                    UPDATE users SET balance = balance + :value WHERE id = :user_id
                ");
                $updateUserStmt->bindParam(':value', $key['value']);
                $updateUserStmt->bindParam(':user_id', $userId);
                if (!$updateUserStmt->execute()) {
                    throw new Exception("Failed to update user balance.");
                }
                 $resultMessage = "Credit applied successfully.";

            } elseif ($key['value_type'] === 'duration_days') {
                if ($applyToAdId !== null) {
                    // Apply duration directly to a specific ad
                    // Find the ad and ensure it belongs to the user
                    $adCheckStmt = $this->db->prepare("SELECT user_id, start_datetime, end_datetime, purchased_duration_days FROM ads WHERE id = :ad_id");
                    $adCheckStmt->bindParam(':ad_id', $applyToAdId);
                    $adCheckStmt->execute();
                    $ad = $adCheckStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$ad || $ad['user_id'] != $userId) {
                         throw new Exception("Ad not found or does not belong to the user.");
                    }
                    
                    // Calculate new end date based on existing end date or current time
                    $startDate = $ad['end_datetime'] ? $ad['end_datetime'] : 'NOW()';
                    // This logic might need refinement based on desired behavior (add to end vs set new period)
                    // Simple addition for now:
                    $updateAdStmt = $this->db->prepare("
                        UPDATE ads 
                        SET purchased_duration_days = purchased_duration_days + :duration, 
                            end_datetime = DATE_ADD(IFNULL(end_datetime, NOW()), INTERVAL :duration DAY) 
                            -- Potentially update start_datetime if it's null?
                            -- start_datetime = IFNULL(start_datetime, NOW()) 
                        WHERE id = :ad_id
                    ");
                    $updateAdStmt->bindParam(':duration', $key['value'], PDO::PARAM_INT);
                    $updateAdStmt->bindParam(':ad_id', $applyToAdId);
                    if (!$updateAdStmt->execute()) {
                        throw new Exception("Failed to apply duration to ad.");
                    }
                     $resultMessage = "Duration applied successfully to ad ID: " . $applyToAdId;
                    
                } else {
                    // Duration credit applied - needs a mechanism to store/track this
                    // For now, throw an error or potentially add to a 'duration_credit' field on user?
                    // Let's prevent this for now
                    throw new Exception("Applying duration requires specifying an Ad ID.");
                    // Alternative: Add duration credit to user (requires schema change)
                    // $updateUserStmt = $this->db->prepare("UPDATE users SET duration_credit_days = duration_credit_days + :value WHERE id = :user_id");
                    // ... execute ...
                    //$resultMessage = "Duration credit added to your account.";
                }
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => $resultMessage,
                'value_type' => $key['value_type'],
                'value' => $key['value']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
             // Log the exception $e->getMessage()
            throw new Exception("Key redemption failed: " . $e->getMessage());
        }
    }

    /**
     * Generates a unique activation key string.
     *
     * Checks the database to ensure the generated key is unique.
     *
     * @param int $length Length of the key string (excluding separators).
     * @param int $maxAttempts Max attempts to find a unique key.
     * @return string The unique key string.
     * @throws Exception If a unique key cannot be generated within max attempts.
     */
    protected function generateUniqueKeyString(int $length = 25, int $maxAttempts = 10): string
    {
        $attempt = 0;
        do {
            $bytes = random_bytes(ceil($length / 2));
            $key = strtoupper(bin2hex($bytes));
            $key = substr($key, 0, $length);
            
            // Add dashes for Windows activation key format (5 groups of 5 chars)
            if ($length === 25) {
                $key = implode('-', str_split($key, 5));
            }
            
            // Check if key exists in the database
            $stmt = $this->db->prepare("SELECT 1 FROM activation_keys WHERE key_string = :key_string LIMIT 1");
            $stmt->bindParam(':key_string', $key);
            $stmt->execute();
            
            if ($stmt->fetchColumn() === false) {
                // Key is unique
                return $key;
            }

            $attempt++;
        } while ($attempt < $maxAttempts);

        // If we reach here, failed to generate a unique key
        throw new Exception("Failed to generate a unique activation key after {$maxAttempts} attempts.");
    }

    /**
     * Retrieves a paginated list of activation keys.
     *
     * @param int $page Page number.
     * @param int $limit Items per page.
     * @param string|null $status Filter by status ('unused', 'used').
     * @return array Contains 'keys' list and 'total' count.
     */
    public function getKeys(int $page = 1, int $limit = 20, ?string $status = null): array
    {
        $offset = ($page - 1) * $limit;
        $keys = [];
        $total = 0;

        // Validate status
        if ($status !== null && !in_array($status, ['unused', 'used'])) {
            $status = null; // Ignore invalid status
        }

        try {
            // Base query
            $countSql = "SELECT COUNT(*) FROM activation_keys";
            $selectSql = "SELECT id, key_string, value_type, value, status, expires_at, created_at, used_by, used_at 
                          FROM activation_keys";
            
            $whereClauses = [];
            $params = [];

            // Add status filter if provided
            if ($status) {
                 $whereClauses[] = "status = :status";
                 $params[':status'] = $status;
            }
            
            if (!empty($whereClauses)) {
                $countSql .= " WHERE " . implode(' AND ', $whereClauses);
                 $selectSql .= " WHERE " . implode(' AND ', $whereClauses);
            }

            // Get total count
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Get paginated keys
            $selectSql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $selectStmt = $this->db->prepare($selectSql);
            // Bind common params
            foreach ($params as $key => &$val) {
                 $selectStmt->bindParam($key, $val);
            }
            $selectStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $selectStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $selectStmt->execute();
            $keys = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Keys Exception: " . $e->getMessage());
            // Return empty result on error
        }

        return [
            'keys' => $keys,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    // TODO: Add other methods as needed, e.g., listKeys, getKeyStatus, etc.
} 