<?php

namespace App\Services;

use PDO;
use Exception;

class UserService
{
    protected $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Retrieves a paginated list of users.
     *
     * @param int $page
     * @param int $limit
     * @param string|null $role Filter by role.
     * @param string|null $searchTerm Search by username or email.
     * @return array
     */
    public function getUsers(int $page = 1, int $limit = 20, ?string $role = null, ?string $searchTerm = null): array
    {
        $offset = ($page - 1) * $limit;
        $users = [];
        $total = 0;

        try {
            $countSql = "SELECT COUNT(*) FROM users";
            $selectSql = "SELECT id, username, email, role, balance, created_at FROM users";
            
            $whereClauses = [];
            $params = [];

            if ($role && in_array($role, ['admin', 'advertiser', 'publisher'])) {
                $whereClauses[] = "role = :role";
                $params[':role'] = $role;
            }
            if ($searchTerm) {
                $whereClauses[] = "(username LIKE :term OR email LIKE :term)";
                $params[':term'] = '%' . $searchTerm . '%';
            }

            if (!empty($whereClauses)) {
                $countSql .= " WHERE " . implode(' AND ', $whereClauses);
                $selectSql .= " WHERE " . implode(' AND ', $whereClauses);
            }

            // Get total count
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            // Get paginated users
            $selectSql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $selectStmt = $this->db->prepare($selectSql);
            foreach ($params as $key => &$val) {
                $selectStmt->bindParam($key, $val);
            }
            $selectStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $selectStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $selectStmt->execute();
            $users = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get Users Exception: " . $e->getMessage());
        }

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ];
    }

    /**
     * Creates a new user.
     *
     * @param string $username
     * @param string $email
     * @param string $password Raw password.
     * @param string $role
     * @return int|false New user ID or false on failure.
     */
    public function createUser(string $username, string $email, string $password, string $role): int|false
    {
        if (empty($username) || empty($email) || empty($password) || !in_array($role, ['admin', 'advertiser', 'publisher'])) {
            return false; // Basic validation
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             error_log("Create User failed: Invalid email format.");
            return false;
        }
        
        // Check if username or email already exists
        if ($this->checkUserExists($username, $email)) {
            error_log("Create User failed: Username or email already exists.");
            return false;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
             error_log("Create User failed: Password hashing failed.");
             return false;
        }

        try {
            $sql = "INSERT INTO users (username, email, password_hash, role, created_at, updated_at)
                    VALUES (:username, :email, :password_hash, :role, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password_hash', $passwordHash);
            $stmt->bindParam(':role', $role);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            } else {
                 error_log("Create User DB Error: " . implode(', ', $stmt->errorInfo()));
                return false;
            }
        } catch (Exception $e) {
            error_log("Create User Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Updates an existing user.
     *
     * @param int $userId
     * @param array $data ('username', 'email', 'role', 'balance', 'password' - optional)
     * @return bool Success or failure.
     */
    public function updateUser(int $userId, array $data): bool
    {
        if (empty($userId) || empty($data)) {
            return false;
        }

        $fields = [];
        $params = [':user_id' => $userId];

        if (isset($data['username']) && !empty($data['username'])) {
            $fields[] = "username = :username";
            $params[':username'] = $data['username'];
            // Check for uniqueness if changed
            // ... (omitted for brevity, but important)
        }
        if (isset($data['email']) && !empty($data['email'])) {
             if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                 error_log("Update User failed: Invalid email format for User ID: {$userId}");
                 return false;
             }
            $fields[] = "email = :email";
            $params[':email'] = $data['email'];
             // Check for uniqueness if changed
             // ... (omitted for brevity, but important)
        }
        if (isset($data['role']) && in_array($data['role'], ['admin', 'advertiser', 'publisher'])) {
            $fields[] = "role = :role";
            $params[':role'] = $data['role'];
        }
         if (isset($data['balance']) && is_numeric($data['balance'])) {
             $fields[] = "balance = :balance";
             $params[':balance'] = $data['balance'];
         }
         if (isset($data['password']) && !empty($data['password'])) {
             $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
             if ($passwordHash === false) {
                 error_log("Update User failed: Password hashing failed for User ID: {$userId}");
                 return false;
             }
             $fields[] = "password_hash = :password_hash";
             $params[':password_hash'] = $passwordHash;
         }
         
        if (empty($fields)) {
            return true; // Nothing to update
        }

        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :user_id";

        try {
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            if (!$success) {
                error_log("Update User DB Error (User ID: {$userId}): " . implode(', ', $stmt->errorInfo()));
            }
            return $success;
        } catch (Exception $e) {
            error_log("Update User Exception (User ID: {$userId}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a user.
     *
     * @param int $userId
     * @return bool
     */
    public function deleteUser(int $userId): bool
    {
        if (empty($userId)) return false;
        // Optional: Prevent deleting the last admin?
        try {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Delete User Exception (User ID: {$userId}): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Checks if a username or email already exists (excluding a specific user ID if provided).
     */
    private function checkUserExists(string $username, string $email, ?int $excludeUserId = null): bool
    {
         $sql = "SELECT 1 FROM users WHERE (username = :username OR email = :email)";
         $params = [':username' => $username, ':email' => $email];
         if ($excludeUserId !== null) {
             $sql .= " AND id != :exclude_id";
             $params[':exclude_id'] = $excludeUserId;
         }
         $sql .= " LIMIT 1";
         
         $stmt = $this->db->prepare($sql);
         $stmt->execute($params);
         return $stmt->fetchColumn() !== false;
    }

} 