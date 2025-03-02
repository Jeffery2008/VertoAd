<?php

namespace App\Models;
use App\Core\Database;
use Exception;

class User
{
    public $db;
    public function __construct()
    {
        $this->db = new Database();
    }
    public function findByUsername($username)
    {
        $stmt = $this->db->query('SELECT * FROM users WHERE username = ?', [$username]);
        return $stmt->fetch();
    }
    public function findByEmail($email)
    {
        $stmt = $this->db->query('SELECT * FROM users WHERE email = ?', [$email]);
        return $stmt->fetch();
    }
    public function create($role, $username, $email, $password)
    {
        // Create password hash
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->query(
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
                [$username, $email, $passwordHash, $role]
            );
            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'email') !== false) {
                    throw new Exception('Email already exists');
                }
                throw new Exception('Username already exists');
            }
            throw $e;
        }
    }
    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $id;
        
        $this->db->query(
            "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
    }
    public function delete($id)
    {
        $this->db->query("DELETE FROM users WHERE id = ?", [$id]);
    }
    public function getById($id)
    {
        $result = $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
        return $result->fetch();
    }
    public function authenticate($email, $password)
    {
        $result = $this->db->query(
            "SELECT * FROM users WHERE email = ?",
            [$email]
        );
        
        $user = $result->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            return $user;
        }
        
        return false;
    }
    public function addBalance($id, $amount)
    {
        $this->db->query(
            "UPDATE users SET balance = balance + ? WHERE id = ?",
            [$amount, $id]
        );
    }
    public function deductBalance($id, $amount)
    {
        $user = $this->getById($id);
        
        if (!$user || $user['balance'] < $amount) {
            throw new Exception('Insufficient balance');
        }
        
        $this->db->query(
            "UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?",
            [$amount, $id, $amount]
        );
    }
    public function getByRole($role)
    {
        $result = $this->db->query(
            "SELECT * FROM users WHERE role = ?",
            [$role]
        );
        return $result->fetchAll();
    }
    public function getAll()
    {
        $stmt = $this->db->query('SELECT * FROM users');
        return $stmt->fetchAll();
    }
} 