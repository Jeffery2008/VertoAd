<?php

namespace App\Core;

class Router
{
    private $routes = [];
    private $params = [];

    public function addRoute($method, $path, $handler)
    {
        // 将路径参数转换为正则表达式
        $pattern = preg_replace('/\{([a-zA-Z]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^{$pattern}$#";
        
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
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
                // 提取路径参数
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);

                if (is_callable($route['handler'])) {
                    call_user_func($route['handler'], $request);
                    return;
                }

                // 处理控制器@方法格式
                list($controller, $action) = explode('@', $route['handler']);
                $controller = "App\\Controllers\\{$controller}";
                
                if (class_exists($controller)) {
                    $controllerInstance = new $controller();
                    if (method_exists($controllerInstance, $action)) {
                        call_user_func([$controllerInstance, $action], $request);
                        return;
                    }
                }

                throw new \Exception("Handler not found: {$route['handler']}");
            }
        }

        // 没有找到匹配的路由
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }
} 