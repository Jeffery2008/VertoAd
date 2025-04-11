<?php

namespace App\Models;

use App\Core\Database;

class AdTargetingModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 创建或更新广告定向规则
     */
    public function saveTargeting($adId, $targeting) {
        // Ensure geo keys exist
        $geo = $targeting['geo'] ?? [];
        $countries = $geo['countries'] ?? [];
        $regions = $geo['regions'] ?? [];
        $cities = $geo['cities'] ?? [];

        $sql = "INSERT INTO ad_targeting (
            ad_id, geo_countries, geo_regions, geo_cities, 
            devices, browsers, os, time_schedule, language
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            geo_countries = VALUES(geo_countries),
            geo_regions = VALUES(geo_regions),
            geo_cities = VALUES(geo_cities),
            devices = VALUES(devices),
            browsers = VALUES(browsers),
            os = VALUES(os),
            time_schedule = VALUES(time_schedule),
            language = VALUES(language)";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $adId,
            json_encode($countries), // Use countries
            json_encode($regions),
            json_encode($cities),
            json_encode($targeting['devices'] ?? []),
            json_encode($targeting['browsers'] ?? []),
            json_encode($targeting['os'] ?? []),
            json_encode($targeting['schedule'] ?? []),
            json_encode($targeting['language'] ?? [])
        ]);
    }

    /**
     * 获取广告定向规则
     */
    public function getTargeting($adId) {
        $sql = "SELECT * FROM ad_targeting WHERE ad_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$adId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return [
            'geo' => [
                'countries' => json_decode($result['geo_countries'] ?? '[]', true), // Use countries
                'regions' => json_decode($result['geo_regions'] ?? '[]', true),
                'cities' => json_decode($result['geo_cities'] ?? '[]', true)
            ],
            'devices' => json_decode($result['devices'] ?? '[]', true),
            'browsers' => json_decode($result['browsers'] ?? '[]', true),
            'os' => json_decode($result['os'] ?? '[]', true),
            'schedule' => json_decode($result['time_schedule'] ?? '[]', true),
            'language' => json_decode($result['language'] ?? '[]', true)
        ];
    }

    /**
     * 检查广告是否符合定向条件
     */
    public function matchTargeting($adId, $context) {
        $targeting = $this->getTargeting($adId);
        if (!$targeting) {
            return true; // 没有定向规则则默认匹配
        }

        // 检查地理位置
        if (!$this->matchGeo($targeting['geo'], $context)) {
            return false;
        }

        // 检查设备类型
        if (!empty($targeting['devices']) && 
            !in_array($context['device'], $targeting['devices'])) {
            return false;
        }

        // 检查浏览器
        if (!empty($targeting['browsers']) && 
            !in_array($context['browser'], $targeting['browsers'])) {
            return false;
        }

        // 检查操作系统
        if (!empty($targeting['os']) && 
            !in_array($context['os'], $targeting['os'])) {
            return false;
        }

        // 检查语言
        if (!empty($targeting['language']) && 
            !in_array($context['language'], $targeting['language'])) {
            return false;
        }

        // 检查时间
        if (!$this->matchSchedule($targeting['schedule'], $context['timezone'])) {
            return false;
        }

        return true;
    }

    /**
     * 检查地理位置匹配
     */
    private function matchGeo($geoTargeting, $context) {
        // Extract context data safely
        $contextCountry = $context['country'] ?? null;
        $contextRegion = $context['region'] ?? null;
        $contextCity = $context['city'] ?? null;

        // If no geographic context is available, we cannot match specific rules
        if ($contextCountry === null && $contextRegion === null && $contextCity === null) {
            // If there are specific geo rules, then it's a mismatch
            if (!empty($geoTargeting['countries']) || !empty($geoTargeting['regions']) || !empty($geoTargeting['cities'])) {
                 error_log("Geo Match Debug: No context geo data, but rules exist for ad targeting.");
                return false; 
            }
            // Otherwise, if no rules, it's a match
            return true;
        }

        // If no specific geographic targeting is set, match any location
        if (empty($geoTargeting['countries']) && 
            empty($geoTargeting['regions']) && 
            empty($geoTargeting['cities'])) {
            return true;
        }

        // Check Country (using 'countries' key from getTargeting)
        // Note: Comparison is case-insensitive for robustness (e.g., 'US' vs 'us')
        if (!empty($geoTargeting['countries'])) {
            $countriesLower = array_map('strtolower', $geoTargeting['countries']);
            if ($contextCountry === null || !in_array(strtolower($contextCountry), $countriesLower)) {
                return false; // Mismatch if context country is null or not in the list
            }
        }

        // Check Region (using 'regions' key from getTargeting)
        // Note: Comparison is case-insensitive
        if (!empty($geoTargeting['regions'])) {
            $regionsLower = array_map('strtolower', $geoTargeting['regions']);
             // Allow matching if country matches and region is specified, even if context region is null?
             // Current logic: require region match if rule exists.
            if ($contextRegion === null || !in_array(strtolower($contextRegion), $regionsLower)) {
                return false; // Mismatch if context region is null or not in the list
            }
        }

        // Check City (using 'cities' key from getTargeting)
        // Note: Comparison is case-insensitive
        if (!empty($geoTargeting['cities'])) {
            $citiesLower = array_map('strtolower', $geoTargeting['cities']);
            if ($contextCity === null || !in_array(strtolower($contextCity), $citiesLower)) {
                return false; // Mismatch if context city is null or not in the list
            }
        }

        // If we passed all checks, it's a match
        return true;
    }

    /**
     * 检查时间表匹配
     * Checks if the current time matches the defined schedule rules.
     * The comparison happens in the timezone specified within the schedule rule itself.
     */
    private function matchSchedule($schedule, $userTimezone = null) { // userTimezone might be useful for logging/debugging later
        // If schedule is empty or not an array, it means no time restriction
        if (empty($schedule) || !is_array($schedule)) {
            return true;
        }

        // Use the schedule's timezone if provided, otherwise default to UTC for safety
        $scheduleTimezoneStr = $schedule['timezone'] ?? 'UTC'; 
        
        try {
            // Validate and create the timezone object for the schedule
            $scheduleTzObject = new \DateTimeZone($scheduleTimezoneStr);
        } catch (\Exception $e) {
            error_log("AdTargetingModel Error: Invalid timezone '{$scheduleTimezoneStr}' in schedule rule. Ad will not match schedule.");
             // If the schedule timezone is invalid, we cannot reliably check the time.
            return false; 
        }
        
        // Get the current time in UTC, then convert to the schedule's target timezone
        try {
            $now = new \DateTime('now', new \DateTimeZone('UTC')); 
            $now->setTimezone($scheduleTzObject);
        } catch (\Exception $e) {
             error_log("AdTargetingModel Error: Failed to create DateTime object. " . $e->getMessage());
             // Cannot determine current time, safest to not match
             return false;
        }
        
        // Get current hour (0-23) and day of the week (1=Monday ... 7=Sunday) in the schedule's timezone
        $currentHour = (int)$now->format('G'); 
        $currentDayOfWeek = (int)$now->format('N'); // ISO-8601 day of week

        // Check Hour Match (if hours are specified in the rule)
        $allowedHours = $schedule['hours'] ?? [];
        if (!empty($allowedHours) && is_array($allowedHours)) {
            if (!in_array($currentHour, $allowedHours)) {
                // error_log("Time Match Debug: Hour {$currentHour} not in allowed hours: " . json_encode($allowedHours));
                return false; // Current hour not allowed
            }
        }

        // Check Day of Week Match (if days are specified in the rule)
        $allowedDays = $schedule['days'] ?? []; // Expecting [1, 2, 3, 4, 5] for Mon-Fri etc.
         if (!empty($allowedDays) && is_array($allowedDays)) {
            if (!in_array($currentDayOfWeek, $allowedDays)) {
                // error_log("Time Match Debug: Day {$currentDayOfWeek} not in allowed days: " . json_encode($allowedDays));
                return false; // Current day not allowed
            }
        }

        // Passed all time-based checks (or no specific time checks were defined)
        return true;
    }

    /**
     * 记录定向统计数据
     */
    public function logTargetingStats($adId, $context, $action = 'view') {
        $sql = "INSERT INTO ad_targeting_stats (
            ad_id, country, region, city, device, browser, os, 
            language, hour, date, views, clicks
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(),
            " . ($action === 'view' ? '1' : '0') . ",
            " . ($action === 'click' ? '1' : '0') . "
        ) ON DUPLICATE KEY UPDATE
            " . ($action === 'view' ? 'views = views + 1' : 'clicks = clicks + 1');

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $adId,
            'CN',
            $context['region'],
            $context['city'],
            $context['device'],
            $context['browser'],
            $context['os'],
            $context['language'],
            (int)(new \DateTime())->format('G')
        ]);
    }

    /**
     * 获取定向效果统计
     */
    public function getTargetingStats($adId, $startDate = null, $endDate = null) {
        $sql = "SELECT 
            country, region, city, device, browser, os, language, hour,
            SUM(views) as total_views,
            SUM(clicks) as total_clicks,
            (SUM(clicks) / SUM(views) * 100) as ctr
        FROM ad_targeting_stats 
        WHERE ad_id = ?";

        $params = [$adId];

        if ($startDate) {
            $sql .= " AND date >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $sql .= " AND date <= ?";
            $params[] = $endDate;
        }

        $sql .= " GROUP BY country, region, city, device, browser, os, language, hour
                  ORDER BY total_views DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
} 