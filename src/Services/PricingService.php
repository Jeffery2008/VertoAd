<?php
namespace HFI\UtilityCenter\Services;

use HFI\UtilityCenter\Models\PositionPricing;
use HFI\UtilityCenter\Models\PricingModel;
use HFI\UtilityCenter\Models\PricingPlan;
use HFI\UtilityCenter\Models\TimePricingRule;
use HFI\UtilityCenter\Models\DiscountCode;
use HFI\UtilityCenter\Models\User;

/**
 * PricingService - Service for handling pricing and billing operations
 */
class PricingService
{
    /**
     * @var PositionPricing $positionPricing
     */
    private $positionPricing;
    
    /**
     * @var PricingModel $pricingModel
     */
    private $pricingModel;
    
    /**
     * @var PricingPlan $pricingPlan
     */
    private $pricingPlan;
    
    /**
     * @var TimePricingRule $timePricingRule
     */
    private $timePricingRule;
    
    /**
     * @var DiscountCode $discountCode
     */
    private $discountCode;
    
    /**
     * @var User $user
     */
    private $user;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->positionPricing = new PositionPricing();
        $this->pricingModel = new PricingModel();
        $this->pricingPlan = new PricingPlan();
        $this->timePricingRule = new TimePricingRule();
        $this->discountCode = new DiscountCode();
        $this->user = new User();
    }
    
    /**
     * Calculate the price for an ad based on position, pricing model, and user
     * 
     * @param array $adData Ad data including position_id, pricing_model_id, etc.
     * @param int $userId The user ID
     * @param string $pricingType Type of pricing (impression or click)
     * @return array Price calculation result
     */
    public function calculateAdPrice($adData, $userId, $pricingType = 'impression')
    {
        // Get user data and pricing plan
        $user = $this->user->getById($userId);
        $userPlan = $this->pricingPlan->getUserPlan($userId);
        
        // Get the base price for this position and model
        $basePrice = $this->positionPricing->calculateEffectivePrice($adData, $pricingType);
        
        $result = [
            'base_price' => $basePrice,
            'time_multiplier' => 1.0,
            'position_multiplier' => $adData['price_multiplier'] ?? 1.0,
            'discount_percentage' => 0,
            'final_price' => $basePrice,
            'price_type' => $pricingType,
            'user_plan' => $userPlan ? $userPlan['name'] : 'Standard'
        ];
        
        // Apply time-based pricing multiplier
        $timeRule = $this->timePricingRule->getCurrentRule($adData['position_id']);
        if ($timeRule) {
            $result['time_multiplier'] = $timeRule['multiplier'];
            $result['final_price'] *= $timeRule['multiplier'];
        }
        
        // Apply pricing plan discount if applicable
        if ($userPlan) {
            $discount = $this->pricingPlan->getDiscount(
                $userPlan['id'], 
                $adData['position_id'], 
                $adData['pricing_model_id'] ?? 0
            );
            
            if ($discount !== null) {
                $result['discount_percentage'] = $discount;
                $result['final_price'] = $this->pricingPlan->applyDiscount(
                    $result['final_price'],
                    $userPlan['id'],
                    $adData['position_id'],
                    $adData['pricing_model_id'] ?? 0
                );
            }
        }
        
        // Round to appropriate precision
        $result['final_price'] = round($result['final_price'], 4);
        
        return $result;
    }
    
    /**
     * Calculate the estimated cost for an ad campaign
     * 
     * @param array $adData Ad data including position_id, pricing_model_id, budget, etc.
     * @param int $userId The user ID
     * @param array $campaignOptions Campaign options (duration, impressions, etc.)
     * @return array Cost estimation details
     */
    public function estimateCampaignCost($adData, $userId, $campaignOptions = [])
    {
        // Default options
        $options = array_merge([
            'estimated_impressions' => 1000,
            'estimated_clicks' => 10,
            'duration_days' => 30,
            'pricing_type' => 'cpm' // cpm, cpc, or mixed
        ], $campaignOptions);
        
        $pricingType = $options['pricing_type'];
        
        // Calculate price per impression or click
        $pricePerImpression = $this->calculateAdPrice($adData, $userId, 'impression')['final_price'];
        $pricePerClick = $this->calculateAdPrice($adData, $userId, 'click')['final_price'];
        
        // Calculate cost based on pricing type
        $estimatedCost = 0;
        
        switch ($pricingType) {
            case 'cpm':
                $estimatedCost = $pricePerImpression * $options['estimated_impressions'];
                break;
                
            case 'cpc':
                $estimatedCost = $pricePerClick * $options['estimated_clicks'];
                break;
                
            case 'mixed':
                $impressionCost = $pricePerImpression * $options['estimated_impressions'];
                $clickCost = $pricePerClick * $options['estimated_clicks'];
                $estimatedCost = $impressionCost + $clickCost;
                break;
        }
        
        // Daily cost estimate
        $dailyCost = $options['duration_days'] > 0 ? 
            $estimatedCost / $options['duration_days'] : 
            $estimatedCost;
        
        return [
            'pricing_type' => $pricingType,
            'price_per_impression' => $pricePerImpression,
            'price_per_click' => $pricePerClick,
            'estimated_impressions' => $options['estimated_impressions'],
            'estimated_clicks' => $options['estimated_clicks'],
            'duration_days' => $options['duration_days'],
            'estimated_total_cost' => $estimatedCost,
            'estimated_daily_cost' => $dailyCost,
            'can_afford' => $this->user->getBalance($userId) >= $estimatedCost
        ];
    }
    
    /**
     * Process a billing transaction for an ad
     * 
     * @param int $userId The user ID
     * @param int $adId The advertisement ID
     * @param string $eventType The event type (impression, click)
     * @param float $amount The amount to charge (if not provided, calculated automatically)
     * @return array Transaction result
     */
    public function processTransaction($userId, $adId, $eventType, $amount = null)
    {
        // Get advertisement data
        $adModel = new \HFI\UtilityCenter\Models\Advertisement();
        $ad = $adModel->getById($adId);
        
        if (!$ad) {
            return [
                'success' => false,
                'message' => 'Advertisement not found'
            ];
        }
        
        // Calculate amount if not provided
        if ($amount === null) {
            $pricingType = $eventType === 'impression' ? 'impression' : 'click';
            $priceCalculation = $this->calculateAdPrice($ad, $userId, $pricingType);
            $amount = $priceCalculation['final_price'];
        }
        
        // Check if user has sufficient balance
        $userBalance = $this->user->getBalance($userId);
        
        if ($userBalance < $amount) {
            return [
                'success' => false,
                'message' => 'Insufficient balance',
                'balance' => $userBalance,
                'required' => $amount
            ];
        }
        
        // Deduct amount from user balance
        $newBalance = $userBalance - $amount;
        $updated = $this->user->updateBalance($userId, $newBalance);
        
        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Failed to update user balance'
            ];
        }
        
        // Log the transaction
        $transactionId = $this->logTransaction($userId, $adId, $eventType, $amount, $newBalance);
        
        // Update ad spend statistics
        $this->updateAdSpendStatistics($adId, $amount, $eventType);
        
        return [
            'success' => true,
            'message' => 'Transaction processed successfully',
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'previous_balance' => $userBalance,
            'new_balance' => $newBalance,
            'event_type' => $eventType
        ];
    }
    
    /**
     * Log a billing transaction
     * 
     * @param int $userId The user ID
     * @param int $adId The advertisement ID
     * @param string $eventType The event type (impression, click)
     * @param float $amount The amount charged
     * @param float $newBalance The new user balance
     * @return int|false The transaction ID or false if logging failed
     */
    private function logTransaction($userId, $adId, $eventType, $amount, $newBalance)
    {
        $db = $this->positionPricing->getDb();
        
        return $db->insert('billing_transactions', [
            'user_id' => $userId,
            'ad_id' => $adId,
            'event_type' => $eventType,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Update ad spend statistics
     * 
     * @param int $adId The advertisement ID
     * @param float $amount The amount spent
     * @param string $eventType The event type (impression, click)
     * @return bool Whether the update was successful
     */
    private function updateAdSpendStatistics($adId, $amount, $eventType)
    {
        $db = $this->positionPricing->getDb();
        $date = date('Y-m-d');
        
        // Check if there's an entry for today
        $dailySpend = $db->queryOne(
            "SELECT * FROM ad_spend_daily WHERE ad_id = ? AND date = ?",
            [$adId, $date]
        );
        
        if ($dailySpend) {
            // Update existing entry
            $updateData = [
                'total_spend' => $dailySpend['total_spend'] + $amount
            ];
            
            if ($eventType === 'impression') {
                $updateData['impression_spend'] = $dailySpend['impression_spend'] + $amount;
            } else if ($eventType === 'click') {
                $updateData['click_spend'] = $dailySpend['click_spend'] + $amount;
            }
            
            return $db->update(
                'ad_spend_daily',
                $updateData,
                ['id' => $dailySpend['id']]
            );
        } else {
            // Create new entry
            $insertData = [
                'ad_id' => $adId,
                'date' => $date,
                'total_spend' => $amount,
                'impression_spend' => $eventType === 'impression' ? $amount : 0,
                'click_spend' => $eventType === 'click' ? $amount : 0
            ];
            
            return $db->insert('ad_spend_daily', $insertData) !== false;
        }
    }
    
    /**
     * Check if an advertisement is within budget constraints
     * 
     * @param int $adId The advertisement ID
     * @return array Budget status check result
     */
    public function checkBudgetStatus($adId)
    {
        $db = $this->positionPricing->getDb();
        $ad = (new \HFI\UtilityCenter\Models\Advertisement())->getById($adId);
        
        if (!$ad) {
            return [
                'valid' => false,
                'message' => 'Advertisement not found'
            ];
        }
        
        $result = [
            'ad_id' => $adId,
            'daily_budget' => $ad['daily_budget'],
            'total_budget' => $ad['total_budget'],
            'within_budget' => true,
            'message' => 'Within budget constraints'
        ];
        
        // Check daily budget
        if ($ad['daily_budget'] > 0) {
            $today = date('Y-m-d');
            $dailySpend = $db->queryOne(
                "SELECT SUM(total_spend) as spent FROM ad_spend_daily WHERE ad_id = ? AND date = ?",
                [$adId, $today]
            );
            
            $todaySpent = $dailySpend['spent'] ?? 0;
            $result['daily_spent'] = $todaySpent;
            $result['daily_remaining'] = $ad['daily_budget'] - $todaySpent;
            
            if ($todaySpent >= $ad['daily_budget']) {
                $result['within_budget'] = false;
                $result['message'] = 'Daily budget exceeded';
                return $result;
            }
        }
        
        // Check total budget
        if ($ad['total_budget'] > 0) {
            $totalSpend = $db->queryOne(
                "SELECT SUM(total_spend) as spent FROM ad_spend_daily WHERE ad_id = ?",
                [$adId]
            );
            
            $overallSpent = $totalSpend['spent'] ?? 0;
            $result['total_spent'] = $overallSpent;
            $result['total_remaining'] = $ad['total_budget'] - $overallSpent;
            
            if ($overallSpent >= $ad['total_budget']) {
                $result['within_budget'] = false;
                $result['message'] = 'Total budget exceeded';
                return $result;
            }
        }
        
        return $result;
    }
    
    /**
     * Apply a discount code to a purchase
     * 
     * @param string $code The discount code
     * @param float $amount The purchase amount
     * @param int $userId The user ID
     * @param string $orderId Optional order ID
     * @return array Result of applying the discount
     */
    public function applyDiscountCode($code, $amount, $userId, $orderId = null)
    {
        $result = $this->discountCode->applyCode($code, $amount, $userId);
        
        if (is_string($result)) {
            // Error message
            return [
                'success' => false,
                'message' => $result
            ];
        }
        
        // Log the usage if an order ID is provided
        if ($orderId && isset($result['discount_code']['id'])) {
            $this->discountCode->logUsage(
                $result['discount_code']['id'],
                $userId,
                $orderId,
                $result['original_amount'],
                $result['final_amount']
            );
        }
        
        return [
            'success' => true,
            'original_amount' => $result['original_amount'],
            'discount_amount' => $result['discount_amount'],
            'final_amount' => $result['final_amount'],
            'discount_code' => $result['discount_code']['code'],
            'discount_type' => $result['discount_code']['discount_type'],
            'discount_value' => $result['discount_code']['discount_value']
        ];
    }
    
    /**
     * Get pricing information for a position
     * 
     * @param int $positionId The position ID
     * @return array Pricing information
     */
    public function getPositionPricing($positionId)
    {
        // Get position details
        $positionModel = new \HFI\UtilityCenter\Models\AdPosition();
        $position = $positionModel->getById($positionId);
        
        if (!$position) {
            return [
                'success' => false,
                'message' => 'Position not found'
            ];
        }
        
        // Get pricing models for this position
        $pricingDetails = $this->positionPricing->getByPosition($positionId);
        
        // Get time-based pricing rules
        $timeRules = $this->timePricingRule->getByPosition($positionId);
        
        // Current effective rule
        $currentRule = $this->timePricingRule->getCurrentRule($positionId);
        
        return [
            'success' => true,
            'position' => $position,
            'pricing_models' => $pricingDetails,
            'time_rules' => $timeRules,
            'current_time_rule' => $currentRule,
            'current_multiplier' => $currentRule ? $currentRule['multiplier'] : 1.0
        ];
    }
    
    /**
     * Process a recharge using an activation key
     * 
     * @param string $key The activation key
     * @param int $userId The user ID
     * @return array Recharge result
     */
    public function processKeyRecharge($key, $userId)
    {
        // This would be tied to the product key system
        $productKeyModel = new \HFI\UtilityCenter\Models\ProductKey();
        $result = $productKeyModel->activateKey($key, $userId);
        
        if (!$result['success']) {
            return $result;
        }
        
        // If successful, return detailed information
        return [
            'success' => true,
            'message' => 'Key activated successfully',
            'amount_added' => $result['amount'],
            'previous_balance' => $result['previous_balance'],
            'new_balance' => $result['new_balance'],
            'key_info' => $result['key_info']
        ];
    }
    
    /**
     * Get transaction history for a user
     * 
     * @param int $userId The user ID
     * @param array $options Query options (limit, offset, etc.)
     * @return array User transaction history
     */
    public function getUserTransactionHistory($userId, $options = [])
    {
        $db = $this->positionPricing->getDb();
        
        // Default options
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;
        $sortBy = $options['sort_by'] ?? 'created_at';
        $sortDir = $options['sort_dir'] ?? 'DESC';
        $filterType = $options['filter_type'] ?? null;
        
        // Base query
        $query = "
            SELECT 
                bt.*,
                a.name as ad_name,
                a.position_id
            FROM billing_transactions bt
            LEFT JOIN advertisements a ON bt.ad_id = a.id
            WHERE bt.user_id = ?
        ";
        
        $params = [$userId];
        
        // Apply type filter if specified
        if ($filterType !== null) {
            $query .= " AND bt.event_type = ?";
            $params[] = $filterType;
        }
        
        // Add sorting and pagination
        $query .= " ORDER BY bt.$sortBy $sortDir LIMIT $limit OFFSET $offset";
        
        // Get transactions
        $transactions = $db->query($query, $params);
        
        // Get total count for pagination
        $countQuery = "
            SELECT COUNT(*) as count FROM billing_transactions 
            WHERE user_id = ?
        ";
        
        $countParams = [$userId];
        
        if ($filterType !== null) {
            $countQuery .= " AND event_type = ?";
            $countParams[] = $filterType;
        }
        
        $totalCount = $db->queryOne($countQuery, $countParams);
        
        return [
            'transactions' => $transactions,
            'total' => $totalCount['count'] ?? 0,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < ($totalCount['count'] ?? 0)
        ];
    }
    
    /**
     * Get spending analysis for a user or advertisement
     * 
     * @param array $options Analysis options
     * @return array Spending analysis
     */
    public function getSpendingAnalysis($options = [])
    {
        $db = $this->positionPricing->getDb();
        
        // Default options
        $userId = $options['user_id'] ?? null;
        $adId = $options['ad_id'] ?? null;
        $startDate = $options['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $options['end_date'] ?? date('Y-m-d');
        $groupBy = $options['group_by'] ?? 'day'; // day, week, month
        
        // Parameters and conditions
        $conditions = [];
        $params = [];
        
        if ($userId !== null) {
            $conditions[] = "user_id = ?";
            $params[] = $userId;
        }
        
        if ($adId !== null) {
            $conditions[] = "ad_id = ?";
            $params[] = $adId;
        }
        
        if ($startDate) {
            $conditions[] = "date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "date <= ?";
            $params[] = $endDate;
        }
        
        // Build the WHERE clause
        $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
        
        // Group by clause based on option
        $groupByClause = "date";
        $dateFormat = "%Y-%m-%d";
        
        if ($groupBy === 'week') {
            $groupByClause = "YEARWEEK(date, 1)";
            $dateFormat = "%Y-%u"; // ISO week number
        } else if ($groupBy === 'month') {
            $groupByClause = "DATE_FORMAT(date, '%Y-%m')";
            $dateFormat = "%Y-%m";
        }
        
        // Query for daily spending
        $query = "
            SELECT 
                DATE_FORMAT(date, '$dateFormat') as period,
                SUM(total_spend) as total,
                SUM(impression_spend) as impression_total,
                SUM(click_spend) as click_total
            FROM ad_spend_daily
            $whereClause
            GROUP BY $groupByClause
            ORDER BY period ASC
        ";
        
        $spending = $db->query($query, $params);
        
        // Get total spending
        $totalQuery = "
            SELECT 
                SUM(total_spend) as total,
                SUM(impression_spend) as impression_total,
                SUM(click_spend) as click_total
            FROM ad_spend_daily
            $whereClause
        ";
        
        $total = $db->queryOne($totalQuery, $params);
        
        return [
            'spending_by_period' => $spending,
            'total_spend' => $total['total'] ?? 0,
            'impression_spend' => $total['impression_total'] ?? 0,
            'click_spend' => $total['click_total'] ?? 0,
            'period_type' => $groupBy,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }
} 