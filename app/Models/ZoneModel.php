<?php

namespace App\Models;

use App\Core\Database;

class ZoneModel
{
    protected $db;
    protected $table = 'zones';
    protected $query;

    public function __construct()
    {
        $this->db = new Database();
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

    public function like($field, $value)
    {
        $this->query = $this->query->like($field, $value);
        return $this;
    }

    public function orLike($field, $value)
    {
        $this->query = $this->query->orLike($field, $value);
        return $this;
    }

    public function groupStart()
    {
        $this->query = $this->query->groupStart();
        return $this;
    }

    public function groupEnd()
    {
        $this->query = $this->query->groupEnd();
        return $this;
    }

    public function limit($limit, $offset = 0)
    {
        $this->query = $this->query->limit($limit, $offset);
        return $this;
    }

    public function countAllResults($resetQuery = true)
    {
        $result = $this->query->count($this->table);
        if ($resetQuery) {
            $this->query = $this->db;
        }
        return $result;
    }

    public function create($data)
    {
        return $this->db->insert($this->table, $data);
    }

    public function update($id, $data)
    {
        return $this->db->update($this->table, $data, ['id' => $id]);
    }

    public function delete($id)
    {
        return $this->db->delete($this->table, ['id' => $id]);
    }
} 