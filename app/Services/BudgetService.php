<?php

namespace App\Services;

use App\Utils\RedisService;
use App\Models\Ad; // Assuming Ad model handles DB interactions
use Predis\Client as PredisClient;

class BudgetService
{
    private RedisService $redisService;
    private ?PredisClient $redisClient;
    private Ad $adModel; // For database fallback/updates

    // Define Redis key prefixes for budget tracking
    private const REDIS_BUDGET_PREFIX = 'ad_budget:';
    private const REDIS_DAILY_SPEND_PREFIX = 'ad_daily_spend:';

    public function __construct(RedisService $redisService, Ad $adModel)
    {
        $this->redisService = $redisService;
        $this->redisClient = $this->redisService->getClient(); // Get client (might be null)
        $this->adModel = $adModel;
    }

    /**
     * Initializes or updates the budget for an ad in Redis if enabled.
     *
     * @param int $adId
     * @param float $totalBudget Total budget amount.
     * @param float|null $dailyBudget Optional daily budget amount.
     */
    public function initializeBudget(int $adId, float $totalBudget, ?float $dailyBudget = null): void
    {
        if (!$this->redisClient) {
            // Redis disabled or connection failed, rely on DB
            return;
        }

        $totalBudgetKey = self::REDIS_BUDGET_PREFIX . $adId;
        $dailySpendKey = self::REDIS_DAILY_SPEND_PREFIX . $adId . ':' . date('Y-m-d');

        try {
            $pipe = $this->redisClient->pipeline();

            // Set/update total budget (potentially just store the limit here)
            // Or store remaining budget? For atomic checks, storing *spent* amount might be better.
            // Let's store the *limit* for now for simplicity.
            $pipe->set($totalBudgetKey, $totalBudget);

            // Set expiry for daily spend key (expire at midnight UTC + small buffer)
            $endOfDay = strtotime('tomorrow midnight UTC') - time();
             // Initialize daily spend if not set, and set expiry
            $pipe->setnx($dailySpendKey, 0);
            $pipe->expire($dailySpendKey, $endOfDay > 0 ? $endOfDay : 60); // Min 60s expiry
            
             // TODO: Set/update daily budget limit in Redis (e.g., another key or hash)
            // For now, we assume the check logic will fetch the daily limit from DB if needed.

            $pipe->execute();
        } catch (\Exception $e) {
            error_log("BudgetService Error: Failed to initialize/update budget in Redis for Ad ID {$adId}. Error: " . $e->getMessage());
            // Optionally, disable redis for this instance? Or just log.
        }
    }

    /**
     * Checks if serving an ad is within budget limits.
     *
     * @param int $adId
     * @param float $cost Cost of the action (e.g., view/click).
     * @return bool True if within budget, false otherwise.
     */
    public function canAfford(int $adId, float $cost): bool
    {
        if (!$this->redisClient) {
            // --- Fallback to Database Check --- 
            return $this->canAffordDb($adId, $cost);
        }

        // --- Redis Check --- 
        $totalBudgetKey = self::REDIS_BUDGET_PREFIX . $adId;
        $dailySpendKey = self::REDIS_DAILY_SPEND_PREFIX . $adId . ':' . date('Y-m-d');

        try {
            // 1. Get Budget Limits (Total from Redis, Daily from DB/Model for now)
            $totalBudgetLimit = (float)$this->redisClient->get($totalBudgetKey);
            // TODO: Fetch daily budget limit efficiently (maybe cache in Redis too?)
            $adDetails = $this->adModel->getById($adId); // Fetch details
            if (!$adDetails) return false; // Ad doesn't exist
            $dailyBudgetLimit = $adDetails['daily_budget']; // Assumes daily_budget column exists
            $currentRemainingDbBudget = $adDetails['remaining_budget']; // Current DB state

            // 2. Check Total Budget (using DB value as the source of truth for remaining)
             if ($currentRemainingDbBudget < $cost) {
                 error_log("Budget Check (Redis Path): Ad {$adId} cannot afford {$cost}. DB Remaining: {$currentRemainingDbBudget}");
                 // Consider marking ad as out of budget here? (e.g., update status)
                 return false;
             }

            // 3. Check Daily Budget (if applicable)
            if ($dailyBudgetLimit !== null && $dailyBudgetLimit > 0) {
                $currentDailySpend = (float)$this->redisClient->get($dailySpendKey);
                if (($currentDailySpend + $cost) > $dailyBudgetLimit) {
                     error_log("Budget Check (Redis Path): Ad {$adId} daily limit exceeded. Limit: {$dailyBudgetLimit}, Current Spend: {$currentDailySpend}, Cost: {$cost}");
                    return false; // Exceeds daily limit
                }
            }

            // If all checks pass
            return true;

        } catch (\Exception $e) {
            error_log("BudgetService Error: Failed to check budget in Redis for Ad ID {$adId}. Falling back to DB check. Error: " . $e->getMessage());
            // Fallback to DB check on Redis error
            return $this->canAffordDb($adId, $cost);
        }
    }

