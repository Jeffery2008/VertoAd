<?php

class AdService {
    private $db;
    private $validator;
    private $logger;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->validator = new Validator();
        $this->logger = new Logger();
    }

    /**
     * Get ad by ID
     */
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT a.*, p.name as position_name, p.width, p.height 
            FROM advertisements a
            JOIN ad_positions p ON a.position_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Search ads with filters and pagination
     */
    public function search($filters = [], $page = 1, $limit = 20) {
        $where = [];
        $params = [];
        
        foreach ($filters as $key => $value) {
            $where[] = "$key = ?";
            $params[] = $value;
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page - 1) * $limit;

        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM advertisements $whereClause
        ");
        $countStmt->execute($params);
        $total = $countStmt->fetchColumn();

        // Get paginated results
        $stmt = $this->db->prepare("
            SELECT a.*, p.name as position_name, p.width, p.height
            FROM advertisements a
            JOIN ad_positions p ON a.position_id = p.id
            $whereClause
            ORDER BY a.priority DESC, a.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Create new advertisement
     */
    public function create($data) {
        // Validate required fields
        $required = ['advertiser_id', 'position_id', 'title', 'content', 'start_date', 'end_date', 'total_budget'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Validate dates
        if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
            throw new Exception("End date must be after start date");
        }

        // Validate position exists
        $position = $this->db->prepare("SELECT * FROM ad_positions WHERE id = ?");
        $position->execute([$data['position_id']]);
        if (!$position->fetch()) {
            throw new Exception("Invalid position ID");
        }

        // Validate advertiser exists and has sufficient balance
        $advertiser = $this->db->prepare("SELECT * FROM advertisers WHERE id = ?");
        $advertiser->execute([$data['advertiser_id']]);
        $advertiserData = $advertiser->fetch();
        if (!$advertiserData) {
            throw new Exception("Invalid advertiser ID");
        }
        if ($advertiserData['balance'] < $data['total_budget']) {
            throw new Exception("Insufficient balance");
        }

        // Store original content
        $data['original_content'] = $data['content'];
        
        // Set default status
        $data['status'] = 'pending';
        $data['remaining_budget'] = $data['total_budget'];

        // Insert ad
        $stmt = $this->db->prepare("
            INSERT INTO advertisements (
                advertiser_id, position_id, title, content, original_content,
                start_date, end_date, status, total_budget, remaining_budget
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['advertiser_id'],
            $data['position_id'],
            $data['title'],
            json_encode($data['content']),
            json_encode($data['original_content']),
            $data['start_date'],
            $data['end_date'],
            $data['status'],
            $data['total_budget'],
            $data['remaining_budget']
        ]);

        return $this->getById($this->db->lastInsertId());
    }

    /**
     * Update advertisement
     */
    public function update($id, $data) {
        // Get existing ad
        $ad = $this->getById($id);
        if (!$ad) {
            throw new Exception("Advertisement not found");
        }

        // Build update fields
        $updates = [];
        $params = [];

        if (isset($data['title'])) {
            $updates[] = "title = ?";
            $params[] = $data['title'];
        }
        if (isset($data['content'])) {
            $updates[] = "content = ?";
            $params[] = json_encode($data['content']);
        }
        if (isset($data['start_date'])) {
            if (isset($data['end_date'])) {
                if (strtotime($data['start_date']) > strtotime($data['end_date'])) {
                    throw new Exception("End date must be after start date");
                }
            } elseif (strtotime($data['start_date']) > strtotime($ad['end_date'])) {
                throw new Exception("End date must be after start date");
            }
            $updates[] = "start_date = ?";
            $params[] = $data['start_date'];
        }
        if (isset($data['end_date'])) {
            if (strtotime($ad['start_date']) > strtotime($data['end_date'])) {
                throw new Exception("End date must be after start date");
            }
            $updates[] = "end_date = ?";
            $params[] = $data['end_date'];
        }
        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
        if (isset($data['priority'])) {
            $updates[] = "priority = ?";
            $params[] = $data['priority'];
        }

        if (empty($updates)) {
            return $ad;
        }

        // Update ad
        $params[] = $id;
        $stmt = $this->db->prepare("
            UPDATE advertisements 
            SET " . implode(", ", $updates) . "
            WHERE id = ?
        ");
        $stmt->execute($params);

        return $this->getById($id);
    }

    /**
     * Delete advertisement
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM advertisements WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Get ads for a specific position
     */
    public function getAdsForPosition($positionId, $ipAddress = null) {
        // Get position details
        $position = $this->db->prepare("SELECT * FROM ad_positions WHERE id = ?");
        $position->execute([$positionId]);
        $positionData = $position->fetch();
        if (!$positionData) {
            throw new Exception("Invalid position ID");
        }

        // Get geographic data if IP provided
        $geoData = null;
        if ($ipAddress) {
            $geoData = $this->getGeographicData($ipAddress);
        }

        // Get active ads for position
        $stmt = $this->db->prepare("
            SELECT a.*, p.name as position_name, p.width, p.height
            FROM advertisements a
            JOIN ad_positions p ON a.position_id = p.id
            WHERE a.position_id = ?
            AND a.status = 'active'
            AND a.start_date <= CURRENT_DATE
            AND a.end_date >= CURRENT_DATE
            AND a.remaining_budget > 0
            ORDER BY a.priority DESC, RAND()
            LIMIT ?
        ");
        $stmt->execute([$positionId, $positionData['max_ads']]);
        $ads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter ads by targeting rules if geo data available
        if ($geoData && count($ads) > 0) {
            $ads = array_filter($ads, function($ad) use ($geoData) {
                return $this->matchesTargeting($ad, $geoData);
            });
        }

        return $ads;
    }

    /**
     * Get geographic data from IP address
     */
    private function getGeographicData($ip) {
        $url = "https://whois.pconline.com.cn/ipJson.jsp?ip=$ip&json=true";
        $response = file_get_contents($url);
        if ($response === false) {
            return null;
        }
        return json_decode($response, true);
    }

    /**
     * Check if ad matches targeting rules
     */
    private function matchesTargeting($ad, $geoData) {
        // Get targeting rules
        $stmt = $this->db->prepare("
            SELECT * FROM targeting_rules 
            WHERE ad_id = ? AND status = 'active'
        ");
        $stmt->execute([$ad['id']]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rules as $rule) {
            $conditions = json_decode($rule['conditions'], true);
            
            switch ($rule['type']) {
                case 'geo':
                    if (!$this->matchesGeoTargeting($conditions, $geoData)) {
                        return false;
                    }
                    break;
                    
                case 'time':
                    if (!$this->matchesTimeTargeting($conditions)) {
                        return false;
                    }
                    break;
                    
                // Add other targeting types as needed
            }
        }

        return true;
    }

    /**
     * Check if ad matches geographic targeting
     */
    private function matchesGeoTargeting($conditions, $geoData) {
        if (isset($conditions['pro']) && $conditions['pro'] != $geoData['pro']) {
            return false;
        }
        if (isset($conditions['city']) && $conditions['city'] != $geoData['city']) {
            return false;
        }
        return true;
    }

    /**
     * Check if current time matches time targeting
     */
    private function matchesTimeTargeting($conditions) {
        $currentHour = (int)date('H');
        $currentDay = strtolower(date('l'));

        if (isset($conditions['hours']) && !in_array($currentHour, $conditions['hours'])) {
            return false;
        }
        if (isset($conditions['days']) && !in_array($currentDay, $conditions['days'])) {
            return false;
        }
        return true;
    }

    /**
     * Record impression for an ad
     */
    public function recordImpression($adId, $ipAddress, $userAgent) {
        // Start transaction
        $this->db->beginTransaction();

        try {
            // Update daily statistics
            $stmt = $this->db->prepare("
                INSERT INTO ad_statistics (ad_id, date, impressions)
                VALUES (?, CURRENT_DATE, 1)
                ON DUPLICATE KEY UPDATE impressions = impressions + 1
            ");
            $stmt->execute([$adId]);

            // Record geographic data
            if ($ipAddress) {
                $geoData = $this->getGeographicData($ipAddress);
                if ($geoData) {
                    $stmt = $this->db->prepare("
                        INSERT INTO geographic_stats (
                            ad_id, country, region, city, impressions
                        ) VALUES (?, 'CN', ?, ?, 1)
                        ON DUPLICATE KEY UPDATE impressions = impressions + 1
                    ");
                    $stmt->execute([
                        $adId,
                        $geoData['pro'],
                        $geoData['city']
                    ]);
                }
            }

            // Record device data
            $deviceData = get_browser($userAgent, true);
            if ($deviceData) {
                $stmt = $this->db->prepare("
                    INSERT INTO device_stats (
                        ad_id, device_type, browser, os, resolution, impressions
                    ) VALUES (?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE impressions = impressions + 1
                ");
                $stmt->execute([
                    $adId,
                    $deviceData['device_type'],
                    $deviceData['browser'],
                    $deviceData['platform'],
                    $deviceData['resolution']
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to record impression", [
                'ad_id' => $adId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Record click for an ad
     */
    public function recordClick($adId, $ipAddress, $userAgent) {
        // Similar to recordImpression but for clicks
        $this->db->beginTransaction();

        try {
            // Update daily statistics
            $stmt = $this->db->prepare("
                INSERT INTO ad_statistics (ad_id, date, clicks)
                VALUES (?, CURRENT_DATE, 1)
                ON DUPLICATE KEY UPDATE clicks = clicks + 1
            ");
            $stmt->execute([$adId]);

            // Record geographic data
            if ($ipAddress) {
                $geoData = $this->getGeographicData($ipAddress);
                if ($geoData) {
                    $stmt = $this->db->prepare("
                        INSERT INTO geographic_stats (
                            ad_id, country, region, city, clicks
                        ) VALUES (?, 'CN', ?, ?, 1)
                        ON DUPLICATE KEY UPDATE clicks = clicks + 1
                    ");
                    $stmt->execute([
                        $adId,
                        $geoData['pro'],
                        $geoData['city']
                    ]);
                }
            }

            // Record device data
            $deviceData = get_browser($userAgent, true);
            if ($deviceData) {
                $stmt = $this->db->prepare("
                    INSERT INTO device_stats (
                        ad_id, device_type, browser, os, resolution, clicks
                    ) VALUES (?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE clicks = clicks + 1
                ");
                $stmt->execute([
                    $adId,
                    $deviceData['device_type'],
                    $deviceData['browser'],
                    $deviceData['platform'],
                    $deviceData['resolution']
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error("Failed to record click", [
                'ad_id' => $adId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Record performance metrics
     */
    public function recordMetric($adId, $metricType, $value, $sessionId, $additionalData = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO performance_metrics (
                    ad_id,
                    metric_type,
                    value,
                    additional_data,
                    session_id
                ) VALUES (?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $adId,
                $metricType,
                $value,
                $additionalData ? json_encode($additionalData) : null,
                $sessionId
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to record metric", [
                'ad_id' => $adId,
                'metric_type' => $metricType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
