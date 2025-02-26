<?php
namespace HFI\UtilityCenter\Models;

/**
 * DiscountCode - Model for discount codes
 */
class DiscountCode extends BaseModel
{
    /**
     * @var string $tableName The database table name
     */
    protected $tableName = 'discount_codes';
    
    /**
     * Discount type constants
     */
    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';
    
    /**
     * Get all discount codes
     * 
     * @param bool $activeOnly Whether to get only active codes
     * @return array Discount codes
     */
    public function getAll($activeOnly = true) 
    {
        $query = "SELECT * FROM {$this->tableName}";
        
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        
        $query .= " ORDER BY created_at DESC";
        
        return $this->db->query($query);
    }
    
    /**
     * Get a discount code by ID
     * 
     * @param int $id The discount code ID
     * @return array|null Discount code data or null if not found
     */
    public function getById($id) 
    {
        return $this->db->queryOne("SELECT * FROM {$this->tableName} WHERE id = ?", [$id]);
    }
    
    /**
     * Get a discount code by code
     * 
     * @param string $code The discount code
     * @param bool $activeOnly Whether to get only active codes
     * @return array|null Discount code data or null if not found
     */
    public function getByCode($code, $activeOnly = true) 
    {
        $query = "SELECT * FROM {$this->tableName} WHERE code = ?";
        
        if ($activeOnly) {
            $query .= " AND is_active = 1";
        }
        
        return $this->db->queryOne($query, [$code]);
    }
    
