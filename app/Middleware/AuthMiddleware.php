<?php

namespace App\Middleware;

use App\Services\AuthService;
use Exception;
// Depending on framework, Request/Response/Next handler might be passed
// use Psr\Http\Message\ServerRequestInterface as Request;
// use Psr\Http\Message\ResponseInterface as Response;
// use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthMiddleware
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Middleware invokable method (adjust signature based on framework).
     *
     * Checks authentication and optionally role-based access.
     *
     * @param mixed $request The incoming request object (framework specific).
     * @param mixed $handler The next middleware or request handler (framework specific).
     * @param array $roles Optional array of roles allowed for this route.
     * @return mixed The response object (framework specific).
     */
    public function __invoke($request, $handler, array $roles = []) // Example signature
    {
        try {
            // 1. Check Authentication
            if (!$this->authService->isAuthenticated()) {
                // Not logged in
                return $this->unauthorizedResponse('Authentication required.');
            }

            // 2. Check Role-Based Access (if roles are specified for the route)
            if (!empty($roles)) {
                $userRole = $this->authService->getCurrentUserRole();
                if (!$userRole || !in_array($userRole, $roles)) {
                    // User does not have the required role
                    return $this->forbiddenResponse('Insufficient permissions.');
                }
            }

            // 3. User is authenticated and has the required role (if applicable)
            // Optional: Attach user info to the request for controllers to use
            // $user = $this->authService->getCurrentUser();
            // $request = $request->withAttribute('user', $user); 

            // Proceed to the next middleware or controller
            // The exact call depends on the framework (e.g., PSR-15, Slim, Laravel)
            // return $handler->handle($request); // PSR-15 example
            return $this->callNextHandler($request, $handler); // Placeholder call

        } catch (Exception $e) {
            // Log the exception
            return $this->errorResponse('An internal authentication error occurred.');
        }
    }

    // --- Helper methods for responses (adjust based on framework/BaseApiController) ---

    protected function unauthorizedResponse(string $message = 'Unauthorized')
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit();
    }

    protected function forbiddenResponse(string $message = 'Forbidden')
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit();
    }

    protected function errorResponse(string $message = 'Internal Server Error')
    {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit();
    }
    
    // Placeholder for calling the next handler - replace with actual framework logic
    protected function callNextHandler($request, $handler) {
        // This needs to be implemented based on your specific framework/router
        // Examples:
        // PSR-15: return $handler->handle($request);
        // Slim: return $handler($request); 
        // Or maybe the framework handles it automatically after middleware runs.
        
        // If no framework, you might manually call the controller method here, 
        // but that tightly couples middleware and routing.
        echo "/* Middleware passed, but routing/handler logic needs implementation */";
        exit();
    }
} 