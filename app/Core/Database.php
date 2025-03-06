<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $connection = null;
    private $conditions = [];
    private $parameters = [];
    private $groupStarted = false;
    private $orConditions = [];

    private function __construct()
    {
        try {
            // 获取配置文件的绝对路径
            $configPath = dirname(dirname(__DIR__)) . '/config/database.php';
            
            if (!file_exists($configPath)) {
                throw new \Exception("Database configuration file not found at: " . $configPath);
            }
            
            $config = require $configPath;
            
            if (!is_array($config)) {
                throw new \Exception("Invalid database configuration format");
            }
            
            // 构建 DSN
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                $config['host'],
                $config['dbname'],
                $config['charset']
            );
            
            // 设置 PDO 选项
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
            ];

            // 创建 PDO 连接
            $this->connection = new PDO($dsn, $config['username'], $config['password'], $options);
            
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
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
        
        $stmt = $this->connection->prepare($sql);
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
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->connection->lastInsertId();
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
        
        $stmt = $this->connection->prepare($sql);
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
        
        $stmt = $this->connection->prepare($sql);
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
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($this->parameters);
        
        return (int)$stmt->fetchColumn();
    }
} 