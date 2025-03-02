<?php

namespace App\Core;

class Request
{
    private $params;
    private $query;
    private $body;
    private $method;
    private $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->query = $_GET;
        $this->params = [];
        
        // 处理 PUT, DELETE 等请求的 body
        $this->body = [];
        if ($this->method !== 'GET') {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                $this->body = json_decode($input, true) ?? [];
            } else {
                $this->body = $_POST;
            }
        }
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getQuery($key = null)
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? null;
    }

    public function getBody($key = null)
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? null;
    }

    public function getParam($key)
    {
        return $this->params[$key] ?? null;
    }

    public function setParams($params)
    {
        $this->params = $params;
    }

    public function getHeader($key)
    {
        $headers = getallheaders();
        return $headers[$key] ?? null;
    }

    public function isAjax()
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    public function getIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function getUserAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function isSecure()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443;
    }
} 