<?php

namespace App\Models;

use App\Models\BaseModel;

class Advertisement extends BaseModel
{
    protected $tableName = 'advertisements';

    public function __construct()
    {
        parent::__construct();
    }

    public function findAll()
    {
        return $this->db->fetchAll("SELECT * FROM {$this->tableName}");
    }

    public function find($id)
    {
        return $this->db->fetchRow("SELECT * FROM {$this->tableName} WHERE id = ?", [$id]);
    }

    public function create(array $data)
    {
        return $this->db->insert($this->tableName, $data);
    }

    public function update($id, array $data)
    {
        return $this->db->update($this->tableName, $data, ['id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete($this->tableName, ['id' => $id]);
    }
}
