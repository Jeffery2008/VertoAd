<?php

namespace App\Utils;

/**
 * Cache utility for performance optimization
 */
class Cache
{
    private $db;
    private $logger;
    private $useFileCache;
    private $cacheDir;
    private $defaultTtl;
    private $configTable = 'system_config';
    
    /**
     * Initialize the cache utility
     * 
     * @param Database|null $db Database connection
     * @param Logger|null $logger Logger utility
     * @param bool $useFileCache Whether to use file-based caching
     * @param string $cacheDir Directory for file cache storage
     * @param int $defaultTtl Default time-to-live in seconds
     */
    public function __construct($db = null, $logger = null, $useFileCache = true, $cacheDir = null, $defaultTtl = 300)
    {
        $this->db = $db ?: new Database();
        $this->logger = $logger ?: new Logger();
        $this->useFileCache = $useFileCache;
        $this->cacheDir = $cacheDir ?: __DIR__ . '/../../cache';
        $this->defaultTtl = $defaultTtl;
        
        // Ensure cache directory exists if using file cache
        if ($this->useFileCache && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get a value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed Cached value or default
     */
    public function get($key, $default = null)
    {
        $value = null;
        
        if ($this->useFileCache) {
            $value = $this->getFromFileCache($key);
        }
        
        if ($value === null) {
            // If we don't have a cached value, return the default
            $value = $default;
        }
        
        return $value;
    }
    
    /**
     * Store a value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return bool Success
     */
    public function set($key, $value, $ttl = null)
    {
        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }
        
        $success = false;
        
        if ($this->useFileCache) {
            $success = $this->setInFileCache($key, $value, $ttl);
        }
        
        return $success;
    }
    
    /**
     * Delete a value from cache
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete($key)
    {
        $success = false;
        
        if ($this->useFileCache) {
            $success = $this->deleteFromFileCache($key);
        }
        
        return $success;
    }
    
    /**
     * Clear all cache values
     * 
     * @return bool Success
     */
    public function clear()
    {
        $success = false;
        
        if ($this->useFileCache) {
            $success = $this->clearFileCache();
        }
        
        return $success;
    }
    
    /**
     * Get a config value from system_config table or cache
     * 
     * @param string $key Config key
     * @param mixed $default Default value if key not found
     * @return mixed Config value
     */
    public function getConfig($key, $default = null)
    {
        // Try to get from cache first
        $cacheKey = 'config_' . $key;
        $cachedValue = $this->get($cacheKey);
        
        if ($cachedValue !== null) {
            return $this->castConfigValue($cachedValue['value'], $cachedValue['type']);
        }
        
        // If not in cache, get from database
        try {
            $query = "SELECT * FROM {$this->configTable} WHERE `key` = :key";
            $result = $this->db->fetchOne($query, ['key' => $key]);
            
            if ($result) {
                // Cache for future use (1 hour TTL for config values)
                $this->set($cacheKey, $result, 3600);
                return $this->castConfigValue($result['value'], $result['type']);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching config: ' . $e->getMessage());
        }
        
        return $default;
    }
    
    /**
     * Get a value from file cache
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    private function getFromFileCache($key)
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            $this->logger->error("Failed to read cache file: {$filePath}");
            return null;
        }
        
        $cacheData = json_decode($content, true);
        
        // Check if cache has expired
        if (isset($cacheData['expiry']) && $cacheData['expiry'] < time()) {
            $this->deleteFromFileCache($key);
            return null;
        }
        
        return isset($cacheData['data']) ? $cacheData['data'] : null;
    }
    
    /**
     * Store a value in file cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time-to-live in seconds
     * @return bool Success
     */
    private function setInFileCache($key, $value, $ttl)
    {
        $filePath = $this->getCacheFilePath($key);
        
        $cacheData = [
            'expiry' => time() + $ttl,
            'data' => $value
        ];
        
        $content = json_encode($cacheData);
        
        $result = file_put_contents($filePath, $content, LOCK_EX);
        
        if ($result === false) {
            $this->logger->error("Failed to write cache file: {$filePath}");
            return false;
        }
        
        return true;
    }
    
    /**
     * Delete a value from file cache
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    private function deleteFromFileCache($key)
    {
        $filePath = $this->getCacheFilePath($key);
        
        if (file_exists($filePath)) {
            $result = unlink($filePath);
            
            if ($result === false) {
                $this->logger->error("Failed to delete cache file: {$filePath}");
                return false;
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Clear all file cache values
     * 
     * @return bool Success
     */
    private function clearFileCache()
    {
        $files = glob($this->cacheDir . '/*.cache');
        
        if ($files === false) {
            $this->logger->error("Failed to list cache files");
            return false;
        }
        
        $success = true;
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                $this->logger->error("Failed to delete cache file: {$file}");
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get the file path for a cache key
     * 
     * @param string $key Cache key
     * @return string File path
     */
    private function getCacheFilePath($key)
    {
        // Generate a safe filename from key
        $filename = md5($key) . '.cache';
        return $this->cacheDir . '/' . $filename;
    }
    
    /**
     * Cast a config value to the correct type
     * 
     * @param string $value Config value
     * @param string $type Config value type
     * @return mixed Typed config value
     */
    private function castConfigValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'boolean':
                return $value === 'true' || $value === '1' || $value === 1;
            case 'json':
                return json_decode($value, true);
            case 'string':
            default:
                return $value;
        }
    }
    
    /**
     * Remember a computed value or get from cache if available
     * 
     * @param string $key Cache key
     * @param callable $callback Function to compute value if not in cache
     * @param int|null $ttl Time-to-live in seconds (null for default)
     * @return mixed Cached or computed value
     */
    public function remember($key, $callback, $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            
            if ($value !== null) {
                $this->set($key, $value, $ttl);
            }
        }
        
        return $value;
    }
    
    /**
     * Get cache TTL from config for a specific cache type
     * 
     * @param string $type Cache type (e.g., ad_serving, analytics)
     * @return int TTL in seconds
     */
    public function getTtl($type)
    {
        $configKey = "cache_ttl_{$type}";
        return $this->getConfig($configKey, $this->defaultTtl);
    }
}