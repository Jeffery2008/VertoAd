<?php

namespace App\Utils;

use App\Config\Services;
use Predis\Client as PredisClient;
use Predis\Connection\ConnectionException;

class RedisService
{
    private ?PredisClient $client = null;
    private bool $isEnabled = false;

    public function __construct()
    {
        $this->isEnabled = Services::isRedisEnabled();
        if ($this->isEnabled) {
            $this->connect();
        }
    }

    private function connect(): void
    {
        try {
            $config = Services::getRedisConfig();
            
            // Filter out null password if not set
            $parameters = [
                'scheme' => $config['scheme'],
                'host'   => $config['host'],
                'port'   => $config['port'],
                'database' => $config['database'],
                'timeout' => $config['timeout'],
                'read_write_timeout' => $config['timeout'], // Predis uses this for R/W operations
            ];
            if (!empty($config['password'])) {
                $parameters['password'] = $config['password'];
            }

            $this->client = new PredisClient($parameters, ['exceptions' => true]);
            // Optional: Test connection immediately
            // $this->client->ping(); 
             error_log("RedisService: Connection successful."); // Log success
        } catch (ConnectionException $e) {
            $this->client = null; // Ensure client is null on connection failure
            $this->isEnabled = false; // Disable Redis for this instance if connection fails
            error_log("RedisService Error: Failed to connect to Redis. Disabling Redis features. Error: " . $e->getMessage());
        } catch (\Exception $e) {
            // Catch other potential exceptions during client creation
            $this->client = null;
            $this->isEnabled = false;
            error_log("RedisService Error: Unexpected error during Redis client creation. Disabling Redis features. Error: " . $e->getMessage());
        }
    }

    /**
     * Get the Predis client instance.
     *
     * @return PredisClient|null The client instance, or null if Redis is disabled or connection failed.
     */
    public function getClient(): ?PredisClient
    {
        // Return client only if enabled and connection was successful
        return $this->isEnabled && $this->client ? $this->client : null;
    }

    /**
     * Check if Redis is enabled and connected.
     */
    public function isConnected(): bool
    {
        return $this->isEnabled && $this->client !== null;
    }
} 