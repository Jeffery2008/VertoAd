<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;
    private $conditions = [];
    private $parameters = [];
    private $groupStarted = false;
    private $orConditions = [];

    private function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        
        try {
            $this->pdo = new \PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}",
                $config['username'],
                $config['password'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
                ]
            );
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    /**
     * 执行SQL查询
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            
            // 将参数转换为适当的类型
            $params = array_map(function($param) {
                if ($param === null) {
                    return null;
                }
                // 如果是数字，保持数字类型
                if (is_numeric($param)) {
                    return $param + 0; // 将字符串数字转换为实际的数字类型
                }
                return (string)$param;
            }, $params);
            
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }

    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    public function commit()
    {
        return $this->pdo->commit();
    }

    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    public function prepare($sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function where($field, $value)
    {
        if ($this->groupStarted) {
            $this->orConditions[] = [$field, '=', $value];
        } else {
            $this->conditions[] = [$field, '=', $value];
        }
        $this->parameters[] = $value;
        return $this;
    }

    public function like($field, $value)
    {
        if ($this->groupStarted) {
            $this->orConditions[] = [$field, 'LIKE', $value];
        } else {
            $this->conditions[] = [$field, 'LIKE', $value];
        }
        $this->parameters[] = $value;
        return $this;
    }

    public function orLike($field, $value)
    {
        $this->orConditions[] = [$field, 'LIKE', $value];
        $this->parameters[] = $value;
        return $this;
    }

    public function groupStart()
    {
        $this->groupStarted = true;
        return $this;
    }

    public function groupEnd()
    {
        $this->groupStarted = false;
        return $this;
    }

    public function limit($limit, $offset = 0)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function get($table)
    {
        $sql = "SELECT * FROM " . $table;
        
        if (!empty($this->conditions) || !empty($this->orConditions)) {
            $sql .= " WHERE ";
            $conditions = [];
            
            foreach ($this->conditions as $condition) {
                $conditions[] = "{$condition[0]} {$condition[1]} ?";
            }
            
            if (!empty($this->orConditions)) {
                $orConditions = [];
                foreach ($this->orConditions as $condition) {
                    $orConditions[] = "{$condition[0]} {$condition[1]} ?";
                }
                if (!empty($conditions)) {
                    $sql .= implode(" AND ", $conditions) . " OR ";
                }
                $sql .= "(" . implode(" OR ", $orConditions) . ")";
            } else {
                $sql .= implode(" AND ", $conditions);
            }
        }
        
        if (isset($this->limit)) {
            $sql .= " LIMIT " . (int)$this->offset . ", " . (int)$this->limit;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->parameters);
        
        // Reset conditions and parameters
        $this->conditions = [];
        $this->parameters = [];
        $this->orConditions = [];
        $this->groupStarted = false;
        unset($this->limit);
        unset($this->offset);
        
        return $stmt->fetchAll();
    }

    public function insert($table, $data)
    {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where)
    {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $whereConditions = [];
        foreach ($where as $key => $value) {
            $whereConditions[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE " . implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete($table, $where)
    {
        $whereConditions = [];
        $values = [];
        
        foreach ($where as $key => $value) {
            $whereConditions[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function count($table)
    {
        $sql = "SELECT COUNT(*) FROM " . $table;
        
        if (!empty($this->conditions) || !empty($this->orConditions)) {
            $sql .= " WHERE ";
            $conditions = [];
            
            foreach ($this->conditions as $condition) {
                $conditions[] = "{$condition[0]} {$condition[1]} ?";
            }
            
            if (!empty($this->orConditions)) {
                $orConditions = [];
                foreach ($this->orConditions as $condition) {
                    $orConditions[] = "{$condition[0]} {$condition[1]} ?";
                }
                if (!empty($conditions)) {
                    $sql .= implode(" AND ", $conditions) . " OR ";
                }
                $sql .= "(" . implode(" OR ", $orConditions) . ")";
            } else {
                $sql .= implode(" AND ", $conditions);
            }
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->parameters);
        
        return (int)$stmt->fetchColumn();
    }
} 