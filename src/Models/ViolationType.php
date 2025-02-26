<?php

namespace VertoAD\Core\Models;

class ViolationType extends BaseModel
{
    protected $tableName = 'violation_types';
    
    /**
     * Get all violation types
     * 
     * @return array List of violation types
     */
    public function getAll()
    {
        $query = "SELECT * FROM {$this->tableName} ORDER BY severity DESC, name ASC";
        return $this->db->fetchAll($query);
    }
    
    /**
     * Get violation type by ID
     * 
     * @param int $id Violation type ID
     * @return array|bool Violation type data or false if not found
     */
    public function find($id)
    {
        $query = "SELECT * FROM {$this->tableName} WHERE id = :id";
        return $this->db->fetchOne($query, ['id' => $id]);
    }
    
    /**
     * Get violation type by name
     * 
     * @param string $name Violation type name
     * @return array|bool Violation type data or false if not found
     */
    public function findByName($name)
    {
        $query = "SELECT * FROM {$this->tableName} WHERE name = :name";
        return $this->db->fetchOne($query, ['name' => $name]);
    }
    
    /**
     * Create a new violation type
     * 
     * @param array $data Violation type data
     * @return int|bool Violation type ID on success, false on failure
     */
    public function create($data)
    {
        // Check if violation type already exists
        if ($this->findByName($data['name'])) {
            return false;
        }
        
        $query = "INSERT INTO {$this->tableName} (
            name, 
            description, 
            severity
        ) VALUES (
            :name, 
            :description, 
            :severity
        )";
        
        $params = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'severity' => $data['severity'] ?? 'medium'
        ];
        
        return $this->db->insert($query, $params);
    }
    
    /**
     * Update a violation type
     * 
     * @param int $id Violation type ID
     * @param array $data Updated data
     * @return bool Success
     */
    public function update($id, $data)
    {
        // If name is being changed, check if new name already exists
        if (isset($data['name'])) {
            $existing = $this->findByName($data['name']);
            if ($existing && $existing['id'] != $id) {
                return false;
            }
        }
        
        $fields = [];
        $params = ['id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        
        $query = "UPDATE {$this->tableName} SET " . implode(', ', $fields) . " WHERE id = :id";
        return $this->db->execute($query, $params);
    }
    
    /**
     * Delete a violation type
     * 
     * @param int $id Violation type ID
     * @return bool Success
     */
    public function delete($id)
    {
        $query = "DELETE FROM {$this->tableName} WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }
} 