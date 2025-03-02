<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private $pdo;

    public function __construct()
    {
        try {
            $config = require ROOT_PATH . '/config/database.php';
            
            $this->pdo = new PDO(
                'mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'],
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            die('Connection failed: ' . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->pdo;
    }
    
    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 开始事务
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit()
    {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback()
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * 关闭数据库连接
     */
    public function close()
    {
        $this->pdo = null;
    }
} 