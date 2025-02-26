<?php
namespace VertoAD\Core\Models;

/**
 * PricingPlan - Model for pricing plans and discounts
 */
class PricingPlan extends BaseModel
{
    /**
     * @var string $tableName The database table name
     */
    protected $tableName = 'pricing_plans';
    
    /**
     * Get all pricing plans
     * 
     * @param bool $activeOnly Whether to get only active plans
     * @return array Pricing plans
     */
    public function getAll($activeOnly = true) 
    {
        $query = "SELECT * FROM {$this->tableName}";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY name";
        
        return $this->db->query($query);
    }
    
    /**
     * Get a pricing plan by ID
     * 
     * @param int $id The plan ID
     * @return array|null Plan data or null if not found
     */
    public function getById($id) 
    {
        return $this->db->queryOne("SELECT * FROM {$this->tableName} WHERE id = ?", [$id]);
    }
    
    /**
     * Create a new pricing plan
     * 
     * @param array $data The plan data
     * @return int|false The ID of the new plan or false if creation failed
     */
    public function create($data) 
    {
        $this->validateData([
            'name' => 'required|string|max:100',
            'description' => 'string|max:500'
        ], $data);
        
        // Check if the plan name already exists
        $planExists = $this->db->queryOne(
            "SELECT 1 FROM {$this->tableName} WHERE name = ?", 
            [$data['name']]
        );
        
        if ($planExists) {
            throw new \InvalidArgumentException('A plan with this name already exists');
        }
        
        return $this->db->insert($this->tableName, [
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Update an existing pricing plan
     * 
     * @param int $id The plan ID
     * @param array $data The plan data to update
     * @return bool Whether the update was successful
     */
    public function update($id, $data) 
    {
        $this->validateData([
            'name' => 'string|max:100',
            'description' => 'string|max:500',
            'is_active' => 'boolean'
        ], $data);
        
        // Check if the plan name already exists (excluding this plan)
        if (isset($data['name'])) {
            $planExists = $this->db->queryOne(
                "SELECT 1 FROM {$this->tableName} WHERE name = ? AND id != ?", 
                [$data['name'], $id]
            );
            
            if ($planExists) {
                throw new \InvalidArgumentException('A plan with this name already exists');
            }
        }
        
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
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
     * Delete a pricing plan if not in use
     * 
     * @param int $id The plan ID
     * @return bool Whether the deletion was successful
     */
    public function delete($id) 
    {
        // Check if the plan is being used in rules
        $rulesExist = $this->db->queryOne(
            "SELECT 1 FROM pricing_plan_rules WHERE plan_id = ?", 
            [$id]
        );
        
        if ($rulesExist) {
            // Plan is in use, just deactivate it
            return $this->update($id, ['is_active' => 0]);
        }
        
        return $this->db->delete($this->tableName, ['id' => $id]);
    }
    
    /**
     * Get all rules for a specific pricing plan
     * 
     * @param int $planId The pricing plan ID
     * @return array Pricing plan rules
     */
    public function getPlanRules($planId) 
    {
        return $this->db->query("
            SELECT 
                ppr.*,
                pp.name AS position_name,
                pm.name AS model_name,
                pm.type AS model_type
            FROM pricing_plan_rules ppr
            JOIN ad_positions pp ON ppr.position_id = pp.id
            JOIN pricing_models pm ON ppr.pricing_model_id = pm.id
            WHERE ppr.plan_id = ?
            ORDER BY pp.name, pm.name
        ", [$planId]);
    }
    
    /**
     * Add a rule to a pricing plan
     * 
     * @param array $data The rule data
     * @return int|false The ID of the new rule or false if creation failed
     */
    public function addRule($data) 
    {
        $this->validateData([
            'plan_id' => 'required|integer',
            'position_id' => 'required|integer',
            'pricing_model_id' => 'required|integer',
            'discount_percentage' => 'required|numeric|min:0|max:100'
        ], $data);
        
        // Check if the plan, position, and model exist
        $planExists = $this->db->queryOne(
            "SELECT 1 FROM {$this->tableName} WHERE id = ?", 
            [$data['plan_id']]
        );
        
        $positionExists = $this->db->queryOne(
            "SELECT 1 FROM ad_positions WHERE id = ?", 
            [$data['position_id']]
        );
        
        $modelExists = $this->db->queryOne(
            "SELECT 1 FROM pricing_models WHERE id = ?", 
            [$data['pricing_model_id']]
        );
        
        if (!$planExists || !$positionExists || !$modelExists) {
            throw new \InvalidArgumentException('Plan, position, or pricing model does not exist');
        }
        
        // Check if this rule already exists
        $ruleExists = $this->db->queryOne("
            SELECT id FROM pricing_plan_rules 
            WHERE plan_id = ? AND position_id = ? AND pricing_model_id = ?
        ", [
            $data['plan_id'],
            $data['position_id'],
            $data['pricing_model_id']
        ]);
        
        if ($ruleExists) {
            // Update the existing rule
            return $this->updateRule($ruleExists['id'], [
                'discount_percentage' => $data['discount_percentage']
            ]);
        }
        
        return $this->db->insert('pricing_plan_rules', [
            'plan_id' => $data['plan_id'],
            'position_id' => $data['position_id'],
            'pricing_model_id' => $data['pricing_model_id'],
            'discount_percentage' => $data['discount_percentage']
        ]);
    }
    
    /**
     * Update an existing pricing plan rule
     * 
     * @param int $ruleId The rule ID
     * @param array $data The rule data to update
     * @return bool Whether the update was successful
     */
    public function updateRule($ruleId, $data) 
    {
        $this->validateData([
            'discount_percentage' => 'numeric|min:0|max:100'
        ], $data);
        
        $updateData = [];
        
        if (isset($data['discount_percentage'])) {
            $updateData['discount_percentage'] = $data['discount_percentage'];
        }
        
        return $this->db->update(
            'pricing_plan_rules',
            $updateData,
            ['id' => $ruleId]
        );
    }
    
    /**
     * Delete a pricing plan rule
     * 
     * @param int $ruleId The rule ID
     * @return bool Whether the deletion was successful
     */
    public function deleteRule($ruleId) 
    {
        return $this->db->delete('pricing_plan_rules', ['id' => $ruleId]);
    }
    
    /**
     * Get applicable discount for a position and pricing model
     * 
     * @param int $planId The plan ID
     * @param int $positionId The position ID
     * @param int $pricingModelId The pricing model ID
     * @return float|null The discount percentage or null if no discount applies
     */
    public function getDiscount($planId, $positionId, $pricingModelId) 
    {
        // Check if a specific rule exists for this combination
        $rule = $this->db->queryOne("
            SELECT discount_percentage 
            FROM pricing_plan_rules 
            WHERE plan_id = ? AND position_id = ? AND pricing_model_id = ?
        ", [$planId, $positionId, $pricingModelId]);
        
        if ($rule) {
            return $rule['discount_percentage'];
        }
        
        // Check if a generic rule exists for this position (any model)
        $rule = $this->db->queryOne("
            SELECT discount_percentage 
            FROM pricing_plan_rules 
            WHERE plan_id = ? AND position_id = ? AND pricing_model_id = 0
        ", [$planId, $positionId]);
        
        if ($rule) {
            return $rule['discount_percentage'];
        }
        
        // Check if a generic rule exists for this model (any position)
        $rule = $this->db->queryOne("
            SELECT discount_percentage 
            FROM pricing_plan_rules 
            WHERE plan_id = ? AND position_id = 0 AND pricing_model_id = ?
        ", [$planId, $pricingModelId]);
        
        return $rule ? $rule['discount_percentage'] : null;
    }
    
    /**
     * Apply discount to a price based on the plan
     * 
     * @param float $price The base price
     * @param int $planId The plan ID
     * @param int $positionId The position ID
     * @param int $pricingModelId The pricing model ID
     * @return float The discounted price
     */
    public function applyDiscount($price, $planId, $positionId, $pricingModelId) 
    {
        $discount = $this->getDiscount($planId, $positionId, $pricingModelId);
        
        if ($discount === null) {
            // No discount applies
            return $price;
        }
        
        // Apply the discount
        $discountedPrice = $price * (1 - ($discount / 100));
        
        // Ensure the price doesn't go below zero
        return max(0, $discountedPrice);
    }
    
    /**
     * Assign a pricing plan to a user
     * 
     * @param int $userId The user ID
     * @param int $planId The plan ID
     * @return bool Whether the assignment was successful
     */
    public function assignToUser($userId, $planId) 
    {
        // Check if the user and plan exist
        $userExists = $this->db->queryOne(
            "SELECT 1 FROM users WHERE id = ?", 
            [$userId]
        );
        
        $planExists = $this->db->queryOne(
            "SELECT 1 FROM {$this->tableName} WHERE id = ?", 
            [$planId]
        );
        
        if (!$userExists || !$planExists) {
            throw new \InvalidArgumentException('User or pricing plan does not exist');
        }
        
        // Update the user's pricing plan
        return $this->db->update(
            'users',
            ['pricing_plan_id' => $planId],
            ['id' => $userId]
        );
    }
    
    /**
     * Get a user's current pricing plan
     * 
     * @param int $userId The user ID
     * @return array|null The pricing plan or null if none assigned
     */
    public function getUserPlan($userId) 
    {
        $planId = $this->db->queryOne(
            "SELECT pricing_plan_id FROM users WHERE id = ?", 
            [$userId]
        );
        
        if (!$planId || !$planId['pricing_plan_id']) {
            return null;
        }
        
        return $this->getById($planId['pricing_plan_id']);
    }
} 