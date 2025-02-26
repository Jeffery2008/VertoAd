<?php

namespace App\Middleware;

use App\Services\SecurityService;
use App\Services\AuthService;
use App\Utils\Logger;

/**
 * SecurityMiddleware - Apply security measures to HTTP requests
 */
class SecurityMiddleware
{
    /**
     * @var SecurityService $securityService Security service
     */
    private $securityService;
    
    /**
     * @var AuthService $authService Auth service
     */
    private $authService;
    
    /**
     * @var Logger $logger Logger instance
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->securityService = new SecurityService();
        $this->authService = new AuthService();
        $this->logger = new Logger('SecurityMiddleware');
    }
    
    /**
     * Process the request and apply security measures
     * 
     * @param callable $next The next middleware in the chain
     * @return callable A middleware handler
     */
    public function __invoke($next)
    {
        return function ($request, $response) use ($next) {
            // Extract route info from request (this depends on your router implementation)
            $route = $request->getAttribute('route') ?? [];
            $routeName = $route['name'] ?? 'unknown';
            
            // Apply rate limiting
            $this->applyRateLimiting($request, $response, $routeName);
            
            // Check for valid CSRF token on state-changing requests
            if ($this->shouldCheckCsrf($request)) {
                $this->verifyCsrfToken($request, $response);
            }
            
            // Check API key for API routes
            if ($this->isApiRoute($routeName)) {
                $this->verifyApiAuthentication($request, $response);
            }
            
            // Add security headers to response
            $response = $this->addSecurityHeaders($response);
            
            // Proceed to next middleware
            $response = $next($request, $response);
            
            // Add CSRF token to forms in HTML responses if needed
            if (strpos($response->getHeaderLine('Content-Type'), 'text/html') !== false) {
                $response = $this->injectCsrfTokens($request, $response);
            }
            
            return $response;
        };
    }
    
    /**
     * Apply rate limiting to the request
     * 
     * @param object $request The request object
     * @param object $response The response object 
     * @param string $routeName The current route name
     * @return void
     */
    private function applyRateLimiting($request, $response, $routeName)
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Apply IP-based rate limiting
        $ipResult = $this->securityService->applyRateLimit($ipAddress, 'ip', $routeName);
        
