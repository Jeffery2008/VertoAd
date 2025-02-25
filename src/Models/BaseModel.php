<?php

namespace App\Models;

use App\Utils\Database;
use PDO;

class BaseModel {
    protected $db;
    protected $tableName;

    public function __construct() {
        $this->db = Database::getConnection(); // Use getConnection() to get DB instance
        if (!$this->db) {
            // Handle database connection error
            die("Failed to connect to database in BaseModel constructor.");
        }
    }

    // Add common database interaction methods here (e.g., find, findAll, create, update, delete) later
}
