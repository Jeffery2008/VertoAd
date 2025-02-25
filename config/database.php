<?php

namespace App\Utils;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $conn;

    private $host = 'localhost'; // Default, will be updated by installer
    private $db_name = 'ad_system'; // Default, will be updated by installer
    private $username = 'root'; // Default, will be updated by installer
    private $password = ''; // Default, will be updated by installer

    private function __construct() {
        $this->host = 'localhost'; // Fallback default
        $this->db_name = 'ad_system'; // Fallback default
        $this->username = 'root'; // Fallback default
        $this->password = ''; // Fallback default
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function connect() {
        if ($this->conn) {
            return $this->conn; // Return existing connection if available
        }

        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }

        return $this->conn;
    }

    public static function getConnection() {
        $instance = self::getInstance();
        return $instance->connect();
    }
}
