<?php
namespace App\Models;

require_once dirname(__DIR__) . '/Core/Database.php';
use App\Core\Database;

class UserModel
{
    protected $db;
    protected $table = 'users';
    protected $query;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->query = $this->db;
    }

    public function find($id)
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE id = ?", [$id])->fetch();
    }

    public function findAll()
    {
        return $this->query->get($this->table);
    }

    public function where($field, $value)
    {
        $this->query = $this->query->where($field, $value);
        return $this;
    }

    public function findByUsername($username)
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE username = ?", [$username])->fetch();
    }

    public function findByEmail($email)
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE email = ?", [$email])->fetch();
    }

    public function create($role, $username, $email, $password)
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        return $this->db->insert($this->table, [
            'role' => $role,
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update($id, $data)
    {
        return $this->db->update($this->table, $data, ['id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete($this->table, ['id' => $id]);
    }

    public function getAll()
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY created_at DESC")->fetchAll();
    }

    public function updateLastLogin($id)
    {
        return $this->update($id, [
            'last_login' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function updatePassword($id, $password)
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        return $this->update($id, [
            'password_hash' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function countByRole($role)
    {
        return $this->db->query("SELECT COUNT(*) FROM {$this->table} WHERE role = ?", [$role])->fetchColumn();
    }

    public function getByRole($role, $limit = null)
    {
        $sql = "SELECT * FROM {$this->table} WHERE role = ? ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        return $this->db->query($sql, [$role])->fetchAll();
    }

    public function countAllResults($resetQuery = true)
    {
        $result = $this->query->count($this->table);
        if ($resetQuery) {
            $this->query = $this->db;
        }
        return $result;
    }
} 