        // If user is authenticated, also apply user-based rate limiting
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId) {
            $userResult = $this->securityService->applyRateLimit($userId, 'user', $routeName);
            
            // Use the more restrictive result
            if ($userResult['limited'] && !$ipResult['limited']) {
                $ipResult = $userResult;
            }
        }
        
        // API key rate limiting
        $apiKey = $this->getApiKeyFromRequest($request);
        if ($apiKey && $this->isApiRoute($routeName)) {
            $keyResult = $this->securityService->applyRateLimit($apiKey, 'api_key', $routeName);
            
            // Use the more restrictive result
            if ($keyResult['limited'] && !$ipResult['limited']) {
                $ipResult = $keyResult;
            }
        }
        
        // If rate limited, return 429 Too Many Requests
        if ($ipResult['limited']) {
            $response->withStatus(429)
                ->withHeader('Retry-After', (string)($ipResult['reset'] - time()))
                ->withHeader('X-RateLimit-Limit', (string)($this->getRateLimitForRoute($routeName)))
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string)$ipResult['reset']);
            
            $response->getBody()->write(json_encode([
                'error' => 'Too many requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $ipResult['reset'] - time()
            ]));
            
            // Log the rate limit event
            $this->logger->warning('Rate limit exceeded', [
                'ip' => $ipAddress,
                'user_id' => $userId,
                'api_key' => $apiKey ? substr($apiKey, 0, 8) . '...' : null,
                'route' => $routeName
            ]);
            
            exit; // Stop execution
        }
        
        // Add rate limit headers to response
        $response = $response->withHeader('X-RateLimit-Limit', (string)$this->getRateLimitForRoute($routeName))
            ->withHeader('X-RateLimit-Remaining', (string)$ipResult['remaining'])
            ->withHeader('X-RateLimit-Reset', (string)$ipResult['reset']);
    }
    
    /**
     * Get the rate limit for a specific route
     * 
     * @param string $routeName The route name
     * @return int The rate limit
     */
    private function getRateLimitForRoute($routeName)
    {
        // In a real application, this would be configurable
        // For now, we'll use default values
        if (strpos($routeName, 'api.') === 0) {
            return 120; // API routes
        } else if (strpos($routeName, 'admin.') === 0) {
            return 300; // Admin routes
        } else {
            return 60; // Default
        }
    }
    
    /**
     * Check if the request should be CSRF protected
     * 
     * @param object $request The request object
     * @return bool Whether to check CSRF
     */
    private function shouldCheckCsrf($request)
    {
        $method = $request->getMethod();
        
        // Only check CSRF for state-changing requests
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return false;
        }
        
        // Skip CSRF for API routes with valid authentication
        if ($this->isApiRequest($request) && $this->hasValidApiAuth($request)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verify the CSRF token in the request
     * 
     * @param object $request The request object
     * @param object $response The response object
     * @return void
     */
    private function verifyCsrfToken($request, $response)
    {
        $method = $request->getMethod();
        
        // Only process state-changing methods
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return;
        }
        
        // Get the token from the request
        $token = $this->getCsrfTokenFromRequest($request);
        if (!$token) {
            $this->logger->warning('Missing CSRF token', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'method' => $method,
                'uri' => $request->getUri()->getPath()
            ]);
            
            $response->withStatus(403);
            $response->getBody()->write(json_encode([
                'error' => 'CSRF validation failed',
                'message' => 'Missing CSRF token'
            ]));
            
            exit; // Stop execution
        }
        
        // Get the page/form ID from the request
        $pageId = $this->getPageIdFromRequest($request);
        
        // Verify the token
        if (!$this->securityService->verifyCsrfToken($token, $pageId)) {
            $this->logger->warning('Invalid CSRF token', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'method' => $method,
                'uri' => $request->getUri()->getPath(),
                'token' => $token,
                'page_id' => $pageId
            ]);
            
            $response->withStatus(403);
            $response->getBody()->write(json_encode([
                'error' => 'CSRF validation failed',
                'message' => 'Invalid CSRF token'
            ]));
            
            exit; // Stop execution
        }
    }
    
    /**
     * Get the CSRF token from the request
     * 
     * @param object $request The request object
     * @return string|null The CSRF token or null if not found
     */
    private function getCsrfTokenFromRequest($request)
    {
        // Check for token in the request body
        $params = $request->getParsedBody();
        if (isset($params['csrf_token'])) {
            return $params['csrf_token'];
        }
        
        // Check for token in the headers
        $headerToken = $request->getHeaderLine('X-CSRF-Token');
        if ($headerToken) {
            return $headerToken;
        }
        
        return null;
    }
    
    /**
     * Get the page ID from the request
     * 
     * @param object $request The request object
     * @return string The page ID
     */
    private function getPageIdFromRequest($request)
    {
        // This could be derived from the route or a form parameter
        $params = $request->getParsedBody();
        if (isset($params['page_id'])) {
            return $params['page_id'];
        }
        
        // Default to the request URI
        return $request->getUri()->getPath();
    }
    
    /**
     * Check if the request is to an API route
     * 
     * @param string $routeName The route name
     * @return bool Whether this is an API route
     */
    private function isApiRoute($routeName)
    {
        return strpos($routeName, 'api.') === 0;
    }
    
    /**
     * Check if this is an API request
     * 
     * @param object $request The request object
     * @return bool Whether this is an API request
     */
    private function isApiRequest($request)
    {
        // Check if the request is for the API based on URI
        $uri = $request->getUri()->getPath();
        if (strpos($uri, '/api/') === 0) {
            return true;
        }
        
        // Check Accept header
        $accept = $request->getHeaderLine('Accept');
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the request has valid API authentication
     * 
     * @param object $request The request object
     * @return bool Whether the request has valid API auth
     */
    private function hasValidApiAuth($request)
    {
        // Check for API key
        $apiKey = $this->getApiKeyFromRequest($request);
        if ($apiKey && $this->authService->validateApiKey($apiKey)) {
            return true;
        }
        
        // Check for OAuth token
        $token = $this->getBearerTokenFromRequest($request);
        if ($token && $this->authService->validateBearerToken($token)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get API key from the request
     * 
     * @param object $request The request object
     * @return string|null The API key or null if not found
     */
    private function getApiKeyFromRequest($request)
    {
        // Check for API key in header
        $apiKey = $request->getHeaderLine('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }
        
        // Check for API key in query string
        $params = $request->getQueryParams();
        if (isset($params['api_key'])) {
            return $params['api_key'];
        }
        
        return null;
    }
    
    /**
     * Get bearer token from the request
     * 
     * @param object $request The request object
     * @return string|null The bearer token or null if not found
     */
    private function getBearerTokenFromRequest($request)
    {
        $auth = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Verify API authentication
     * 
     * @param object $request The request object
     * @param object $response The response object
     * @return void
     */
    private function verifyApiAuthentication($request, $response)
    {
        // Skip authentication check for certain public API endpoints if needed
        $uri = $request->getUri()->getPath();
        if ($this->isPublicApiEndpoint($uri)) {
            return;
        }
        
        if (!$this->hasValidApiAuth($request)) {
            $this->logger->warning('Unauthorized API access attempt', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                'uri' => $uri
            ]);
            
            $response->withStatus(401)
                ->withHeader('WWW-Authenticate', 'Bearer realm="api"');
            
            $response->getBody()->write(json_encode([
                'error' => 'Unauthorized',
                'message' => 'Valid API key or Bearer token required'
            ]));
            
            exit; // Stop execution
        }
    }
    
    /**
     * Check if an API endpoint is public (no auth required)
     * 
     * @param string $uri The request URI
     * @return bool Whether the endpoint is public
     */
    private function isPublicApiEndpoint($uri)
    {
        // List of public API endpoints
        $publicEndpoints = [
            '/api/v1/auth/login',
            '/api/v1/auth/challenge',
            '/api/v1/auth/token'
        ];
        
        return in_array($uri, $publicEndpoints);
    }
    
    /**
     * Add security headers to the response
     * 
     * @param object $response The response object
     * @return object The modified response
     */
    private function addSecurityHeaders($response)
    {
        // Content Security Policy
        $cspValue = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " . 
                   "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' https://cdn.jsdelivr.net; " .
                   "connect-src 'self'; " .
                   "frame-ancestors 'self'";
        
        return $response
            // Prevent clickjacking
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            // Prevent MIME type sniffing
            ->withHeader('X-Content-Type-Options', 'nosniff')
            // XSS protection
            ->withHeader('X-XSS-Protection', '1; mode=block')
            // HSTS (optional, should only be used with HTTPS)
            ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            // Referrer policy
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            // Content Security Policy
            ->withHeader('Content-Security-Policy', $cspValue)
            // Feature policy
            ->withHeader('Feature-Policy', "camera 'none'; microphone 'none'; geolocation 'self'");
    }
    
    /**
     * Inject CSRF tokens into HTML forms
     * 
     * @param object $request The request object
     * @param object $response The response object
     * @return object The modified response
     */
    private function injectCsrfTokens($request, $response)
    {
        // Only process HTML responses
        if (strpos($response->getHeaderLine('Content-Type'), 'text/html') === false) {
            return $response;
        }
        
        $body = (string)$response->getBody();
        
        // If the body doesn't contain a form, return the original response
        if (strpos($body, '<form') === false) {
            return $response;
        }
        
        // Replace each form with a version that includes a CSRF token
        $body = preg_replace_callback('/<form(.*?)>/i', function($matches) use ($request) {
            $formHtml = $matches[0];
            $formAttrs = $matches[1];
            
            // Extract form ID or action for the page ID
            $pageId = $this->extractFormIdentifier($formAttrs, $request);
            
            // Generate a CSRF token for this form
            $userId = $_SESSION['user_id'] ?? null;
            $token = $this->securityService->generateCsrfToken($pageId, $userId);
            
            // Add the CSRF token to the form
            $csrfInput = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
            $csrfInput .= '<input type="hidden" name="page_id" value="' . htmlspecialchars($pageId) . '">';
            
            return $formHtml . $csrfInput;
        }, $body);
        
        // Create a new response with the modified body
        $response = $response->withBody(new \Slim\Psr7\Stream(fopen('php://temp', 'r+')));
        $response->getBody()->write($body);
        
        return $response;
    }
    
    /**
     * Extract form identifier from form attributes
     * 
     * @param string $formAttrs Form attributes as a string
     * @param object $request Request object
     * @return string The form identifier
     */
    private function extractFormIdentifier($formAttrs, $request)
    {
        // Try to get form ID
        if (preg_match('/id=["\']([^"\']+)["\']/', $formAttrs, $matches)) {
            return 'form_' . $matches[1];
        }
        
        // Try to get form action
        if (preg_match('/action=["\']([^"\']+)["\']/', $formAttrs, $matches)) {
            return $matches[1];
        }
        
        // Default to current URI
        return $request->getUri()->getPath();
    }
} 