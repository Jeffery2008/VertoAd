<?php

namespace VertoAD\Core\Routing;

/**
 * Router Class
 * Handles route registration and dispatching
 */
class Router {
    private $routes = [];
    private $debug = false;
    
    /**
     * Constructor
     */
    public function __construct($debug = false) {
        $this->debug = $debug;
    }
    
    /**
     * Register a GET route
     * 
     * @param string $path
     * @param string $handler
     * @return void
     */
    public function get($path, $handler) {
        $this->addRoute('GET', $path, $handler);
    }
    
    /**
     * Register a POST route
     * 
     * @param string $path
     * @param string $handler
     * @return void
     */
    public function post($path, $handler) {
        $this->addRoute('POST', $path, $handler);
    }
    
    /**
     * Register a PUT route
     * 
     * @param string $path
     * @param string $handler
     * @return void
     */
    public function put($path, $handler) {
        $this->addRoute('PUT', $path, $handler);
    }
    
    /**
     * Register a DELETE route
     * 
     * @param string $path
     * @param string $handler
     * @return void
     */
    public function delete($path, $handler) {
        $this->addRoute('DELETE', $path, $handler);
    }
    
    /**
     * Add a route to the routes array
     * 
     * @param string $method
     * @param string $path
     * @param string $handler
     * @return void
     */
    private function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
        
        if ($this->debug) {
            error_log("Route added: $method $path -> $handler");
        }
    }
    
    /**
     * Dispatch the request to the appropriate handler
     * 
     * @return void
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        
        // Remove query string from URI
        $uri = parse_url($uri, PHP_URL_PATH);
        
        // Remove trailing slashes
        $uri = rtrim($uri, '/');
        
        // Default to '/' if URI is empty
        if (empty($uri)) {
            $uri = '/';
        }
        
        if ($this->debug) {
            error_log("Dispatching request: $method $uri");
            error_log("Routes registered: " . count($this->routes));
        }
        
        // Find matching route
        $route = $this->findRoute($method, $uri);
        
        // No route found
        if (!$route) {
            if ($this->debug) {
                error_log("No route found for: $method $uri");
                // Log all registered routes for debugging
                foreach ($this->routes as $index => $routeInfo) {
                    error_log("Route[$index]: {$routeInfo['method']} {$routeInfo['path']} -> {$routeInfo['handler']}");
                }
            }
            $this->handleNotFound();
            return;
        }
        
        if ($this->debug) {
            error_log("Route found: {$route['method']} {$route['path']} -> {$route['handler']}");
        }
        
        // Get controller and method
        list($controller, $method) = $this->parseHandler($route['handler']);
        
        // Get URL parameters
        $params = $this->getRouteParams($route['path'], $uri);
        
        if ($this->debug) {
            error_log("Controller: $controller");
            error_log("Method: $method");
            error_log("Params: " . json_encode($params));
        }
        
        // Call controller method
        $this->callHandler($controller, $method, $params);
    }
    
    /**
     * Find a matching route for the request
     * 
     * @param string $method
     * @param string $uri
     * @return array|null
     */
    private function findRoute($method, $uri) {
        foreach ($this->routes as $route) {
            // Check method matches
            if ($route['method'] !== $method) {
                continue;
            }
            
            // Convert route path to regex
            $pattern = $this->routeToRegex($route['path']);
            
            if ($this->debug) {
                error_log("Checking route: {$route['method']} {$route['path']} -> {$route['handler']}");
                error_log("Pattern: $pattern");
                error_log("URI: $uri");
            }
            
            // Check if URI matches the pattern
            if (preg_match($pattern, $uri)) {
                return $route;
            }
        }
        
        return null;
    }
    
    /**
     * Convert a route path to a regex pattern
     * 
     * @param string $path
     * @return string
     */
    private function routeToRegex($path) {
        // Replace route parameters with regex patterns
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $pattern);
        
        // Add start and end delimiters
        $pattern = '/^' . $pattern . '$/';
        
        return $pattern;
    }
    
    /**
     * Get parameters from the route
     * 
     * @param string $routePath
     * @param string $uri
     * @return array
     */
    private function getRouteParams($routePath, $uri) {
        $params = [];
        
        // Get parameter names from route path
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $routePath, $paramNames);
        
        // Convert route path to regex with capture groups
        $pattern = $this->routeToRegex($routePath);
        
        // Match URI against pattern
        preg_match($pattern, $uri, $paramValues);
        
        // Remove full match from values
        array_shift($paramValues);
        
        // Combine parameter names and values
        foreach ($paramNames[1] as $index => $name) {
            $params[$name] = $paramValues[$index] ?? null;
        }
        
        return $params;
    }
    
    /**
     * Parse handler string into controller and method
     * 
     * @param string $handler
     * @return array
     */
    private function parseHandler($handler) {
        $parts = explode('@', $handler);
        
        $controllerName = $parts[0];
        $methodName = $parts[1] ?? 'index';
        
        // Add namespace to controller
        $controller = "\\VertoAD\\Core\\Controllers\\$controllerName";
        
        return [$controller, $methodName];
    }
    
    /**
     * Call the handler method
     * 
     * @param string $controller
     * @param string $method
     * @param array $params
     * @return void
     */
    private function callHandler($controller, $method, $params) {
        try {
            // Check if controller class exists
            if (!class_exists($controller)) {
                if ($this->debug) {
                    error_log("Controller class not found: $controller");
                }
                throw new \Exception("Controller not found: $controller");
            }
            
            // Create controller instance
            $controllerInstance = new $controller();
            
            // Check if method exists
            if (!method_exists($controllerInstance, $method)) {
                if ($this->debug) {
                    error_log("Method not found: $controller::$method");
                }
                throw new \Exception("Method not found: $controller::$method");
            }
            
            // Call method with parameters
            call_user_func_array([$controllerInstance, $method], $params);
        } catch (\Exception $e) {
            // Log exception
            error_log("Router Error: " . $e->getMessage());
            
            // Handle error
            $this->handleServerError($e);
        }
    }
    
    /**
     * Handle 404 Not Found errors
     * 
     * @return void
     */
    private function handleNotFound() {
        header("HTTP/1.0 404 Not Found");
        
        // Check if custom error page exists
        if (file_exists(__DIR__ . '/../../templates/errors/404.php')) {
            require_once __DIR__ . '/../../templates/errors/404.php';
        } else {
            echo "404 Page Not Found";
        }
        
        exit;
    }
    
    /**
     * Handle 500 Server Error
     * 
     * @param \Exception $e
     * @return void
     */
    private function handleServerError($e) {
        header("HTTP/1.0 500 Internal Server Error");
        
        // Check if custom error page exists
        if (file_exists(__DIR__ . '/../../templates/errors/500.php')) {
            require_once __DIR__ . '/../../templates/errors/500.php';
        } else {
            echo "500 Internal Server Error";
            
            // Show error details in development environment
            if (getenv('APP_ENV') === 'development') {
                echo "<pre>" . $e->getMessage() . "\n\n" . $e->getTraceAsString() . "</pre>";
            }
        }
        
        exit;
    }
} 