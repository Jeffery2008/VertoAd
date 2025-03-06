<?php
namespace App\Models;

require_once dirname(__DIR__) . '/Core/Database.php';
use App\Core\Database;

class AdModel
{
    protected $db;
    protected $table = 'ads';
    protected $query;

    public function __construct()
    {
        $this->db = Database::getInstance();
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

    public function countAllResults($resetQuery = true)
    {
        $result = $this->query->count($this->table);
        if ($resetQuery) {
            $this->query = $this->db;
        }
        return $result;
    }

    public function getActiveAds()
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE status = 'active'")->fetchAll();
    }

    public function getAdsByAdvertiser($advertiserId)
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE advertiser_id = ?", [$advertiserId])->fetchAll();
    }
} 