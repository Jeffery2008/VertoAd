<?php

namespace App\Core;

use Exception;

class Router
{
    private $routes = [];
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function addRoute($method, $path, $handler, $middleware = [])
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^{$pattern}$#";
        
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => (array) $middleware,
            'path' => $path
        ];
    }

    public function handleRequest(Request $request)
    {
        $method = $request->getMethod();
        $uri = $request->getUri();
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);

                $finalAction = function ($req) use ($route, $params) {
                    if (is_callable($route['handler'])) {
                        return call_user_func($route['handler'], $req);
                    }

                    if (is_string($route['handler']) && strpos($route['handler'], '@') !== false) {
                        list($controllerName, $action) = explode('@', $route['handler']);
                        $controllerClass = "App\\Controllers\\" . str_replace('/', '\\', $controllerName);

                        if ($this->container->has($controllerClass)) {
                            $controllerInstance = $this->container->get($controllerClass);
                            
                            if (method_exists($controllerInstance, $action)) {
                                return call_user_func_array([$controllerInstance, $action], array_merge([$req], array_values($params)));
                            }
                        }
                         throw new Exception("Controller or action not found: {$route['handler']}");
                    }
                    
                    throw new Exception("Invalid handler format for route: {$route['path']}");
                };

                $handler = array_reduce(
                    array_reverse($route['middleware']),
                    function ($next, $middlewareInfo) use ($request) {
                        return function ($req) use ($next, $middlewareInfo) {
                            list($middlewareClass, $args) = is_array($middlewareInfo) 
                                ? [$middlewareInfo[0], $middlewareInfo[1] ?? []] 
                                : [$middlewareInfo, []];
                                
                            if ($this->container->has($middlewareClass)) {
                                $middlewareInstance = $this->container->get($middlewareClass);
                                return $middlewareInstance($req, $next, $args);
                            }
                            throw new Exception("Middleware class not found or not bound in container: {$middlewareClass}");
                        };
                    },
                    $finalAction
                );

                try {
                    $response = $handler($request);
                    return;
                } catch (Exception $e) {
                    error_log("Routing/Middleware/Controller Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                    header("HTTP/1.0 500 Internal Server Error");
                    echo "500 Internal Server Error";
                    return;
                }
            }
        }

        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }
} 