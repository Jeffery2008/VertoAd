<?php

namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Database;

/**
 * ConversionType Model
 * 
 * Manages conversion types for tracking different kinds of conversion events
 */
class ConversionType
{
    /**
     * @var Database $db Database connection
     */
    private $db;
    
    /**
     * @var int $id Conversion type ID
     */
    private $id;
    
    /**
     * Constructor
     * 
     * @param int|null $id Conversion type ID
     */
    public function __construct($id = null)
    {
        $this->db = Database::getConnection();
        
        if ($id) {
            $this->id = $id;
        }
    }
    
    /**
     * Get conversion type by ID
     * 
     * @param int $id Conversion type ID
     * @return array|false Conversion type data or false if not found
     */
    public function find($id)
    {
        $sql = "SELECT * FROM conversion_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        
        if ($result) {
            $this->id = $id;
        }
        
        return $result;
    }
    
    /**
     * Get conversion type by name
     * 
     * @param string $name Conversion type name
     * @return array|false Conversion type data or false if not found
     */
    public function findByName($name)
    {
        $sql = "SELECT * FROM conversion_types WHERE name = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name]);
        
        $result = $stmt->fetch();
        
        if ($result) {
            $this->id = $result['id'];
        }
        
        return $result;
    }
    
    /**
     * Get all conversion types
     * 
     * @return array Array of conversion types
     */
    public function getAll()
    {
        $sql = "SELECT * FROM conversion_types ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create new conversion type
     * 
     * @param array $data Conversion type data
     * @return int|false ID of the new conversion type or false on failure
     */
    public function create($data)
    {
        $sql = "INSERT INTO conversion_types 
                (name, description, value_type, default_value) 
                VALUES (?, ?, ?, ?)";
                
        $stmt = $this->db->prepare($sql);
        
        $result = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['value_type'] ?? 'fixed',
            $data['default_value'] ?? 0.0000
        ]);
        
        if ($result) {
            $this->id = $this->db->lastInsertId();
            return $this->id;
        }
        
        return false;
    }
    
    /**
     * Update conversion type
     * 
     * @param array $data Conversion type data
     * @return bool Success
     */
    public function update($data)
    {
        if (!$this->id) {
            return false;
        }
        
        $fields = [];
        $values = [];
        
        // Build dynamic update fields
        foreach (['name', 'description', 'value_type', 'default_value'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return true; // Nothing to update
        }
        
        $values[] = $this->id; // Add ID for WHERE clause
        
        $sql = "UPDATE conversion_types 
                SET " . implode(', ', $fields) . " 
                WHERE id = ?";
                
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
    
    /**
     * Delete conversion type
     * 
     * @return bool Success
     */
    public function delete()
    {
        if (!$this->id) {
            return false;
        }
        
        // Check if this type is being used in conversions
        $sql = "SELECT COUNT(*) as count FROM conversions WHERE conversion_type_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            // Don't delete if there are conversions of this type
            return false;
        }
        
        $sql = "DELETE FROM conversion_types WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$this->id]);
    }
    
    /**
     * Get conversion count by type
     * 
     * @param array $filters Optional filters (date range, etc.)
     * @return array Conversion counts by type
     */
    public function getConversionCountsByType($filters = [])
    {
        $conditions = [];
        $params = [];
        
        // Add date range filter if provided
        if (!empty($filters['start_date'])) {
            $conditions[] = "c.conversion_time >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = "c.conversion_time <= ?";
            $params[] = $filters['end_date'];
        }
        
        // Add advertiser filter if provided
        if (!empty($filters['advertiser_id'])) {
            $conditions[] = "a.advertiser_id = ?";
            $params[] = $filters['advertiser_id'];
        }
        
        // Add ad filter if provided
        if (!empty($filters['ad_id'])) {
            $conditions[] = "c.ad_id = ?";
            $params[] = $filters['ad_id'];
        }
        
        $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";
        
        $sql = "SELECT 
                    t.id, 
                    t.name, 
                    t.value_type, 
                    COUNT(c.id) as count,
                    SUM(c.value) as total_value
                FROM conversion_types t
                LEFT JOIN conversions c ON t.id = c.conversion_type_id
                LEFT JOIN advertisements a ON c.ad_id = a.id
                {$whereClause}
                GROUP BY t.id, t.name, t.value_type
                ORDER BY count DESC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
} 