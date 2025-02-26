<?php

namespace VertoAD\Core\Services;

use VertoAD\Core\Utils\Cache;
use VertoAD\Core\Models\Conversion;
use VertoAD\Core\Models\Impression;
use VertoAD\Core\Models\Click;

/**
 * AnalyticsCacheService
 * 
 * Handles caching for analytics data to improve performance
 */
class AnalyticsCacheService
{
    /**
     * @var Cache $cache Cache utility
     */
    private $cache;
    
    /**
     * @var int $defaultTtl Default cache TTL in seconds
     */
    private $defaultTtl;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cache = new Cache();
        $this->defaultTtl = $this->cache->getTtl('analytics') ?: 3600; // 1 hour default
    }
    
    /**
     * Get cached conversion data by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Filter options
     * @return array|null Cached conversion data or null if not cached
     */
    public function getCachedConversionsByAdId($adId, $options = [])
    {
        $cacheKey = $this->generateCacheKey('conversions_ad', $adId, $options);
        return $this->cache->get($cacheKey);
    }
    
    /**
     * Cache conversion data by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $data Conversion data
     * @param array $options Filter options
     * @return bool Success
     */
    public function cacheConversionsByAdId($adId, $data, $options = [])
    {
        $cacheKey = $this->generateCacheKey('conversions_ad', $adId, $options);
        return $this->cache->set($cacheKey, $data, $this->defaultTtl);
    }
    
    /**
     * Get cached conversion summary by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Filter options
     * @return array|null Cached conversion summary or null if not cached
     */
    public function getCachedConversionSummaryByAdId($adId, $options = [])
    {
        $cacheKey = $this->generateCacheKey('conversion_summary_ad', $adId, $options);
        return $this->cache->get($cacheKey);
    }
    
    /**
     * Cache conversion summary by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $data Conversion summary data
     * @param array $options Filter options
     * @return bool Success
     */
    public function cacheConversionSummaryByAdId($adId, $data, $options = [])
    {
        $cacheKey = $this->generateCacheKey('conversion_summary_ad', $adId, $options);
        return $this->cache->set($cacheKey, $data, $this->defaultTtl);
    }
    
    /**
     * Get cached conversion rate by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Filter options
     * @return float|null Cached conversion rate or null if not cached
     */
    public function getCachedConversionRateByAdId($adId, $options = [])
    {
        $cacheKey = $this->generateCacheKey('conversion_rate_ad', $adId, $options);
        return $this->cache->get($cacheKey);
    }
    
    /**
     * Cache conversion rate by ad ID
     * 
     * @param int $adId Ad ID
     * @param float $rate Conversion rate
     * @param array $options Filter options
     * @return bool Success
     */
    public function cacheConversionRateByAdId($adId, $rate, $options = [])
    {
        $cacheKey = $this->generateCacheKey('conversion_rate_ad', $adId, $options);
        return $this->cache->set($cacheKey, $rate, $this->defaultTtl);
    }
    
    /**
     * Get cached daily conversion data by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Filter options
     * @return array|null Cached daily conversion data or null if not cached
     */
    public function getCachedDailyConversionsByAdId($adId, $options = [])
    {
        $cacheKey = $this->generateCacheKey('daily_conversions_ad', $adId, $options);
        return $this->cache->get($cacheKey);
    }
    
    /**
     * Cache daily conversion data by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $data Daily conversion data
     * @param array $options Filter options
     * @return bool Success
     */
    public function cacheDailyConversionsByAdId($adId, $data, $options = [])
    {
        $cacheKey = $this->generateCacheKey('daily_conversions_ad', $adId, $options);
        return $this->cache->set($cacheKey, $data, $this->defaultTtl);
    }
    
    /**
     * Get cached ROI analytics by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $options Filter options
     * @return array|null Cached ROI analytics or null if not cached
     */
    public function getCachedRoiAnalyticsByAdId($adId, $options = [])
    {
        $cacheKey = $this->generateCacheKey('roi_analytics_ad', $adId, $options);
        return $this->cache->get($cacheKey);
    }
    
    /**
     * Cache ROI analytics by ad ID
     * 
     * @param int $adId Ad ID
     * @param array $data ROI analytics data
     * @param array $options Filter options
     * @return bool Success
     */
    public function cacheRoiAnalyticsByAdId($adId, $data, $options = [])
    {
        $cacheKey = $this->generateCacheKey('roi_analytics_ad', $adId, $options);
        return $this->cache->set($cacheKey, $data, $this->defaultTtl);
    }
    
    /**
     * Get cached dashboard summary data
     * 
     * @param int $userId User ID
     * @param array $options Filter options
     * @return array|null Cached dashboard summary or null if not cached
     */
    public function getCachedDashboardSummary($userId, $options = [])
    {
        $cacheKey = $this->generateCacheKey('dashboard_summary', $userId, $options);
        return $this->cache->get($cacheKey);
    }
    
    /**
     * Cache dashboard summary data
     * 
     * @param int $userId User ID
     * @param array $data Dashboard summary data
     * @param array $options Filter options
     * @return bool Success
     */
    public function cacheDashboardSummary($userId, $data, $options = [])
    {
        $cacheKey = $this->generateCacheKey('dashboard_summary', $userId, $options);
        return $this->cache->set($cacheKey, $data, $this->defaultTtl);
    }
    
    /**
     * Clear all conversion-related caches for an ad
     * 
     * @param int $adId Ad ID
     * @return bool Success
     */
    public function clearAdConversionCaches($adId)
    {
        $cacheKeys = [
            $this->generateCacheKey('conversions_ad', $adId),
            $this->generateCacheKey('conversion_summary_ad', $adId),
            $this->generateCacheKey('conversion_rate_ad', $adId),
            $this->generateCacheKey('daily_conversions_ad', $adId),
            $this->generateCacheKey('roi_analytics_ad', $adId)
        ];
        
        $success = true;
        foreach ($cacheKeys as $key) {
            if (!$this->cache->delete($key)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Generate a cache key based on type, ID, and options
     * 
     * @param string $type Cache type
     * @param int $id Entity ID
     * @param array $options Filter options
     * @return string Cache key
     */
    private function generateCacheKey($type, $id, $options = [])
    {
        $key = "{$type}_{$id}";
        
        if (!empty($options)) {
            // Sort options to ensure consistent cache keys
            ksort($options);
            
            // Add options hash to key
            $optionsHash = md5(json_encode($options));
            $key .= "_{$optionsHash}";
        }
        
        return $key;
    }
    
    /**
     * Remember a computed value or get from cache if available
     * 
     * @param string $type Cache type
     * @param int $id Entity ID
     * @param array $options Filter options
     * @param callable $callback Function to compute value if not in cache
     * @return mixed Cached or computed value
     */
    public function remember($type, $id, $options, $callback)
    {
        $cacheKey = $this->generateCacheKey($type, $id, $options);
        return $this->cache->remember($cacheKey, $callback, $this->defaultTtl);
    }
} 