    /**
     * Create a new discount code
     * 
     * @param array $data The discount code data
     * @return int|false The ID of the new discount code or false if creation failed
     */
    public function create($data) 
    {
        $this->validateData([
            'code' => 'required|string|max:50',
            'description' => 'string|max:500',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase_amount' => 'numeric|min:0',
            'max_discount_amount' => 'numeric|min:0',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'usage_limit' => 'integer|min:0',
            'created_by_user_id' => 'integer'
        ], $data);
        
        // Additional validation for percentage discount
        if ($data['discount_type'] === self::TYPE_PERCENTAGE && $data['discount_value'] > 100) {
            throw new \InvalidArgumentException('Percentage discount cannot exceed 100%');
        }
        
        // Check if the code already exists
        $codeExists = $this->db->queryOne(
            "SELECT 1 FROM {$this->tableName} WHERE code = ?", 
            [$data['code']]
        );
        
        if ($codeExists) {
            throw new \InvalidArgumentException('A discount code with this code already exists');
        }
        
        // Ensure valid_to is after valid_from
        if (!empty($data['valid_from']) && !empty($data['valid_to'])) {
            $validFrom = strtotime($data['valid_from']);
            $validTo = strtotime($data['valid_to']);
            
            if ($validFrom > $validTo) {
                throw new \InvalidArgumentException('Valid to date must be after valid from date');
            }
        }
        
        return $this->db->insert($this->tableName, [
            'code' => $data['code'],
            'description' => $data['description'] ?? '',
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'min_purchase_amount' => $data['min_purchase_amount'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? 0,
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1
        ]);
    }
    
    /**
     * Update an existing discount code
     * 
     * @param int $id The discount code ID
     * @param array $data The discount code data to update
     * @return bool Whether the update was successful
     */
    public function update($id, $data) 
    {
        $this->validateData([
            'code' => 'string|max:50',
            'description' => 'string|max:500',
            'discount_type' => 'string|in:percentage,fixed',
            'discount_value' => 'numeric|min:0',
            'min_purchase_amount' => 'numeric|min:0',
            'max_discount_amount' => 'numeric|min:0',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'usage_limit' => 'integer|min:0',
            'is_active' => 'boolean'
        ], $data);
        
        // Additional validation for percentage discount
        if (isset($data['discount_type']) && 
            isset($data['discount_value']) && 
            $data['discount_type'] === self::TYPE_PERCENTAGE && 
            $data['discount_value'] > 100) {
            throw new \InvalidArgumentException('Percentage discount cannot exceed 100%');
        }
        
        // Check if the code already exists (excluding this discount code)
        if (isset($data['code'])) {
            $codeExists = $this->db->queryOne(
                "SELECT 1 FROM {$this->tableName} WHERE code = ? AND id != ?", 
                [$data['code'], $id]
            );
            
            if ($codeExists) {
                throw new \InvalidArgumentException('A discount code with this code already exists');
            }
        }
        
        // Get current discount code data
        $currentCode = $this->getById($id);
        if (!$currentCode) {
            throw new \InvalidArgumentException('Discount code not found');
        }
        
        // Ensure valid_to is after valid_from
        $validFrom = isset($data['valid_from']) ? strtotime($data['valid_from']) : 
            (!empty($currentCode['valid_from']) ? strtotime($currentCode['valid_from']) : null);
            
        $validTo = isset($data['valid_to']) ? strtotime($data['valid_to']) : 
            (!empty($currentCode['valid_to']) ? strtotime($currentCode['valid_to']) : null);
        
        if ($validFrom !== null && $validTo !== null && $validFrom > $validTo) {
            throw new \InvalidArgumentException('Valid to date must be after valid from date');
        }
        
        $updateData = [];
        
        if (isset($data['code'])) {
            $updateData['code'] = $data['code'];
        }
        
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        
        if (isset($data['discount_type'])) {
            $updateData['discount_type'] = $data['discount_type'];
        }
        
        if (isset($data['discount_value'])) {
            $updateData['discount_value'] = $data['discount_value'];
        }
        
        if (isset($data['min_purchase_amount'])) {
            $updateData['min_purchase_amount'] = $data['min_purchase_amount'];
        }
        
        if (isset($data['max_discount_amount'])) {
            $updateData['max_discount_amount'] = $data['max_discount_amount'];
        }
        
        if (isset($data['valid_from'])) {
            $updateData['valid_from'] = $data['valid_from'];
        }
        
        if (isset($data['valid_to'])) {
            $updateData['valid_to'] = $data['valid_to'];
        }
        
        if (isset($data['usage_limit'])) {
            $updateData['usage_limit'] = $data['usage_limit'];
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
     * Delete a discount code
     * 
     * @param int $id The discount code ID
     * @return bool Whether the deletion was successful
     */
    public function delete($id) 
    {
        // Check if the code has been used
        $usageExists = $this->db->queryOne(
            "SELECT 1 FROM discount_usage_log WHERE discount_id = ?", 
            [$id]
        );
        
        if ($usageExists) {
            // Code has been used, just deactivate it
            return $this->update($id, ['is_active' => 0]);
        }
        
        return $this->db->delete($this->tableName, ['id' => $id]);
    }
    
    /**
     * Check if a discount code is valid and can be applied
     * 
     * @param string $code The discount code
     * @param float $purchaseAmount The purchase amount
     * @param int $userId The user ID (optional)
     * @return array|string Discount code data if valid, error message if invalid
     */
    public function validateCode($code, $purchaseAmount, $userId = null) 
    {
        // Get the discount code
        $discountCode = $this->getByCode($code);
        
        if (!$discountCode) {
            return 'Invalid discount code';
        }
        
        // Check if the code is active
        if (!$discountCode['is_active']) {
            return 'This discount code is no longer active';
        }
        
        // Check the valid dates
        $now = time();
        
        if (!empty($discountCode['valid_from']) && strtotime($discountCode['valid_from']) > $now) {
            return 'This discount code is not yet valid';
        }
        
        if (!empty($discountCode['valid_to']) && strtotime($discountCode['valid_to']) < $now) {
            return 'This discount code has expired';
        }
        
        // Check the minimum purchase amount
        if ($discountCode['min_purchase_amount'] > 0 && $purchaseAmount < $discountCode['min_purchase_amount']) {
            return "Minimum purchase amount of {$discountCode['min_purchase_amount']} required";
        }
        
        // Check the usage limit
        if ($discountCode['usage_limit'] > 0) {
            $usageCount = $this->db->queryOne(
                "SELECT COUNT(*) as count FROM discount_usage_log WHERE discount_id = ?", 
                [$discountCode['id']]
            );
            
            if ($usageCount['count'] >= $discountCode['usage_limit']) {
                return 'This discount code has reached its usage limit';
            }
        }
        
        // Check if the user has already used this code (if user ID is provided)
        if ($userId) {
            $userUsage = $this->db->queryOne(
                "SELECT 1 FROM discount_usage_log WHERE discount_id = ? AND user_id = ?", 
                [$discountCode['id'], $userId]
            );
            
            if ($userUsage) {
                return 'You have already used this discount code';
            }
        }
        
        // Code is valid
        return $discountCode;
    }
    
    /**
     * Apply a discount code to a purchase amount
     * 
     * @param string $code The discount code
     * @param float $purchaseAmount The purchase amount
     * @param int $userId The user ID (optional)
     * @return array|string Result of applying the discount or error message
     */
    public function applyCode($code, $purchaseAmount, $userId = null) 
    {
        $validation = $this->validateCode($code, $purchaseAmount, $userId);
        
        if (is_string($validation)) {
            // Validation failed, return the error message
            return $validation;
        }
        
        // Calculate the discount
        $discountAmount = 0;
        
        if ($validation['discount_type'] === self::TYPE_PERCENTAGE) {
            $discountAmount = $purchaseAmount * ($validation['discount_value'] / 100);
        } else {
            $discountAmount = $validation['discount_value'];
        }
        
        // Apply maximum discount limit if applicable
        if ($validation['max_discount_amount'] > 0 && $discountAmount > $validation['max_discount_amount']) {
            $discountAmount = $validation['max_discount_amount'];
        }
        
        // Ensure discount doesn't exceed purchase amount
        $discountAmount = min($discountAmount, $purchaseAmount);
        
        // Calculate final amount
        $finalAmount = $purchaseAmount - $discountAmount;
        
        return [
            'discount_code' => $validation,
            'original_amount' => $purchaseAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount
        ];
    }
    
    /**
     * Log the usage of a discount code
     * 
     * @param int $discountId The discount code ID
     * @param int $userId The user ID
     * @param string $orderId The order ID (optional)
     * @param float $originalAmount The original amount
     * @param float $discountedAmount The amount after discount
     * @return int|false The ID of the new log entry or false if creation failed
     */
    public function logUsage($discountId, $userId, $orderId, $originalAmount, $discountedAmount) 
    {
        return $this->db->insert('discount_usage_log', [
            'discount_id' => $discountId,
            'user_id' => $userId,
            'order_id' => $orderId,
            'amount_before' => $originalAmount,
            'amount_after' => $discountedAmount,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Generate a unique discount code
     * 
     * @param int $length The length of the code (default 8)
     * @return string A unique discount code
     */
    public function generateCode($length = 8) 
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            
            // Check if the code already exists
            $codeExists = $this->db->queryOne(
                "SELECT 1 FROM {$this->tableName} WHERE code = ?", 
                [$code]
            );
        } while ($codeExists);
        
        return $code;
    }
    
    /**
     * Get the usage history of a discount code
     * 
     * @param int $discountId The discount code ID
     * @return array Usage history
     */
    public function getUsageHistory($discountId) 
    {
        return $this->db->query("
            SELECT 
                dul.*,
                u.username,
                u.email
            FROM discount_usage_log dul
            LEFT JOIN users u ON dul.user_id = u.id
            WHERE dul.discount_id = ?
            ORDER BY dul.created_at DESC
        ", [$discountId]);
    }
    
    /**
     * Get the available discount types
     * 
     * @return array Discount types
     */
    public function getDiscountTypes() 
    {
        return [
            self::TYPE_PERCENTAGE => 'Percentage (%)',
            self::TYPE_FIXED => 'Fixed Amount'
        ];
    }
} 