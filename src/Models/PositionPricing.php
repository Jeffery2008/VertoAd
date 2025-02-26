<?php
namespace VertoAD\Core\Models;

use PDO;

/**
 * PositionPricing - Model for ad position pricing
 */
class PositionPricing extends BaseModel
{
    /**
     * @var string $tableName The database table name
     */
    protected $tableName = 'position_pricing';
    
    /**
     * Get pricing for a specific position
     * 
     * @param int $positionId The ad position ID
     * @param bool $activeOnly Whether to get only active pricing
     * @return array Position pricing data
     */
    public function getByPosition($positionId, $activeOnly = true) 
    {
        $query = "
            SELECT pp.*, pm.name as model_name, pm.type as model_type, ap.name as position_name 
            FROM {$this->tableName} pp
            JOIN pricing_models pm ON pp.pricing_model_id = pm.id
            JOIN ad_positions ap ON pp.position_id = ap.id
            WHERE pp.position_id = ?
        ";
        
        if ($activeOnly) {
            $query .= " AND pp.is_active = 1 AND pm.is_active = 1";
        }
        
        return $this->db->query($query, [$positionId]);
    }
    
    /**
     * Get pricing for a specific position and model
     * 
     * @param int $positionId The ad position ID
     * @param int $modelId The pricing model ID
     * @return array|null Position pricing data or null if not found
     */
    public function getByPositionAndModel($positionId, $modelId) 
    {
        return $this->db->queryOne("
            SELECT * FROM {$this->tableName} 
            WHERE position_id = ? AND pricing_model_id = ?
        ", [$positionId, $modelId]);
    }
    
    /**
     * Create a new position pricing
     * 
     * @param array $data The position pricing data
     * @return int|false The ID of the new position pricing or false if creation failed
     */
    public function create($data) 
    {
        $this->validateData([
            'position_id' => 'required|integer',
            'pricing_model_id' => 'required|integer',
            'base_price' => 'required|numeric|min:0',
            'min_bid' => 'numeric|min:0'
        ], $data);
        
        // Check if the position and pricing model exist
        $positionExists = $this->db->queryOne(
            "SELECT 1 FROM ad_positions WHERE id = ?", 
            [$data['position_id']]
        );
        
        $modelExists = $this->db->queryOne(
            "SELECT 1 FROM pricing_models WHERE id = ?", 
            [$data['pricing_model_id']]
        );
        
        if (!$positionExists || !$modelExists) {
            throw new \InvalidArgumentException('Position or pricing model does not exist');
        }
        
        // Check if a pricing already exists for this position and model
        $existingPricing = $this->getByPositionAndModel(
            $data['position_id'], 
            $data['pricing_model_id']
        );
        
        if ($existingPricing) {
            // Update the existing pricing instead
            return $this->update($existingPricing['id'], $data);
        }
        
        return $this->db->insert($this->tableName, [
            'position_id' => $data['position_id'],
            'pricing_model_id' => $data['pricing_model_id'],
            'base_price' => $data['base_price'],
            'min_bid' => $data['min_bid'] ?? 0,
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Update an existing position pricing
     * 
     * @param int $id The position pricing ID
     * @param array $data The position pricing data to update
     * @return bool Whether the update was successful
     */
    public function update($id, $data) 
    {
        $this->validateData([
            'position_id' => 'integer',
            'pricing_model_id' => 'integer',
            'base_price' => 'numeric|min:0',
            'min_bid' => 'numeric|min:0',
            'is_active' => 'boolean'
        ], $data);
        
        $updateData = [];
        
        if (isset($data['position_id'])) {
            $updateData['position_id'] = $data['position_id'];
        }
        
        if (isset($data['pricing_model_id'])) {
            $updateData['pricing_model_id'] = $data['pricing_model_id'];
        }
        
        if (isset($data['base_price'])) {
            $updateData['base_price'] = $data['base_price'];
        }
        
        if (isset($data['min_bid'])) {
            $updateData['min_bid'] = $data['min_bid'];
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
     * Delete a position pricing
     * 
     * @param int $id The position pricing ID
     * @return bool Whether the deletion was successful
     */
    public function delete($id) 
    {
        return $this->db->delete($this->tableName, ['id' => $id]);
    }
    
    /**
     * Get all position pricing with position and model details
     * 
     * @param bool $activeOnly Whether to get only active pricing
     * @return array List of position pricing
     */
    public function getAllWithDetails($activeOnly = true) 
    {
        $query = "
            SELECT 
                pp.*, 
                pm.name as model_name, 
                pm.type as model_type, 
                ap.name as position_name,
                ap.width,
                ap.height,
                ap.format
            FROM {$this->tableName} pp
            JOIN pricing_models pm ON pp.pricing_model_id = pm.id
            JOIN ad_positions ap ON pp.position_id = ap.id
        ";
        
        if ($activeOnly) {
            $query .= " WHERE pp.is_active = 1 AND pm.is_active = 1";
        }
        
        $query .= " ORDER BY ap.name, pm.name";
        
        return $this->db->query($query);
    }
    
    /**
     * Calculate the effective price for an ad based on its position, pricing model, and time factors
     * 
     * @param array $ad Ad data
     * @param string $pricingType Type of pricing (impression or click)
     * @return float The calculated price
     */
    public function calculateEffectivePrice($ad, $pricingType = 'impression') 
    {
        $positionId = $ad['position_id'];
        $pricingModelId = $ad['pricing_model_id'] ?? null;
        
        // If no pricing model is specified, get the default one
        if (!$pricingModelId) {
            $pricingModel = $this->db->queryOne("
                SELECT id FROM pricing_models 
                WHERE type = ? AND is_active = 1 
                ORDER BY id ASC LIMIT 1
            ", [$pricingType === 'impression' ? 'cpm' : 'cpc']);
            
            $pricingModelId = $pricingModel['id'] ?? null;
        }
        
        // Get the base price for this position and model
        $positionPricing = $this->getByPositionAndModel($positionId, $pricingModelId);
        
        if (!$positionPricing) {
            // No pricing found, use the bid amount directly
            return $pricingType === 'impression' ? 
                ($ad['bid_amount'] / 1000) : // Convert CPM to cost per impression
                $ad['bid_amount'];
        }
        
        $basePrice = $positionPricing['base_price'];
        
        // Apply time-based pricing adjustments if applicable
        $timeMultiplier = $this->getTimeMultiplier($positionId);
        
        // Apply position multiplier (if this is a premium position)
        $positionMultiplier = $ad['price_multiplier'] ?? 1.0;
        
        // Calculate the effective price
        $effectivePrice = $basePrice * $timeMultiplier * $positionMultiplier;
        
        // For impression pricing, convert from CPM to cost per impression
        if ($pricingType === 'impression') {
            $effectivePrice /= 1000;
        }
        
        return $effectivePrice;
    }
    
    /**
     * Get the time-based pricing multiplier for a position
     * 
     * @param int $positionId The ad position ID
     * @return float The time multiplier (default 1.0)
     */
    private function getTimeMultiplier($positionId) 
    {
        $now = time();
        $dayOfWeek = date('w', $now); // 0 (Sunday) to 6 (Saturday)
        $hour = date('G', $now); // 0 to 23
        
        $rule = $this->db->queryOne("
            SELECT multiplier 
            FROM time_pricing_rules 
            WHERE position_id = ? 
            AND day_of_week = ? 
            AND start_hour <= ? 
            AND end_hour > ? 
            AND is_active = 1
        ", [$positionId, $dayOfWeek, $hour, $hour]);
        
        return $rule ? $rule['multiplier'] : 1.0;
    }
} 