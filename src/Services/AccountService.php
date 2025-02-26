<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Models\BaseModel;
use VertoAD\Core\Utils\Logger;
use PDO;

class AccountService extends BaseModel {
    private $logger;

    public function __construct() {
        parent::__construct();
        $this->logger = new Logger('AccountService');
    }

    /**
     * Get the current balance for a user
     * 
     * @param int $userId User ID
     * @return float Current balance
     */
    public function getBalance($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT balance 
                FROM user_accounts 
                WHERE user_id = :user_id
            ");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? floatval($result['balance']) : 0.0;
        } catch (\Exception $e) {
            $this->logger->error("Error getting balance: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Adjust a user's balance and record the transaction
     * 
     * @param int $userId User ID
     * @param float $amount Amount to adjust (positive for credit, negative for debit)
     * @param string $description Transaction description
     * @return float New balance after adjustment
     */
    public function adjustBalance($userId, $amount, $description) {
        try {
            $this->db->beginTransaction();

            // Get current balance
            $currentBalance = $this->getBalance($userId);
            $newBalance = $currentBalance + $amount;
            
            if ($newBalance < 0 && $amount < 0) {
                throw new \Exception("Insufficient funds");
            }

            // Update balance
            $stmt = $this->db->prepare("
                UPDATE user_accounts 
                SET balance = :new_balance 
                WHERE user_id = :user_id
            ");
            $stmt->bindParam(':new_balance', $newBalance, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            // Record transaction
            $transactionType = $amount >= 0 ? 'adjustment' : 'withdrawal';
            $this->recordTransaction($userId, $amount, $newBalance, $transactionType, $description);

            $this->db->commit();
            return $newBalance;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error adjusting balance: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Record a transaction in the system
     * 
     * @param int $userId User ID
     * @param float $amount Transaction amount
     * @param float $newBalance Balance after transaction
     * @param string $type Transaction type (deposit, withdrawal, adjustment, ad_spend)
     * @param string $description Transaction description
     * @param string $status Transaction status (completed, pending, failed)
     * @return int Transaction ID
     */
    public function recordTransaction($userId, $amount, $newBalance, $type, $description, $status = 'completed') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO transactions 
                (user_id, amount, new_balance, type, description, status) 
                VALUES 
                (:user_id, :amount, :new_balance, :type, :description, :status)
            ");
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':amount', $amount, PDO::PARAM_STR);
            $stmt->bindParam(':new_balance', $newBalance, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            
            $stmt->execute();
            return $this->db->lastInsertId();
        } catch (\Exception $e) {
            $this->logger->error("Error recording transaction: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Deduct funds for ad spending
     * 
     * @param int $userId User ID
     * @param float $amount Amount to deduct
     * @param string $adId Ad ID or details for the description
     * @return float New balance after deduction
     */
    public function deductForAd($userId, $amount, $adId) {
        try {
            $this->db->beginTransaction();
            
            $currentBalance = $this->getBalance($userId);
            if ($currentBalance < $amount) {
                throw new \Exception("Insufficient funds for ad placement");
            }
            
            $newBalance = $currentBalance - $amount;
            
            // Update balance
            $stmt = $this->db->prepare("
                UPDATE user_accounts 
                SET balance = :new_balance 
                WHERE user_id = :user_id
            ");
            $stmt->bindParam(':new_balance', $newBalance, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Record transaction
            $description = "Ad spend for ad ID: $adId";
            $this->recordTransaction($userId, -$amount, $newBalance, 'ad_spend', $description);
            
            $this->db->commit();
            return $newBalance;
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Error deducting for ad: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get transactions for a specific user
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters (type, start_date, end_date)
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @return array List of transactions
     */
    public function getUserTransactions($userId, $filters = [], $limit = 50, $offset = 0) {
        try {
            $query = "
                SELECT t.*, u.username 
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE t.user_id = :user_id
            ";
            
            $params = [':user_id' => $userId];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $query .= " AND t.type = :type";
                $params[':type'] = $filters['type'];
            }
            
            if (!empty($filters['start_date'])) {
                $query .= " AND t.created_at >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            
            if (!empty($filters['end_date'])) {
                $query .= " AND t.created_at <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            
            $query .= " ORDER BY t.created_at DESC";
            $query .= " LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            // Bind all parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $this->logger->error("Error getting user transactions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Count total number of transactions for a user with filters
     * 
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return int Total count
     */
    public function countUserTransactions($userId, $filters = []) {
        try {
            $query = "
                SELECT COUNT(*) as total
                FROM transactions
                WHERE user_id = :user_id
            ";
            
            $params = [':user_id' => $userId];
            
            if (!empty($filters['type'])) {
                $query .= " AND type = :type";
                $params[':type'] = $filters['type'];
            }
            
            if (!empty($filters['start_date'])) {
                $query .= " AND created_at >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            
            if (!empty($filters['end_date'])) {
                $query .= " AND created_at <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
        } catch (\Exception $e) {
            $this->logger->error("Error counting user transactions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all transactions across the system
     * 
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array List of transactions
     */
    public function getAllTransactions($filters = [], $limit = 100, $offset = 0) {
        try {
            $query = "
                SELECT t.*, u.username 
                FROM transactions t
                JOIN users u ON t.user_id = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Apply filters
            if (!empty($filters['type'])) {
                $query .= " AND t.type = :type";
                $params[':type'] = $filters['type'];
            }
            
            if (!empty($filters['user_id'])) {
                $query .= " AND t.user_id = :user_id";
                $params[':user_id'] = $filters['user_id'];
            }
            
            if (!empty($filters['start_date'])) {
                $query .= " AND t.created_at >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            
            if (!empty($filters['end_date'])) {
                $query .= " AND t.created_at <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            
            $query .= " ORDER BY t.created_at DESC";
            $query .= " LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($query);
            
            // Bind all parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $this->logger->error("Error getting all transactions: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize a user account when they register
     * 
     * @param int $userId User ID
     * @param float $initialBalance Optional initial balance
     * @return bool Success status
     */
    public function initializeUserAccount($userId, $initialBalance = 0.0) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_accounts (user_id, balance)
                VALUES (:user_id, :balance)
                ON DUPLICATE KEY UPDATE balance = balance
            ");
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':balance', $initialBalance, PDO::PARAM_STR);
            
            return $stmt->execute();
        } catch (\Exception $e) {
            $this->logger->error("Error initializing user account: " . $e->getMessage());
            throw $e;
        }
    }
}
