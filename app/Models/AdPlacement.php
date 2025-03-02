<?php

namespace App\Models;

use App\Core\Database;

class AdPlacement
{
    private $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function create($userId, $name, $code)
    {
        $stmt = $this->db->query(
            'INSERT INTO ad_placements (user_id, name, code) VALUES (?, ?, ?)',
            [$userId, $name, $code]
        );
        return $stmt->rowCount() > 0;
    }

    public function findById($id)
    {
        $stmt = $this->db->query('SELECT * FROM ad_placements WHERE id = ?', [$id]);
        return $stmt->fetch();
    }

    public function getByUserId($userId)
    {
        $stmt = $this->db->query('SELECT * FROM ad_placements WHERE user_id = ?', [$userId]);
        return $stmt->fetchAll();
    }
}
