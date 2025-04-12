<?php

namespace App\Middleware;

use Exception;

/**
 * Basic CSRF Protection Middleware using session tokens.
 */
class CsrfMiddleware
{
    private $sessionKey = 'csrf_token';
    private $formKey = '_csrf_token'; // Name of the hidden input field

    public function __construct() 
    {
        // Ensure session is started (AuthService usually handles this, but good practice here too)
        if (session_status() === PHP_SESSION_NONE) {
            // Use secure session settings if possible
             session_set_cookie_params([
                 'lifetime' => 0, 'path' => '/', 'domain' => '', 
                 'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 
                 'httponly' => true, 'samesite' => 'Lax'
             ]);
            session_start();
        }
    }

    /**
     * Middleware invokable method.
     *
     * For GET requests, generates/renews the token.
     * For POST/PUT/DELETE requests, validates the submitted token.
     *
     * @param mixed $request Framework-specific request object.
     * @param mixed $handler Framework-specific next handler.
     * @return mixed Framework-specific response object.
     */
    public function __invoke($request, $handler) // Adjust signature as needed
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; // Get request method

        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            // Validate token for state-changing requests
            $submittedToken = $_POST[$this->formKey] ?? null;
            $sessionToken = $_SESSION[$this->sessionKey] ?? null;

            if (empty($submittedToken) || empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
                // Token mismatch or missing - CSRF attempt likely
                error_log("CSRF Token Validation Failed. Submitted: {$submittedToken}, Session: {$sessionToken}");
                return $this->forbiddenResponse('Invalid security token. Please try again.');
            }
            // Token is valid, remove it after use (single use per request)
             unset($_SESSION[$this->sessionKey]);
             
        } else {
             // For GET requests, ensure a token exists (generate if needed)
             $this->getToken();
        }

        // Proceed to the next handler
        return $this->callNextHandler($request, $handler); // Use framework-specific call
    }

    /**
     * Generates or retrieves the current CSRF token from the session.
     *
     * @return string The CSRF token.
     */
    public function getToken(): string
    {
        if (empty($_SESSION[$this->sessionKey])) {
            try {
                $_SESSION[$this->sessionKey] = bin2hex(random_bytes(32));
            } catch (Exception $e) {
                 error_log("Failed to generate CSRF token: " . $e->getMessage());
                 // Handle error appropriately - maybe use a less secure fallback?
                 $_SESSION[$this->sessionKey] = 'fallback_csrf_token_' . time(); 
            }
        }
        return $_SESSION[$this->sessionKey];
    }

    /**
     * Gets the name of the hidden input field for the token.
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->formKey;
    }
    
    /**
     * Generates the HTML hidden input field for the CSRF token.
     *
     * @return string
     */
    public function getFormField(): string
    {
         return sprintf('<input type="hidden" name="%s" value="%s">', 
             $this->getFormKey(), 
             htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8')
         );
    }

    // --- Response helpers (Adapt from AuthMiddleware or BaseApiController) ---
    protected function forbiddenResponse(string $message = 'Forbidden')
    {
        http_response_code(403);
        header('Content-Type: application/json'); // Assume API context
        echo json_encode(['error' => $message]);
        exit();
    }

    // Placeholder for calling the next handler (Adapt from AuthMiddleware)
    protected function callNextHandler($request, $handler) {
        echo "/* CSRF Middleware passed, routing/handler needs implementation */";
        exit();
    }
} 