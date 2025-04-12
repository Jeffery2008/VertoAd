<?php

namespace App\Services;

use PDO;
use Exception;

class AuthService
{
    protected $db;

    // Inject PDO connection
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->startSession();
    }

    /**
     * Ensures the session is started securely.
     */
    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0, // Session cookie
                'path' => '/',
                'domain' => '', // Adjust if needed for subdomains
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', // True if HTTPS
                'httponly' => true, // Prevent JS access
                'samesite' => 'Lax' // Mitigate CSRF
            ]);
            session_start();
        }
    }

    /**
     * Attempts to log in a user.
     *
     * @param string $emailOrUsername
     * @param string $password
     * @return bool True on successful login, false otherwise.
     */
    public function login(string $emailOrUsername, string $password): bool
    {
        try {
            $sql = "SELECT id, password_hash, role FROM users WHERE email = :identifier OR username = :identifier LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':identifier', $emailOrUsername);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // Password matches - Regenerate session ID for security
                session_regenerate_id(true);

                // Store user info in session
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['last_activity'] = time(); // For potential timeout

                return true;
            } else {
                // Login failed (user not found or password mismatch)
                return false;
            }
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logs out the current user.
     */
    public function logout(): void
    {
        $this->startSession(); // Ensure session is active
        $_SESSION = []; // Unset all session variables

        // If using session cookies, invalidate it
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Checks if a user is currently authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        $this->startSession();
        // Optional: Add session timeout check
        // if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
        //     $this->logout();
        //     return false;
        // }
        // $_SESSION['last_activity'] = time(); // Update activity time
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    /**
     * Gets the ID of the currently logged-in user.
     *
     * @return int|null User ID or null if not authenticated.
     */
    public function getCurrentUserId(): ?int
    {
        return $this->isAuthenticated() ? (int)$_SESSION['user_id'] : null;
    }

    /**
     * Gets the role of the currently logged-in user.
     *
     * @return string|null User role ('admin', 'advertiser', 'publisher') or null if not authenticated.
     */
    public function getCurrentUserRole(): ?string
    {
        return $this->isAuthenticated() ? $_SESSION['user_role'] : null;
    }

    // Optional: Method to get full user details if needed
    // public function getCurrentUser(): ?array { ... fetch from DB using ID ... }
} 