<?php

namespace App\Middleware;

use Exception;
use App\Core\Request;
use App\Core\Http\Response; // Assuming Response class exists

/**
 * Basic Proof-of-Work (PoW) Verification Middleware.
 *
 * Generates challenges (stored in session) for GET requests to protected forms.
 * Verifies submitted nonce and challenge for POST requests.
 */
class PoWMiddleware
{
    private $sessionKey = 'pow_challenge';
    private $challengeExpiry = 300; // Challenge valid for 5 minutes
    private $difficulty = 4; // Number of leading zeros required (adjust as needed)

    public function __construct() 
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(); // Ensure session is available
        }
    }

    /**
     * Middleware invokable method.
     */
    public function __invoke(Request $request, callable $next): Response
    {
        $method = $request->getMethod();

        if ($method === 'POST') {
            // Verify PoW for POST requests
            $submittedChallenge = $request->getBodyParam('pow_challenge');
            $submittedNonce = $request->getBodyParam('pow_nonce');
            $sessionChallengeData = $_SESSION[$this->sessionKey] ?? null;

            if (!$this->verifyPoW($submittedChallenge, $submittedNonce, $sessionChallengeData)) {
                unset($_SESSION[$this->sessionKey]); // Clear invalid session challenge
                return new Response(['error' => 'Security verification failed (Invalid PoW).'], 403);
            }
            // PoW verified, clear the used challenge
            unset($_SESSION[$this->sessionKey]);
            
        } 
        // Note: Challenge generation needs to happen when rendering the form (e.g., in the controller/route handler for the GET request)

        // Proceed to the next handler
        return $next($request);
    }

    /**
     * Generates a new PoW challenge and stores it in the session.
     * Should be called when rendering the form.
     *
     * @return array Containing 'challenge' string and 'difficulty' number.
     */
    public function generateChallenge(): array
    {
        try {
            $challenge = bin2hex(random_bytes(16));
            $_SESSION[$this->sessionKey] = [
                'challenge' => $challenge,
                'timestamp' => time(),
                'difficulty' => $this->difficulty
            ];
            return [
                'challenge' => $challenge,
                'difficulty' => $this->difficulty
            ];
        } catch (Exception $e) {
            error_log("Failed to generate PoW challenge: " . $e->getMessage());
            // Fallback or re-throw - crucial security component
            throw new Exception("Could not generate security challenge.");
        }
    }

    /**
     * Verifies the submitted PoW solution.
     *
     * @param string|null $submittedChallenge
     * @param string|null $submittedNonce
     * @param array|null $sessionChallengeData
     * @return bool
     */
    private function verifyPoW(?string $submittedChallenge, ?string $submittedNonce, ?array $sessionChallengeData): bool
    {
        if (empty($submittedChallenge) || !isset($submittedNonce) || empty($sessionChallengeData)) {
             error_log("PoW Verify Fail: Missing submitted data or session challenge.");
            return false;
        }

        $challenge = $sessionChallengeData['challenge'];
        $timestamp = $sessionChallengeData['timestamp'];
        $difficulty = $sessionChallengeData['difficulty'];

        // 1. Check if challenge matches session
        if ($submittedChallenge !== $challenge) {
            error_log("PoW Verify Fail: Challenge mismatch.");
            return false;
        }

        // 2. Check for expiry
        if (time() - $timestamp > $this->challengeExpiry) {
            error_log("PoW Verify Fail: Challenge expired.");
            return false;
        }

        // 3. Check nonce is numeric
        if (!is_numeric($submittedNonce)) {
             error_log("PoW Verify Fail: Nonce is not numeric.");
             return false;
        }

        // 4. Verify the hash
        $attemptString = $challenge . $submittedNonce;
        $hash = hash('sha256', $attemptString);
        $targetPrefix = str_repeat('0', $difficulty);

        if (substr($hash, 0, $difficulty) !== $targetPrefix) {
            error_log("PoW Verify Fail: Hash does not meet difficulty. Hash: {$hash}, Nonce: {$submittedNonce}, Challenge: {$challenge}");
            return false;
        }
        
        // All checks passed
        error_log("PoW Verify OK. Nonce: {$submittedNonce}, Challenge: {$challenge}");
        return true;
    }

} 