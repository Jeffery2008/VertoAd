<?php

namespace App\Core;

use PDO;
use Exception;
use App\Services\AuthService;
use App\Services\AdService;
use App\Services\ActivationKeyService;
use App\Middleware\AuthMiddleware;
// Add use statements for all controllers that will be instantiated
use App\Controllers\Api\ServeController;
use App\Controllers\Api\Admin\ActivationKeyController;
use App\Controllers\Api\Advertiser\RedemptionController;

class Container
{
    private $bindings = [];
    private $instances = [];
    private $config = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->registerDefaultBindings();
    }

    // Register how to build essential services
    protected function registerDefaultBindings()
    {
        // Database Connection (PDO)
        $this->bind(PDO::class, function ($container) {
            $dbConfig = $container->getConfig('database');
            if (!$dbConfig) {
                throw new Exception('Database configuration missing.');
            }
            $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
            return new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        });

        // Services (Singleton instances)
        $this->singleton(AuthService::class, function ($container) {
            return new AuthService($container->get(PDO::class));
        });
        $this->singleton(AdService::class, function ($container) {
            return new AdService($container->get(PDO::class));
        });
        $this->singleton(ActivationKeyService::class, function ($container) {
            return new ActivationKeyService($container->get(PDO::class));
        });
        $this->singleton(\App\Services\TrackingService::class, function ($container) {
            return new \App\Services\TrackingService($container->get(PDO::class));
        });
        $this->singleton(\App\Services\PublisherService::class, function ($container) {
            return new \App\Services\PublisherService($container->get(PDO::class));
        });
        $this->singleton(\App\Services\UserService::class, function ($container) {
            return new \App\Services\UserService($container->get(PDO::class));
        });

        // Middleware (New instance each time)
        $this->bind(AuthMiddleware::class, function ($container) {
            return new AuthMiddleware($container->get(AuthService::class));
        });
        $this->bind(\App\Middleware\CsrfMiddleware::class, function ($container) {
            // CsrfMiddleware currently has no dependencies other than session management within itself
            return new \App\Middleware\CsrfMiddleware(); 
        });
        $this->bind(\App\Middleware\PoWMiddleware::class, function ($container) {
            // PoWMiddleware currently has no dependencies other than session management within itself
            return new \App\Middleware\PoWMiddleware(); 
        });
        
        // Controllers (New instance each time)
         $this->bind(ServeController::class, function ($container) {
            return new ServeController($container->get(AdService::class));
        });
         $this->bind(ActivationKeyController::class, function ($container) {
            return new ActivationKeyController($container->get(ActivationKeyService::class));
        });
         $this->bind(RedemptionController::class, function ($container) {
            return new RedemptionController(
                $container->get(ActivationKeyService::class),
                $container->get(AuthService::class) // Add AuthService dependency
            );
        });
        // Bind the new Advertiser AdController
         $this->bind(\App\Controllers\Api\Advertiser\AdController::class, function ($container) {
            return new \App\Controllers\Api\Advertiser\AdController(
                $container->get(AdService::class),
                $container->get(AuthService::class)
            );
        });
        $this->bind(\App\Controllers\Api\Publisher\ZoneController::class, function ($container) {
            return new \App\Controllers\Api\Publisher\ZoneController(
                $container->get(\App\Services\PublisherService::class),
                $container->get(AuthService::class)
            );
        });
        $this->bind(\App\Controllers\Api\Admin\UserController::class, function ($container) {
            return new \App\Controllers\Api\Admin\UserController(
                $container->get(\App\Services\UserService::class)
                // Inject AuthService if needed for checks like "prevent delete self"
                // $container->get(AuthService::class) 
            );
        });
        $this->bind(\App\Controllers\Api\Admin\AdController::class, function ($container) {
            return new \App\Controllers\Api\Admin\AdController(
                $container->get(AdService::class)
            );
        });
        // Add bindings for ALL other controllers used by the router here...
        // e.g., $this->bind(App\Controllers\AuthController::class, fn($c) => new App\Controllers\AuthController($c->get(AuthService::class)));

    }

    // Bind a factory function to a class name
    public function bind(string $key, callable $factory)
    {
        $this->bindings[$key] = $factory;
    }

    // Bind a factory function for a singleton instance
    public function singleton(string $key, callable $factory)
    {
        $this->bindings[$key] = function ($container) use ($factory, $key) {
            if (!isset($this->instances[$key])) {
                $this->instances[$key] = $factory($container);
            }
            return $this->instances[$key];
        };
    }

    // Get an instance from the container
    public function get(string $key)
    {
        if (!isset($this->bindings[$key])) {
            throw new Exception("No binding found for key: {$key}");
        }

        // Check if it's already a resolved singleton
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        $factory = $this->bindings[$key];
        return $factory($this); // Pass the container to the factory
    }
    
    // Check if a binding exists
    public function has(string $key): bool
    {
         return isset($this->bindings[$key]);
    }

    // Get configuration value
    public function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
} 