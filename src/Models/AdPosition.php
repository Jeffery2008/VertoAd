<?php

namespace Models;

class AdPosition extends BaseModel {
    protected $table = 'ad_positions';
    
    protected $fillable = [
        'name',
        'description',
        'width',
        'height',
        'placement_type',
        'price_per_impression',
        'price_per_click',
        'rotation_interval',
        'max_ads',
        'status'
    ];

    // Validation rules
    protected $rules = [
        'name' => 'required|string|max:100',
        'description' => 'nullable|string',
        'width' => 'required|integer|min:1',
        'height' => 'required|integer|min:1',
        'placement_type' => 'required|in:sidebar,banner,popup,inline,floating',
        'price_per_impression' => 'required|numeric|min:0',
        'price_per_click' => 'required|numeric|min:0',
        'rotation_interval' => 'integer|min:1000|default:5000',
        'max_ads' => 'integer|min:1|default:1',
        'status' => 'required|in:active,inactive'
    ];

    public function getActiveAds() {
        $now = date('Y-m-d');
        return $this->db->query(
            "SELECT a.* FROM advertisements a 
            WHERE a.position_id = ? 
            AND a.status = 'active'
            AND a.start_date <= ?
            AND a.end_date >= ?
            AND a.remaining_budget > 0
            ORDER BY a.priority DESC, a.created_at ASC",
            [$this->id, $now, $now]
        );
    }

    public function getAdsByPriority($limit = null) {
        $sql = "SELECT a.* FROM advertisements a 
                WHERE a.position_id = ? 
                AND a.status = 'active'
                AND a.remaining_budget > 0
                ORDER BY a.priority DESC, RAND()";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        return $this->db->query($sql, [$this->id]);
    }

    public function getRevenueStats($startDate, $endDate) {
        return $this->db->query(
            "SELECT 
                DATE(s.created_at) as date,
                SUM(s.impressions) as total_impressions,
                SUM(s.clicks) as total_clicks,
                SUM(s.spent_amount) as total_revenue,
                AVG(s.bounce_rate) as avg_bounce_rate,
                AVG(s.avg_view_time) as avg_view_time
            FROM ad_statistics s
            JOIN advertisements a ON s.ad_id = a.id
            WHERE a.position_id = ?
            AND DATE(s.created_at) BETWEEN ? AND ?
            GROUP BY DATE(s.created_at)
            ORDER BY date ASC",
            [$this->id, $startDate, $endDate]
        );
    }

    public function getGeographicStats($startDate, $endDate) {
        return $this->db->query(
            "SELECT 
                g.country,
                g.region,
                g.city,
                SUM(g.impressions) as total_impressions,
                SUM(g.clicks) as total_clicks,
                SUM(g.conversions) as total_conversions
            FROM geographic_stats g
            JOIN advertisements a ON g.ad_id = a.id
            WHERE a.position_id = ?
            AND DATE(g.created_at) BETWEEN ? AND ?
            GROUP BY g.country, g.region, g.city
            ORDER BY total_impressions DESC",
            [$this->id, $startDate, $endDate]
        );
    }

    public function getDeviceStats($startDate, $endDate) {
        return $this->db->query(
            "SELECT 
                d.device_type,
                d.browser,
                d.os,
                d.resolution,
                SUM(d.impressions) as total_impressions,
                SUM(d.clicks) as total_clicks,
                SUM(d.conversions) as total_conversions
            FROM device_stats d
            JOIN advertisements a ON d.ad_id = a.id
            WHERE a.position_id = ?
            AND DATE(d.created_at) BETWEEN ? AND ?
            GROUP BY d.device_type, d.browser, d.os, d.resolution
            ORDER BY total_impressions DESC",
            [$this->id, $startDate, $endDate]
        );
    }

    public function getCompetitionMetrics($days = 30) {
        $startDate = date('Y-m-d', strtotime("-$days days"));
        return $this->db->query(
            "SELECT 
                COUNT(DISTINCT a.advertiser_id) as total_advertisers,
                COUNT(a.id) as total_ads,
                AVG(a.total_budget) as avg_budget,
                MAX(a.total_budget) as max_budget,
                MIN(a.total_budget) as min_budget,
                AVG(s.spent_amount) as avg_daily_spend,
                MAX(s.spent_amount) as max_daily_spend
            FROM advertisements a
            LEFT JOIN ad_statistics s ON a.id = s.ad_id
            WHERE a.position_id = ?
            AND (
                (a.status = 'active' AND a.start_date >= ?)
                OR 
                DATE(s.created_at) >= ?
            )",
            [$this->id, $startDate, $startDate]
        );
    }

    public function updatePricing($data) {
        $this->validateData([
            'price_per_impression' => 'required|numeric|min:0',
            'price_per_click' => 'required|numeric|min:0'
        ], $data);

        return $this->update([
            'price_per_impression' => $data['price_per_impression'],
            'price_per_click' => $data['price_per_click']
        ]);
    }

    protected function validatePlacementType($type) {
        $allowedTypes = ['sidebar', 'banner', 'popup', 'inline', 'floating'];
        if (!in_array($type, $allowedTypes)) {
            throw new \InvalidArgumentException('Invalid placement type');
        }
        return true;
    }

    public function getAvailableSlots($date = null) {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $totalSlots = $this->max_ads;
        $usedSlots = $this->db->query(
            "SELECT COUNT(*) as count
            FROM advertisements
            WHERE position_id = ?
            AND status = 'active'
            AND ? BETWEEN start_date AND end_date",
            [$this->id, $date]
        )->first()->count;

        return max(0, $totalSlots - $usedSlots);
    }

    public function checkAvailability($startDate, $endDate) {
        $current = strtotime($startDate);
        $end = strtotime($endDate);
        $available = true;
        $dates = [];

        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $slots = $this->getAvailableSlots($date);
            if ($slots <= 0) {
                $available = false;
                $dates[] = $date;
            }
            $current = strtotime('+1 day', $current);
        }

        return [
            'available' => $available,
            'unavailable_dates' => $dates,
            'next_available' => $available ? $startDate : $this->getNextAvailableDate($endDate)
        ];
    }

    protected function getNextAvailableDate($afterDate) {
        $result = $this->db->query(
            "SELECT MIN(end_date + INTERVAL 1 DAY) as next_date
            FROM advertisements
            WHERE position_id = ?
            AND status = 'active'
            AND end_date >= ?",
            [$this->id, $afterDate]
        )->first();

        return $result->next_date ?: $afterDate;
    }
}
