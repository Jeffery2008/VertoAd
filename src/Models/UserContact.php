<?php

namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Cache;
use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Logger;

class UserContact
{
    private Database $db;
    private Cache $cache;
    private Logger $logger;

    // Cache keys
    private const CACHE_KEY_EMAIL = 'user_contact:email:%s';
    private const CACHE_KEY_PHONE = 'user_contact:phone:%s';
    private const CACHE_KEY_USER = 'user_contact:user:%d';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->logger = Logger::getInstance();
    }

    /**
     * Get user contact information by user ID
     *
     * @param int $userId
     * @return array|null Contact information or null if not found
     */
    public function getByUserId(int $userId): ?array
    {
        // Try cache first
        $cacheKey = sprintf(self::CACHE_KEY_USER, $userId);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Query database
        $sql = "SELECT * FROM user_contacts WHERE user_id = ?";
        try {
            $contact = $this->db->fetchOne($sql, [$userId]);
            if ($contact) {
                $this->cache->set($cacheKey, $contact, self::CACHE_TTL);
                return $contact;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to get user contact: " . $e->getMessage(), [
                'user_id' => $userId
            ]);
        }

        return null;
    }

    /**
     * Get user contact information by email
     *
     * @param string $email
     * @return array|null Contact information or null if not found
     */
    public function getByEmail(string $email): ?array
    {
        $cacheKey = sprintf(self::CACHE_KEY_EMAIL, md5($email));
        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $sql = "SELECT * FROM user_contacts WHERE email = ?";
        try {
            $contact = $this->db->fetchOne($sql, [$email]);
            if ($contact) {
                $this->cache->set($cacheKey, $contact, self::CACHE_TTL);
                return $contact;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to get user contact by email: " . $e->getMessage(), [
                'email' => $email
            ]);
        }

        return null;
    }

    /**
     * Get user contact information by phone number
     *
     * @param string $phone
     * @return array|null Contact information or null if not found
     */
    public function getByPhone(string $phone): ?array
    {
        $cacheKey = sprintf(self::CACHE_KEY_PHONE, $phone);
        $cached = $this->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        $sql = "SELECT * FROM user_contacts WHERE phone = ?";
        try {
            $contact = $this->db->fetchOne($sql, [$phone]);
            if ($contact) {
                $this->cache->set($cacheKey, $contact, self::CACHE_TTL);
                return $contact;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to get user contact by phone: " . $e->getMessage(), [
                'phone' => $phone
            ]);
        }

        return null;
    }

    /**
     * Update or create user contact information
     *
     * @param int $userId
     * @param array $data Contact data (email and/or phone)
     * @return bool Success status
     */
    public function updateOrCreate(int $userId, array $data): bool
    {
        try {
            $existing = $this->getByUserId($userId);
            
            if ($existing) {
                $sql = "UPDATE user_contacts SET ";
                $params = [];
                $updates = [];

                if (isset($data['email'])) {
                    $updates[] = "email = ?";
                    $updates[] = "email_verified = FALSE";
                    $updates[] = "email_verified_at = NULL";
                    $params[] = $data['email'];
                }

                if (isset($data['phone'])) {
                    $updates[] = "phone = ?";
                    $updates[] = "phone_verified = FALSE";
                    $updates[] = "phone_verified_at = NULL";
                    $params[] = $data['phone'];
                }

                $sql .= implode(", ", $updates) . " WHERE user_id = ?";
                $params[] = $userId;

                $this->db->execute($sql, $params);
            } else {
                $sql = "INSERT INTO user_contacts (user_id, email, phone) VALUES (?, ?, ?)";
                $this->db->execute($sql, [
                    $userId,
                    $data['email'] ?? null,
                    $data['phone'] ?? null
                ]);
            }

            // Clear all related caches
            $this->clearContactCache($userId, $data['email'] ?? null, $data['phone'] ?? null);
            return true;

        } catch (\Exception $e) {
            $this->logger->error("Failed to update user contact: " . $e->getMessage(), [
                'user_id' => $userId,
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Verify user's email address
     *
     * @param int $userId
     * @param string $email
     * @return bool Success status
     */
    public function verifyEmail(int $userId, string $email): bool
    {
        try {
            $sql = "UPDATE user_contacts SET 
                    email_verified = TRUE,
                    email_verified_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND email = ?";
            
            $result = $this->db->execute($sql, [$userId, $email]);
            if ($result) {
                $this->clearContactCache($userId, $email);
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to verify email: " . $e->getMessage(), [
                'user_id' => $userId,
                'email' => $email
            ]);
        }
        return false;
    }

    /**
     * Verify user's phone number
     *
     * @param int $userId
     * @param string $phone
     * @return bool Success status
     */
    public function verifyPhone(int $userId, string $phone): bool
    {
        try {
            $sql = "UPDATE user_contacts SET 
                    phone_verified = TRUE,
                    phone_verified_at = CURRENT_TIMESTAMP
                    WHERE user_id = ? AND phone = ?";
            
            $result = $this->db->execute($sql, [$userId, $phone]);
            if ($result) {
                $this->clearContactCache($userId, null, $phone);
                return true;
            }
        } catch (\Exception $e) {
            $this->logger->error("Failed to verify phone: " . $e->getMessage(), [
                'user_id' => $userId,
                'phone' => $phone
            ]);
        }
        return false;
    }

    /**
     * Clear contact related cache
     *
     * @param int $userId
     * @param string|null $email
     * @param string|null $phone
     */
    private function clearContactCache(int $userId, ?string $email = null, ?string $phone = null): void
    {
        $this->cache->delete(sprintf(self::CACHE_KEY_USER, $userId));
        
        if ($email) {
            $this->cache->delete(sprintf(self::CACHE_KEY_EMAIL, md5($email)));
        }
        
        if ($phone) {
            $this->cache->delete(sprintf(self::CACHE_KEY_PHONE, $phone));
        }
    }
} 