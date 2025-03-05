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
            json_encode($targeting['geo']['provinces'] ?? []),
            json_encode($targeting['geo']['regions'] ?? []),
            json_encode($targeting['geo']['cities'] ?? []),
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
                'provinces' => json_decode($result['geo_countries'] ?? '[]'),
                'regions' => json_decode($result['geo_regions'] ?? '[]'),
                'cities' => json_decode($result['geo_cities'] ?? '[]')
            ],
            'devices' => json_decode($result['devices'] ?? '[]'),
            'browsers' => json_decode($result['browsers'] ?? '[]'),
            'os' => json_decode($result['os'] ?? '[]'),
            'schedule' => json_decode($result['time_schedule'] ?? '[]'),
            'language' => json_decode($result['language'] ?? '[]')
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
        // 如果没有地理定向，则匹配所有位置
        if (empty($geoTargeting['provinces']) && 
            empty($geoTargeting['regions']) && 
            empty($geoTargeting['cities'])) {
            return true;
        }

        // 检查省份名称
        if (!empty($geoTargeting['provinces']) && 
            !in_array($context['province'], $geoTargeting['provinces'])) {
            return false;
        }

        // 检查省份代码
        if (!empty($geoTargeting['regions']) && 
            !in_array($context['region'], $geoTargeting['regions'])) {
            return false;
        }

        // 检查城市代码
        if (!empty($geoTargeting['cities']) && 
            !in_array($context['city'], $geoTargeting['cities'])) {
            return false;
        }

        return true;
    }

    /**
     * 检查时间表匹配
     */
    private function matchSchedule($schedule, $userTimezone) {
        if (empty($schedule)) {
            return true;
        }

        $timezone = new \DateTimeZone($schedule['timezone'] ?? 'Asia/Shanghai');
        $userTz = new \DateTimeZone($userTimezone);
        $now = new \DateTime('now', $userTz);
        $now->setTimezone($timezone);
        
        $hour = (int)$now->format('G');
        return in_array($hour, $schedule['hours'] ?? []);
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