    /**
     * Records the spending for an ad impression/click.
     *
     * @param int $adId
     * @param float $cost Cost of the action.
     * @return bool True on success, false on failure (e.g., Redis error if enabled).
     */
    public function recordSpend(int $adId, float $cost): bool
    {
         // Always update the database as the primary source of truth
         $dbSuccess = $this->adModel->decrementBudget($adId, $cost);

         if (!$dbSuccess) {
              error_log("BudgetService Error: Failed to decrement budget in DB for Ad ID {$adId}.");
              // If DB update fails, we probably shouldn't update Redis either
             return false;
         }
         
        // If Redis is enabled, also update the daily spend counter
        if ($this->redisClient) {
            $dailySpendKey = self::REDIS_DAILY_SPEND_PREFIX . $adId . ':' . date('Y-m-d');
            try {
                // Increment daily spend. Use HINCRBYFLOAT if available & needed, otherwise INCRBY + handling decimals.
                 // Using INCR for simplicity now, assumes cost is integer cents or similar if precision is vital.
                 // For float costs, storing as integer cents is safer for INCR.
                 $costInCents = (int)($cost * 100);
                 $newDailySpend = $this->redisClient->incrby($dailySpendKey, $costInCents);
                 
                 // We might need this? Set expiry again in case key expired between check and record?
                 $endOfDay = strtotime('tomorrow midnight UTC') - time();
                 $this->redisClient->expire($dailySpendKey, $endOfDay > 0 ? $endOfDay : 60); 

                 // Optional: Check if spend now exceeds daily limit *after* incrementing
                 // $adDetails = $this->adModel->getById($adId); // Re-fetch needed?
                 // $dailyBudgetLimit = $adDetails['daily_budget'];
                 // if ($dailyBudgetLimit !== null && ($newDailySpend / 100) > $dailyBudgetLimit) {
                 //     // Log warning or potentially trigger alert/status change
                 //     error_log("BudgetService Warning: Ad {$adId} daily spend exceeded limit *after* recording spend.");
                 // }

                return true; // Indicate Redis update was attempted
            } catch (\Exception $e) {
                error_log("BudgetService Error: Failed to record spend in Redis for Ad ID {$adId}. DB was updated. Error: " . $e->getMessage());
                // DB was updated, but Redis failed. Log it, but don't return false unless critical.
                 return true; // Or false if Redis consistency is vital
            }
        }

        return true; // DB update succeeded, Redis not enabled/connected.
    }


    // --- Fallback Database Logic --- 

    /**
     * Fallback budget check using only the database.
     *
     * @param int $adId
     * @param float $cost
     * @return bool
     */
    private function canAffordDb(int $adId, float $cost): bool
    {
         error_log("Budget Check (DB Fallback): Checking budget for Ad {$adId}, Cost: {$cost}");
        try {
            $adDetails = $this->adModel->getById($adId);
            if (!$adDetails) {
                return false; // Ad not found
            }

            // Check total remaining budget
            if ($adDetails['remaining_budget'] < $cost) {
                 error_log("Budget Check (DB Fallback): Ad {$adId} total budget insufficient. Remaining: {$adDetails['remaining_budget']}");
                return false;
            }

            // Check daily budget (Requires fetching daily spend from DB, potentially slow)
            // We need a way to track daily spend in the DB if Redis is off.
            // This might involve a separate table or more complex queries.
            // For now, we SKIP the daily check in the pure DB fallback for simplicity.
            // TODO: Implement DB-based daily spend tracking if Redis is disabled.
            $dailyBudgetLimit = $adDetails['daily_budget'] ?? null;
            if ($dailyBudgetLimit !== null) {
                 error_log("Budget Check (DB Fallback): Daily limit check skipped for Ad {$adId} when Redis is disabled.");
                // Implement DB daily spend check here if needed.
            }

            return true;
        } catch (\Exception $e) {
             error_log("BudgetService Error: Failed during DB budget check for Ad ID {$adId}. Error: " . $e->getMessage());
             return false; // Fail safe
        }
    }
} 