<?php

namespace Models;

class Advertisement extends BaseModel {
    protected $table = 'advertisements';
    
    protected $fillable = [
        'advertiser_id',
        'position_id',
        'title',
        'content',
        'original_content',
        'start_date',
        'end_date',
        'status',
        'priority',
        'total_budget',
        'remaining_budget'
    ];

    protected $rules = [
        'advertiser_id' => 'required|integer|exists:advertisers,id',
        'position_id' => 'required|integer|exists:ad_positions,id',
        'title' => 'required|string|max:255',
        'content' => 'required|string',  // JSON format
        'original_content' => 'required|string',  // For version history
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'status' => 'required|in:pending,active,paused,rejected,completed',
        'priority' => 'integer|min:0|max:100|default:50',
        'total_budget' => 'required|numeric|min:0',
        'remaining_budget' => 'required|numeric|min:0'
    ];

    // Check if ad is currently active
    public function isActive() {
        if ($this->status !== 'active') return false;
        
        $now = date('Y-m-d');
        return $this->start_date <= $now && 
               $this->end_date >= $now && 
               $this->remaining_budget > 0;
    }

    // Record impression
    public function recordImpression($data) {
        $this->validateImpressionData($data);
        
        // Calculate cost
        $position = $this->getPosition();
        $cost = $position->price_per_impression;
        
        // Update remaining budget
        if ($this->remaining_budget < $cost) {
            $this->update(['status' => 'completed']);
            return false;
        }
        
        $this->db->beginTransaction();
        try {
            // Update advertisement budget
            $this->update([
                'remaining_budget' => $this->remaining_budget - $cost
            ]);
            
            // Record statistics
            $this->recordStatistics($data, $cost);
            
            // Record geographic data
            if (isset($data['location'])) {
                $this->recordGeographicData($data['location']);
            }
            
            // Record device data
            if (isset($data['device'])) {
                $this->recordDeviceData($data['device']);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Record click
    public function recordClick($data) {
        $this->validateClickData($data);
        
        // Calculate cost
        $position = $this->getPosition();
        $cost = $position->price_per_click;
        
        // Update remaining budget
        if ($this->remaining_budget < $cost) {
            $this->update(['status' => 'completed']);
            return false;
        }
        
        $this->db->beginTransaction();
        try {
            // Update advertisement budget
            $this->update([
                'remaining_budget' => $this->remaining_budget - $cost
            ]);
            
            // Update statistics
            $this->db->query(
                "UPDATE ad_statistics 
                SET clicks = clicks + 1,
                    spent_amount = spent_amount + ?
                WHERE ad_id = ? AND date = CURRENT_DATE",
                [$cost, $this->id]
            );
            
            // Update geographic stats if location data provided
            if (isset($data['location'])) {
                $this->db->query(
                    "UPDATE geographic_stats 
                    SET clicks = clicks + 1
                    WHERE ad_id = ? 
                    AND country = ? 
                    AND region = ? 
                    AND city = ?",
                    [
                        $this->id,
                        $data['location']['country'],
                        $data['location']['region'],
                        $data['location']['city']
                    ]
                );
            }
            
            // Update device stats if device data provided
            if (isset($data['device'])) {
                $this->db->query(
                    "UPDATE device_stats 
                    SET clicks = clicks + 1
                    WHERE ad_id = ? 
                    AND device_type = ? 
                    AND browser = ? 
                    AND os = ? 
                    AND resolution = ?",
                    [
                        $this->id,
                        $data['device']['type'],
                        $data['device']['browser'],
                        $data['device']['os'],
                        $data['device']['resolution']
                    ]
                );
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Record conversion
    public function recordConversion($data) {
        $this->validateConversionData($data);
        
        $this->db->beginTransaction();
        try {
            // Update statistics
            $this->db->query(
                "UPDATE ad_statistics 
                SET conversions = conversions + 1
                WHERE ad_id = ? AND date = CURRENT_DATE",
                [$this->id]
            );
            
            // Update geographic stats if location data provided
            if (isset($data['location'])) {
                $this->db->query(
                    "UPDATE geographic_stats 
                    SET conversions = conversions + 1
                    WHERE ad_id = ? 
                    AND country = ? 
                    AND region = ? 
                    AND city = ?",
                    [
                        $this->id,
                        $data['location']['country'],
                        $data['location']['region'],
                        $data['location']['city']
                    ]
                );
            }
            
            // Update device stats if device data provided
            if (isset($data['device'])) {
                $this->db->query(
                    "UPDATE device_stats 
                    SET conversions = conversions + 1
                    WHERE ad_id = ? 
                    AND device_type = ? 
                    AND browser = ? 
                    AND os = ? 
                    AND resolution = ?",
                    [
                        $this->id,
                        $data['device']['type'],
                        $data['device']['browser'],
                        $data['device']['os'],
                        $data['device']['resolution']
                    ]
                );
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Get performance statistics
    public function getStats($startDate = null, $endDate = null) {
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }

        return $this->db->query(
            "SELECT 
                SUM(impressions) as total_impressions,
                SUM(clicks) as total_clicks,
                SUM(conversions) as total_conversions,
                SUM(spent_amount) as total_spent,
                AVG(bounce_rate) as avg_bounce_rate,
                AVG(avg_view_time) as avg_view_time,
                CASE 
                    WHEN SUM(impressions) > 0 THEN 
                        (SUM(clicks) * 100.0 / SUM(impressions))
                    ELSE 0 
                END as ctr,
                CASE 
                    WHEN SUM(clicks) > 0 THEN 
                        (SUM(conversions) * 100.0 / SUM(clicks))
                    ELSE 0 
                END as conversion_rate
            FROM ad_statistics
            WHERE ad_id = ?
            AND date BETWEEN ? AND ?",
            [$this->id, $startDate, $endDate]
        )->first();
    }

    // Get daily performance
    public function getDailyStats($startDate = null, $endDate = null) {
        if (!$startDate) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        if (!$endDate) {
            $endDate = date('Y-m-d');
        }

        return $this->db->query(
            "SELECT 
                date,
                impressions,
                clicks,
                conversions,
                spent_amount,
                bounce_rate,
                avg_view_time,
                CASE 
                    WHEN impressions > 0 THEN 
                        (clicks * 100.0 / impressions)
                    ELSE 0 
                END as ctr,
                CASE 
                    WHEN clicks > 0 THEN 
                        (conversions * 100.0 / clicks)
                    ELSE 0 
                END as conversion_rate
            FROM ad_statistics
            WHERE ad_id = ?
            AND date BETWEEN ? AND ?
            ORDER BY date ASC",
            [$this->id, $startDate, $endDate]
        );
    }

    // Get geographic performance
    public function getGeographicStats() {
        return $this->db->query(
            "SELECT 
                country,
                region,
                city,
                impressions,
                clicks,
                conversions,
                CASE 
                    WHEN impressions > 0 THEN 
                        (clicks * 100.0 / impressions)
                    ELSE 0 
                END as ctr,
                CASE 
                    WHEN clicks > 0 THEN 
                        (conversions * 100.0 / clicks)
                    ELSE 0 
                END as conversion_rate
            FROM geographic_stats
            WHERE ad_id = ?
            ORDER BY impressions DESC",
            [$this->id]
        );
    }

    // Get device performance
    public function getDeviceStats() {
        return $this->db->query(
            "SELECT 
                device_type,
                browser,
                os,
                resolution,
                impressions,
                clicks,
                conversions,
                CASE 
                    WHEN impressions > 0 THEN 
                        (clicks * 100.0 / impressions)
                    ELSE 0 
                END as ctr,
                CASE 
                    WHEN clicks > 0 THEN 
                        (conversions * 100.0 / clicks)
                    ELSE 0 
                END as conversion_rate
            FROM device_stats
            WHERE ad_id = ?
            ORDER BY impressions DESC",
            [$this->id]
        );
    }

    // Get position
    public function getPosition() {
        return new AdPosition($this->position_id);
    }

    // Get advertiser
    public function getAdvertiser() {
        return new Advertiser($this->advertiser_id);
    }

    // Protected methods for data validation and recording
    protected function validateImpressionData($data) {
        $rules = [
            'view_time' => 'nullable|integer|min:0',
            'bounce' => 'nullable|boolean',
            'location' => 'nullable|array',
            'location.country' => 'required_with:location|string|size:2',
            'location.region' => 'required_with:location|string',
            'location.city' => 'required_with:location|string',
            'device' => 'nullable|array',
            'device.type' => 'required_with:device|string',
            'device.browser' => 'required_with:device|string',
            'device.os' => 'required_with:device|string',
            'device.resolution' => 'required_with:device|string'
        ];
        
        $this->validateData($rules, $data);
    }

    protected function validateClickData($data) {
        $rules = [
            'location' => 'nullable|array',
            'location.country' => 'required_with:location|string|size:2',
            'location.region' => 'required_with:location|string',
            'location.city' => 'required_with:location|string',
            'device' => 'nullable|array',
            'device.type' => 'required_with:device|string',
            'device.browser' => 'required_with:device|string',
            'device.os' => 'required_with:device|string',
            'device.resolution' => 'required_with:device|string'
        ];
        
        $this->validateData($rules, $data);
    }

    protected function validateConversionData($data) {
        $rules = [
            'type' => 'required|string',
            'value' => 'nullable|numeric',
            'location' => 'nullable|array',
            'location.country' => 'required_with:location|string|size:2',
            'location.region' => 'required_with:location|string',
            'location.city' => 'required_with:location|string',
            'device' => 'nullable|array',
            'device.type' => 'required_with:device|string',
            'device.browser' => 'required_with:device|string',
            'device.os' => 'required_with:device|string',
            'device.resolution' => 'required_with:device|string'
        ];
        
        $this->validateData($rules, $data);
    }

    protected function recordStatistics($data, $cost) {
        // Update or create daily statistics
        $stats = $this->db->query(
            "INSERT INTO ad_statistics 
            (ad_id, date, impressions, spent_amount) 
            VALUES (?, CURRENT_DATE, 1, ?)
            ON DUPLICATE KEY UPDATE 
                impressions = impressions + 1,
                spent_amount = spent_amount + ?",
            [$this->id, $cost, $cost]
        );
        
        // Update view time and bounce rate if provided
        if (isset($data['view_time']) || isset($data['bounce'])) {
            $updates = [];
            $params = [];
            
            if (isset($data['view_time'])) {
                $updates[] = "avg_view_time = ((avg_view_time * impressions) + ?) / (impressions + 1)";
                $params[] = $data['view_time'];
            }
            
            if (isset($data['bounce'])) {
                $updates[] = "bounce_rate = ((bounce_rate * impressions) + ?) / (impressions + 1)";
                $params[] = $data['bounce'] ? 100 : 0;
            }
            
            if (!empty($updates)) {
                $sql = "UPDATE ad_statistics SET " . implode(", ", $updates) . 
                       " WHERE ad_id = ? AND date = CURRENT_DATE";
                $params[] = $this->id;
                
                $this->db->query($sql, $params);
            }
        }
    }

    protected function recordGeographicData($location) {
        $this->db->query(
            "INSERT INTO geographic_stats 
            (ad_id, country, region, city, impressions) 
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                impressions = impressions + 1",
            [
                $this->id,
                $location['country'],
                $location['region'],
                $location['city']
            ]
        );
    }

    protected function recordDeviceData($device) {
        $this->db->query(
            "INSERT INTO device_stats 
            (ad_id, device_type, browser, os, resolution, impressions)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE 
                impressions = impressions + 1",
            [
                $this->id,
                $device['type'],
                $device['browser'],
                $device['os'],
                $device['resolution']
            ]
        );
    }
}
