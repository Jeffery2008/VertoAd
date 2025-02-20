<?php
namespace Models;

use PDO;
use Utils\Logger;

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    
    public function __construct($db = null) {
        if ($db) {
            $this->db = $db;
        } else {
            $dbConnection = new \Database();
            $this->db = $dbConnection->connect();
        }
    }

    public function find($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error("Error finding record: " . $e->getMessage());
            return false;
        }
    }

    public function all($conditions = [], $orderBy = null, $limit = null, $offset = null) {
        try {
            $sql = "SELECT * FROM {$this->table}";
            $params = [];

            if (!empty($conditions)) {
                $whereClauses = [];
                foreach ($conditions as $key => $value) {
                    $whereClauses[] = "$key = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClauses);
            }

            if ($orderBy) {
                $sql .= " ORDER BY $orderBy";
            }

            if ($limit) {
                $sql .= " LIMIT ?";
                $params[] = (int)$limit;
            }

            if ($offset) {
                $sql .= " OFFSET ?";
                $params[] = (int)$offset;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error("Error fetching records: " . $e->getMessage());
            return false;
        }
    }

    public function create(array $data) {
        try {
            $data = array_intersect_key($data, array_flip($this->fillable));
            
            $columns = implode(', ', array_keys($data));
            $values = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO {$this->table} ($columns) VALUES ($values)";
            $stmt = $this->db->prepare($sql);
            
            $success = $stmt->execute(array_values($data));
            
            if ($success) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (\Exception $e) {
            Logger::error("Error creating record: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, array $data) {
        try {
            $data = array_intersect_key($data, array_flip($this->fillable));
            
            $setClauses = [];
            foreach ($data as $key => $value) {
                $setClauses[] = "$key = ?";
            }
            
            $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . " WHERE {$this->primaryKey} = ?";
            $stmt = $this->db->prepare($sql);
            
            $values = array_values($data);
            $values[] = $id;
            
            return $stmt->execute($values);
        } catch (\Exception $e) {
            Logger::error("Error updating record: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            Logger::error("Error deleting record: " . $e->getMessage());
            return false;
        }
    }

    public function count($conditions = []) {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table}";
            $params = [];

            if (!empty($conditions)) {
                $whereClauses = [];
                foreach ($conditions as $key => $value) {
                    $whereClauses[] = "$key = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClauses);
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (\Exception $e) {
            Logger::error("Error counting records: " . $e->getMessage());
            return false;
        }
    }

    public function beginTransaction() {
        return $this->db->beginTransaction();
    }

    public function commit() {
        return $this->db->commit();
    }

    public function rollback() {
        return $this->db->rollBack();
    }
}
