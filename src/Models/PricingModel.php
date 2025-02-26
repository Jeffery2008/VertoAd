<?php
namespace VertoAD\Core\Models;

use PDO;

/**
 * PricingModel - Model for ad pricing strategies
 */
class PricingModel extends BaseModel
{
    /**
     * Pricing model types
     */
    const TYPE_CPM = 'cpm';           // Cost per thousand impressions
    const TYPE_CPC = 'cpc';           // Cost per click
    const TYPE_TIME_BASED = 'time_based';  // Time-based pricing
    const TYPE_POSITION = 'position_based'; // Position-based pricing
    const TYPE_MIXED = 'mixed';       // Mixed model pricing
    
    /**
     * @var string $tableName The database table name
     */
    protected $tableName = 'pricing_models';
    
    /**
     * Get a list of all pricing models
     * 
     * @param bool $activeOnly Whether to get only active models
     * @return array List of pricing models
     */
    public function getAll($activeOnly = true) 
    {
        $query = "SELECT * FROM {$this->tableName}";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY name ASC";
        
        return $this->db->query($query);
    }
    
    /**
     * Get a pricing model by its ID
     * 
     * @param int $id The pricing model ID
     * @return array|null The pricing model data or null if not found
     */
    public function getById($id) 
    {
        return $this->db->queryOne(
            "SELECT * FROM {$this->tableName} WHERE id = ?",
            [$id]
        );
    }
    
    /**
     * Create a new pricing model
     * 
     * @param array $data The pricing model data
     * @return int|false The ID of the new pricing model or false if creation failed
     */
    public function create($data) 
    {
        $this->validateData([
            'name' => 'required|string',
            'type' => 'required|in:cpm,cpc,time_based,position_based,mixed',
            'description' => 'string'
        ], $data);
        
        return $this->db->insert($this->tableName, [
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Update an existing pricing model
     * 
     * @param int $id The pricing model ID
     * @param array $data The pricing model data to update
     * @return bool Whether the update was successful
     */
    public function update($id, $data) 
    {
        $this->validateData([
            'name' => 'string',
            'type' => 'in:cpm,cpc,time_based,position_based,mixed',
            'description' => 'string',
            'is_active' => 'boolean'
        ], $data);
        
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['type'])) {
            $updateData['type'] = $data['type'];
        }
        
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool)$data['is_active'] ? 1 : 0;
        }
        
        return $this->db->update(
            $this->tableName,
            $updateData,
            ['id' => $id]
        );
    }
    
    /**
     * Delete a pricing model
     * 
     * @param int $id The pricing model ID
     * @return bool Whether the deletion was successful
     */
    public function delete($id) 
    {
        // Check if the pricing model is in use
        $inUse = $this->db->queryOne(
            "SELECT 1 FROM advertisements WHERE pricing_model_id = ? LIMIT 1",
            [$id]
        );
        
        if ($inUse) {
            // Don't delete, just deactivate
            return $this->update($id, ['is_active' => 0]);
        }
        
        return $this->db->delete($this->tableName, ['id' => $id]);
    }
    
    /**
     * Get pricing models by type
     * 
     * @param string $type The pricing model type
     * @param bool $activeOnly Whether to get only active models
     * @return array List of pricing models of the specified type
     */
    public function getByType($type, $activeOnly = true) 
    {
        $query = "SELECT * FROM {$this->tableName} WHERE type = ?";
        
        if ($activeOnly) {
            $query .= " AND is_active = 1";
        }
        
        $query .= " ORDER BY name ASC";
        
        return $this->db->query($query, [$type]);
    }
    
    /**
     * Get pricing model types as an associative array
     * 
     * @return array Pricing model types
     */
    public function getTypes() 
    {
        return [
            self::TYPE_CPM => 'Cost Per Thousand Impressions (CPM)',
            self::TYPE_CPC => 'Cost Per Click (CPC)',
            self::TYPE_TIME_BASED => 'Time-Based Pricing',
            self::TYPE_POSITION => 'Position-Based Pricing',
            self::TYPE_MIXED => 'Mixed Pricing Model'
        ];
    }
} 