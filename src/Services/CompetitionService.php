<?php

class CompetitionService {
    private $db;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
    }

    /**
     * Get ad position competition metrics
     */
    public function getPositionCompetition($positionId, $startDate = null, $endDate = null) {
        $params = [$positionId];
        
        $sql = "
            SELECT 
                ap.id,
                ap.name,
                COUNT(DISTINCT o.user_id) as total_advertisers,
                COUNT(DISTINCT o.id) as total_orders,
                AVG(o.total_amount) as avg_order_amount,
                MAX(o.total_amount) as max_order_amount,
                MIN(o.total_amount) as min_order_amount,
                SUM(oi.end_date > NOW()) as active_campaigns
            FROM ad_positions ap
            LEFT JOIN order_items oi ON ap.id = oi.ad_position_id
            LEFT JOIN orders o ON oi.order_id = o.id
            WHERE ap.id = ?
        ";

        if ($startDate) {
            $sql .= " AND o.created_at >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND o.created_at <= ?";
            $params[] = $endDate;
        }

        $sql .= " GROUP BY ap.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get hourly demand distribution
        $sql = "
            SELECT 
                HOUR(o.created_at) as hour,
                COUNT(*) as orders_count
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.ad_position_id = ?
            GROUP BY HOUR(o.created_at)
            ORDER BY hour
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$positionId]);
        $metrics['hourly_demand'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return $metrics;
    }

    /**
     * Get industry deployment analysis
     */
    public function getIndustryAnalysis($startDate = null, $endDate = null) {
        $params = [];
        
        $sql = "
            SELECT 
                u.industry,
                COUNT(DISTINCT u.id) as total_advertisers,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_amount) as total_spend,
                AVG(o.total_amount) as avg_order_amount,
                COUNT(DISTINCT oi.ad_position_id) as unique_positions
            FROM users u
            JOIN orders o ON u.id = o.user_id
            JOIN order_items oi ON o.id = oi.order_id
            WHERE u.industry IS NOT NULL
        ";

        if ($startDate) {
            $sql .= " AND o.created_at >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND o.created_at <= ?";
            $params[] = $endDate;
        }

        $sql .= " GROUP BY u.industry ORDER BY total_spend DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get industry position preferences
        foreach ($analysis as &$industry) {
            $sql = "
                SELECT 
                    ap.name as position_name,
                    COUNT(*) as usage_count
                FROM users u
                JOIN orders o ON u.id = o.user_id
                JOIN order_items oi ON o.id = oi.order_id
                JOIN ad_positions ap ON oi.ad_position_id = ap.id
                WHERE u.industry = ?
                GROUP BY ap.id
                ORDER BY usage_count DESC
                LIMIT 5
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$industry['industry']]);
            $industry['top_positions'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }

        return $analysis;
    }

    /**
     * Get price trend analysis
     */
    public function getPriceTrends($positionId = null, $period = 'daily', $startDate = null, $endDate = null) {
        $params = [];
        $groupBy = '';
        
        switch ($period) {
            case 'hourly':
                $groupBy = "DATE_FORMAT(o.created_at, '%Y-%m-%d %H:00:00')";
                break;
            case 'daily':
                $groupBy = "DATE(o.created_at)";
                break;
            case 'weekly':
                $groupBy = "YEARWEEK(o.created_at)";
                break;
            case 'monthly':
                $groupBy = "DATE_FORMAT(o.created_at, '%Y-%m-01')";
                break;
            default:
                throw new Exception('Invalid period');
        }
        
        $sql = "
            SELECT 
                {$groupBy} as period,
                AVG(oi.price) as avg_price,
                MIN(oi.price) as min_price,
                MAX(oi.price) as max_price,
                COUNT(*) as orders_count
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
        ";

        if ($positionId) {
            $sql .= " WHERE oi.ad_position_id = ?";
            $params[] = $positionId;
        }

        if ($startDate) {
            $sql .= $positionId ? " AND" : " WHERE";
            $sql .= " o.created_at >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $sql .= $positionId || $startDate ? " AND" : " WHERE";
            $sql .= " o.created_at <= ?";
            $params[] = $endDate;
        }

        $sql .= " GROUP BY {$groupBy} ORDER BY period ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate price volatility
        if (count($trends) > 1) {
            $prices = array_column($trends, 'avg_price');
            $volatility = $this->calculateVolatility($prices);
            
            foreach ($trends as &$trend) {
                $trend['volatility'] = $volatility;
            }
        }

        return $trends;
    }

    /**
     * Get competitive insights for a specific position
     */
    public function getCompetitiveInsights($positionId) {
        // Get position details
        $stmt = $this->db->prepare("
            SELECT * FROM ad_positions WHERE id = ?
        ");
        $stmt->execute([$positionId]);
        $position = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$position) {
            throw new Exception('Position not found');
        }

        // Get current competition level
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT o.user_id) as active_advertisers,
                COUNT(*) as active_campaigns,
                AVG(o.total_amount) as avg_campaign_value
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.ad_position_id = ?
            AND oi.end_date > NOW()
            AND o.status = 'active'
        ");
        $stmt->execute([$positionId]);
        $competition = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get historical fill rate
        $stmt = $this->db->prepare("
            SELECT 
                MONTH(day) as month,
                YEAR(day) as year,
                AVG(fill_rate) as avg_fill_rate
            FROM position_fill_rates
            WHERE position_id = ?
            GROUP BY YEAR(day), MONTH(day)
            ORDER BY year DESC, month DESC
            LIMIT 12
        ");
        $stmt->execute([$positionId]);
        $fillRates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get peak demand times
        $stmt = $this->db->prepare("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as demand_count
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.ad_position_id = ?
            GROUP BY HOUR(created_at)
            ORDER BY demand_count DESC
        ");
        $stmt->execute([$positionId]);
        $peakTimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'position' => $position,
            'competition' => $competition,
            'fill_rates' => $fillRates,
            'peak_times' => $peakTimes,
            'recommendations' => $this->generateRecommendations($position, $competition, $fillRates)
        ];
    }

    /**
     * Helper: Generate pricing and strategy recommendations
     */
    private function generateRecommendations($position, $competition, $fillRates) {
        $recommendations = [];

        // Calculate average fill rate
        $avgFillRate = array_sum(array_column($fillRates, 'avg_fill_rate')) / count($fillRates);

        // Price recommendations
        if ($avgFillRate > 0.9 && $competition['active_advertisers'] > 5) {
            $recommendations[] = [
                'type' => 'price',
                'action' => 'increase',
                'reason' => 'High demand and competition',
                'suggestion' => 'Consider increasing base price by 10-15%'
            ];
        } elseif ($avgFillRate < 0.6) {
            $recommendations[] = [
                'type' => 'price',
                'action' => 'decrease',
                'reason' => 'Low fill rate',
                'suggestion' => 'Consider promotional pricing or volume discounts'
            ];
        }

        // Strategy recommendations
        if ($competition['active_advertisers'] > 10) {
            $recommendations[] = [
                'type' => 'strategy',
                'action' => 'segmentation',
                'reason' => 'High competition',
                'suggestion' => 'Implement industry-specific pricing tiers'
            ];
        }

        if ($avgFillRate < 0.8) {
            $recommendations[] = [
                'type' => 'strategy',
                'action' => 'packaging',
                'reason' => 'Room for growth',
                'suggestion' => 'Create bundled offerings with complementary positions'
            ];
        }

        return $recommendations;
    }

    /**
     * Helper: Calculate price volatility
     */
    private function calculateVolatility($prices) {
        $mean = array_sum($prices) / count($prices);
        $variance = array_reduce($prices, function($carry, $price) use ($mean) {
            return $carry + pow($price - $mean, 2);
        }, 0) / count($prices);
        
        return sqrt($variance) / $mean; // Coefficient of variation
    }
}
