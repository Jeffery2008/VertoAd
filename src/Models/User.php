<?php
namespace Models;

use Utils\Logger;

class User extends BaseModel {
    public $id;
    public $username;
    public $password;
    public $email;
    public $type;
    public $status;
    public $created_at;
    public $updated_at;
    
    protected $table = 'users';
    protected $fillable = [
        'username',
        'password',
        'email',
        'type',
        'status'
    ];

    /**
     * Find user by username
     * @param string $username
     * @return User|null
     */
    public static function findByUsername($username) {
        $user = new self();
        try {
            $stmt = $user->db->prepare("SELECT * FROM {$user->table} WHERE username = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$username]);
            
            if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                foreach ($row as $key => $value) {
                    $user->$key = $value;
                }
                return $user;
            }
            
            return null;
        } catch (\Exception $e) {
            Logger::error('Error finding user by username: ' . $e->getMessage(), [
                'username' => $username
            ]);
            return null;
        }
    }

    /**
     * Find user by email
     * @param string $email
     * @return User|null
     */
    public static function findByEmail($email) {
        $user = new self();
        try {
            $stmt = $user->db->prepare("SELECT * FROM {$user->table} WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            
            if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                foreach ($row as $key => $value) {
                    $user->$key = $value;
                }
                return $user;
            }
            
            return null;
        } catch (\Exception $e) {
            Logger::error('Error finding user by email: ' . $e->getMessage(), [
                'email' => $email
            ]);
            return null;
        }
    }

    /**
     * Create new user with additional validation
     * @param array $data
     * @return int|false Last insert ID or false on failure
     */
    public function create(array $data) {
        try {
            $this->beginTransaction();

            // Check for existing username
            if (self::findByUsername($data['username'])) {
                throw new \Exception('Username already exists');
            }

            // Check for existing email
            if (self::findByEmail($data['email'])) {
                throw new \Exception('Email already exists');
            }

            // Hash password if provided
            if (isset($data['password'])) {
                $authService = \Services\AuthService::getInstance();
                $data['password'] = $authService->hashPassword($data['password']);
            }

            // Set default values
            $data['status'] = $data['status'] ?? 'active';
            $data['type'] = $data['type'] ?? 'advertiser';
            $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
            $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');

            $id = parent::create($data);
            if ($id) {
                $this->commit();
                return $id;
            }

            throw new \Exception('Failed to create user');

        } catch (\Exception $e) {
            $this->rollback();
            Logger::error('Error creating user: ' . $e->getMessage(), [
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Update user with additional validation
     * @param int $id User ID
     * @param array $data Update data
     * @return bool
     */
    public function update($id, array $data) {
        try {
            $this->beginTransaction();

            // Find user
            $existing = $this->find($id);
            if (!$existing) {
                throw new \Exception('User not found');
            }

            // Check username uniqueness if being updated
            if (isset($data['username']) && $data['username'] !== $existing['username']) {
                if (self::findByUsername($data['username'])) {
                    throw new \Exception('Username already exists');
                }
            }

            // Check email uniqueness if being updated
            if (isset($data['email']) && $data['email'] !== $existing['email']) {
                if (self::findByEmail($data['email'])) {
                    throw new \Exception('Email already exists');
                }
            }

            // Hash password if being updated
            if (isset($data['password'])) {
                $authService = \Services\AuthService::getInstance();
                $data['password'] = $authService->hashPassword($data['password']);
            }

            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $success = parent::update($id, $data);
            if ($success) {
                $this->commit();
                return true;
            }

            throw new \Exception('Failed to update user');

        } catch (\Exception $e) {
            $this->rollback();
            Logger::error('Error updating user: ' . $e->getMessage(), [
                'user_id' => $id,
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Helper method to update the current user
     * @param array $data Update data
     * @return bool
     */
    public function updateCurrentUser(array $data) {
        if (!isset($this->id)) {
            throw new \Exception('Cannot update user: No ID set');
        }
        return $this->update($this->id, $data);
    }
}
