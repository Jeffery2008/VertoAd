<?php

namespace App\Config;

class Services
{
    public static function getIpinfoToken(): ?string
    {
        // It's recommended to load this from environment variables or a secure configuration store
        // For now, we'll hardcode it here temporarily, but plan to move it.
        return 'cfa96678b90001'; 
    }

    // --- Redis Configuration --- 

    /**
     * Enable/disable Redis usage for features like budget tracking.
     * Load from environment variable or .env file ideally.
     */
    public static function isRedisEnabled(): bool
    {
        return true; // Default to true for now, change to false to disable
                     // TODO: Load from env var e.g., getenv('REDIS_ENABLED') === 'true'
    }

    /**
     * Redis connection parameters.
     * Load from environment variables or .env file ideally.
     */
    public static function getRedisConfig(): array
    {
        return [
            'scheme' => getenv('REDIS_SCHEME') ?: 'tcp',
            'host'   => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port'   => getenv('REDIS_PORT') ?: 6379,
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => getenv('REDIS_DATABASE') ?: 0,
            'timeout' => 0.5, // Connection timeout in seconds
        ];
    }

    // Add other service configurations here as needed
} 