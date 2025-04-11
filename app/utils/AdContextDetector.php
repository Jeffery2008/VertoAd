<?php

namespace App\Utils;

use App\Core\Database;
use App\Utils\GeoIPService;

class AdContextDetector {
    private $db;
    private GeoIPService $geoIPService;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->geoIPService = new GeoIPService();
    }

    /**
     * 获取完整的广告上下文信息
     */
    public function getContext($ip = null) {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        return array_merge(
            $this->getGeoInfo($ip),
            $this->getDeviceInfo($userAgent),
            ['language' => $this->getLanguage($acceptLanguage)]
        );
    }

    /**
     * 获取地理位置信息
     */
    private function getGeoInfo($ip) {
        // 首先检查缓存
        $cached = $this->getGeoFromCache($ip);
        if ($cached) {
            return $cached;
        }

        // If cache miss, use GeoIPService
        $ipinfoData = $this->geoIPService->lookup($ip);

        if ($ipinfoData) {
            // Parse lat/lon from 'loc'
            $latitude = null;
            $longitude = null;
            if (!empty($ipinfoData['loc'])) {
                $parts = explode(',', $ipinfoData['loc']);
                if (count($parts) === 2) {
                    $latitude = (float)$parts[0];
                    $longitude = (float)$parts[1];
                }
            }

            $geoInfo = [
                // Note: ipinfo.io returns region name, not necessarily ISO code.
                // The cache table and potentially targeting logic might expect ISO codes.
                // Storing the name for now.
                'country' => $ipinfoData['country'] ?? null,
                'region' => $ipinfoData['region'] ?? null, 
                'city' => $ipinfoData['city'] ?? null,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'timezone' => $ipinfoData['timezone'] ?? 'UTC' // Default to UTC if not provided
            ];

            // 保存到缓存
            $this->saveGeoToCache($ip, $geoInfo);
            return $geoInfo;

        } else {
             error_log("GeoIP Error: Failed to get GeoIP info from service for IP: " . $ip);
             // Fallback to default values if lookup fails
             return [
                'country' => null,
                'region' => null,
                'city' => null,
                'latitude' => null,
                'longitude' => null,
                'timezone' => 'UTC'
            ];
        }
    }

    /**
     * 从缓存获取地理信息
     */
    private function getGeoFromCache($ip) {
        $sql = "SELECT * FROM ip_geo_cache WHERE ip_address = ? AND last_updated > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ip]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result) {
            return [
                'country' => $result['country'],
                'region' => $result['region'],
                'city' => $result['city'],
                'latitude' => $result['latitude'],
                'longitude' => $result['longitude'],
                'timezone' => $result['timezone']
            ];
        }

        return null;
    }

    /**
     * 保存地理信息到缓存
     */
    private function saveGeoToCache($ip, $geoInfo) {
        $sql = "INSERT INTO ip_geo_cache (
            ip_address, country, region, city, latitude, longitude, timezone
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            country = VALUES(country),
            region = VALUES(region),
            city = VALUES(city),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            timezone = VALUES(timezone),
            last_updated = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $ip,
            $geoInfo['country'],
            $geoInfo['region'],
            $geoInfo['city'],
            $geoInfo['latitude'],
            $geoInfo['longitude'],
            $geoInfo['timezone']
        ]);
    }

    /**
     * 获取设备信息
     */
    private function getDeviceInfo($userAgent) {
        $device = 'desktop';
        $browser = 'unknown';
        $os = 'unknown';

        // 检测移动设备
        if (preg_match('/(android|webos|iphone|ipad|ipod|blackberry|windows phone)/i', $userAgent)) {
            $device = preg_match('/(tablet|ipad)/i', $userAgent) ? 'tablet' : 'mobile';
        }

        // 检测浏览器
        if (preg_match('/chrome/i', $userAgent)) {
            $browser = 'chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
            $browser = 'safari';
        } elseif (preg_match('/edge/i', $userAgent)) {
            $browser = 'edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'opera';
        } elseif (preg_match('/msie|trident/i', $userAgent)) {
            $browser = 'ie';
        }

        // 检测操作系统
        if (preg_match('/windows/i', $userAgent)) {
            $os = 'windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = 'macos';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $os = 'ios';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'linux';
        }

        return [
            'device' => $device,
            'browser' => $browser,
            'os' => $os
        ];
    }

    /**
     * 获取用户语言
     */
    private function getLanguage($acceptLanguage) {
        if (empty($acceptLanguage)) {
            return 'en';
        }

        // 解析Accept-Language头
        $languages = explode(',', $acceptLanguage);
        $primaryLang = explode(';', $languages[0])[0];
        
        // 获取主要语言代码
        return substr($primaryLang, 0, 2);
    }
} 