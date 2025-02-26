<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Models\Advertisement;
use VertoAD\Core\Models\AdPosition;
use VertoAD\Core\Utils\Logger;

class CompetitionService {
    private $logger;
    private $adModel;
    private $positionModel;
    
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->adModel = new Advertisement();
        $this->positionModel = new AdPosition();
    }
    
    /**
     * Run auction to select a winning ad
     * 
     * @param array $eligibleAds Array of eligible ads
     * @param array $targeting Targeting data
     * @return array|null Winning ad or null if no winner
     */
    public function runAuction(array $eligibleAds, array $targeting) {
        if (empty($eligibleAds)) {
            return null;
        }
        
        try {
            // Calculate scores for each ad
            $scoredAds = array_map(function($ad) use ($targeting) {
                return [
                    'ad' => $ad,
                    'score' => $this->calculateAdScore($ad, $targeting),
                    'bid' => $this->calculateEffectiveBid($ad)
                ];
            }, $eligibleAds);
            
            // Sort by total score (bid * quality score)
            usort($scoredAds, function($a, $b) {
                $scoreA = $a['score'] * $a['bid'];
                $scoreB = $b['score'] * $b['bid'];
                return $scoreB <=> $scoreA;
            });
            
            // Get winner and calculate actual cost
            if (!empty($scoredAds)) {
                $winner = $scoredAds[0]['ad'];
                $winner['cost'] = $this->calculateWinningCost(
                    $scoredAds[0],
                    $scoredAds[1] ?? null
                );
                
                $this->logger->info('Auction completed', [
                    'winner_id' => $winner['id'],
                    'score' => $scoredAds[0]['score'],
                    'bid' => $scoredAds[0]['bid'],
                    'cost' => $winner['cost']
                ]);
                
                return $winner;
            }
            
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Auction error', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Calculate ad quality score
     */
    private function calculateAdScore($ad, $targeting) {
        $score = 1.0;
        
        // Historical CTR impact (0-50% boost)
        if ($ad['impressions'] > 0) {
            $ctr = ($ad['clicks'] / $ad['impressions']);
            $score *= (0.5 + min($ctr * 50, 0.5));
        }
        
        // Relevance scoring
        $score *= $this->calculateRelevanceScore($ad, $targeting);
        
        // Performance scoring
        $score *= $this->calculatePerformanceScore($ad);
        
        // Budget pacing
        $score *= $this->calculatePacingScore($ad);
        
        return max(0.1, min($score, 1.0));
    }
    
    /**
     * Calculate content relevance score
     */
    private function calculateRelevanceScore($ad, $targeting) {
        $score = 1.0;
        
        // Device targeting
        if (isset($ad['device_targeting'], $targeting['device_type'])) {
            $score *= in_array($targeting['device_type'], $ad['device_targeting']) ? 1.0 : 0.2;
        }
        
        // Geographic targeting
        if (isset($ad['geo_targeting'], $targeting['geo'])) {
            $score *= $this->calculateGeoScore($ad['geo_targeting'], $targeting['geo']);
        }
        
        // Time targeting
        $score *= $this->calculateTimeScore($ad);
        
        return $score;
    }
    
    /**
     * Calculate performance score based on historical data
     */
    private function calculatePerformanceScore($ad) {
        $score = 1.0;
        
        // Viewability rate impact
        if ($ad['impressions'] > 0) {
            $viewability = $ad['viewable_impressions'] / $ad['impressions'];
            $score *= (0.7 + min($viewability * 0.6, 0.3));
        }
        
        // Conversion rate impact
        if ($ad['clicks'] > 0) {
            $cvr = $ad['conversions'] / $ad['clicks'];
            $score *= (0.8 + min($cvr * 4, 0.2));
        }
        
        return $score;
    }
    
    /**
     * Calculate budget pacing score
     */
    private function calculatePacingScore($ad) {
        if (!isset($ad['daily_budget'], $ad['daily_spend'])) {
            return 1.0;
        }
        
        $budget = $ad['daily_budget'];
        $spent = $ad['daily_spend'];
        
        // If over budget, severely reduce score
        if ($spent >= $budget) {
            return 0.1;
        }
        
        // Calculate ideal spend at current time
        $dayProgress = (time() - strtotime('today')) / 86400;
        $idealSpend = $budget * $dayProgress;
        
        // If behind pace, increase score to catch up
        if ($spent < $idealSpend * 0.8) {
            return 1.2;
        }
        
        // If ahead of pace, reduce score to slow down
        if ($spent > $idealSpend * 1.2) {
            return 0.8;
        }
        
        return 1.0;
    }
    
    /**
     * Calculate geographic targeting score
     */
    private function calculateGeoScore($targeting, $actual) {
        if (empty($targeting)) {
            return 1.0;
        }
        
        // Country match
        if (isset($targeting['country'])) {
            if ($targeting['country'] !== $actual['country']) {
                return 0.2;
            }
            
            // Region match
            if (isset($targeting['region'])) {
                if ($targeting['region'] !== $actual['region']) {
                    return 0.6;
                }
                
                // City match
                if (isset($targeting['city'])) {
                    return $targeting['city'] === $actual['city'] ? 1.0 : 0.8;
                }
            }
        }
        
        return 1.0;
    }
    
    /**
     * Calculate time relevance score
     */
    private function calculateTimeScore($ad) {
        if (!isset($ad['schedule'])) {
            return 1.0;
        }
        
        $now = time();
        $hour = (int)date('G', $now);
        $day = (int)date('w', $now);
        
        // Check hour targeting
        if (isset($ad['schedule']['hours']) && !in_array($hour, $ad['schedule']['hours'])) {
            return 0.4;
        }
        
        // Check day targeting
        if (isset($ad['schedule']['days']) && !in_array($day, $ad['schedule']['days'])) {
            return 0.4;
        }
        
        return 1.0;
    }
    
    /**
     * Calculate effective bid amount
     */
    private function calculateEffectiveBid($ad) {
        $baseBid = $ad['bid_amount'];
        
        // Adjust for remaining budget
        if (isset($ad['daily_budget'], $ad['daily_spend'])) {
            $remaining = $ad['daily_budget'] - $ad['daily_spend'];
            if ($remaining <= 0) {
                return 0;
            }
            $baseBid = min($baseBid, $remaining);
        }
        
        return $baseBid;
    }
    
    /**
     * Calculate second-price auction cost
     */
    private function calculateWinningCost($winner, $runnerUp = null) {
        if (!$runnerUp) {
            // If no second bidder, charge 60% of bid
            return max(0.01, $winner['bid'] * 0.6);
        }
        
        // Calculate second price with quality score adjustment
        $secondPrice = ($runnerUp['bid'] * $runnerUp['score']) / $winner['score'];
        
        // Ensure cost is between minimum bid and actual bid
        return max(0.01, min($winner['bid'], $secondPrice));
    }
}
