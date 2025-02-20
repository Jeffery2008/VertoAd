/**
 * Client-side implementation of Proof of Work challenge for login security
 */
class ProofOfWorkClient {
    constructor() {
        this.worker = null;
        this.currentChallenge = null;
    }

    /**
     * Initialize Web Worker for PoW computation
     */
    initWorker() {
        const workerCode = `
            self.onmessage = function(e) {
                const { username, nonce, difficulty } = e.data;
                let solution = 0;
                
                while (true) {
                    const hash = sha256(username + nonce + solution);
                    const leadingZeros = hash.substring(0, difficulty);
                    
                    if (leadingZeros === '0'.repeat(difficulty)) {
                        self.postMessage({ solution });
                        break;
                    }
                    solution++;
                    
                    // Allow interruption every 1000 iterations
                    if (solution % 1000 === 0) {
                        if (self.interrupted) break;
                    }
                }
            };

            // SHA-256 implementation
            function sha256(message) {
                const msgBuffer = new TextEncoder().encode(message);
                return crypto.subtle.digest('SHA-256', msgBuffer)
                    .then(hashBuffer => {
                        const hashArray = Array.from(new Uint8Array(hashBuffer));
                        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                    });
            }
        `;

        const blob = new Blob([workerCode], { type: 'application/javascript' });
        this.worker = new Worker(URL.createObjectURL(blob));
    }

    /**
     * Start solving PoW challenge
     * @param {Object} challenge - Challenge data from server
     * @param {Function} onComplete - Callback for solution
     */
    async solveChallenge(challenge, onComplete) {
        if (!this.worker) {
            this.initWorker();
        }

        this.currentChallenge = challenge;
        
        this.worker.onmessage = (e) => {
            const { solution } = e.data;
            onComplete({
                username: challenge.username,
                nonce: challenge.nonce,
                solution: solution
            });
        };

        this.worker.postMessage({
            username: challenge.username,
            nonce: challenge.nonce,
            difficulty: challenge.difficulty
        });
    }

    /**
     * Stop current PoW computation
     */
    stopSolving() {
        if (this.worker) {
            this.worker.interrupted = true;
            this.worker.terminate();
            this.worker = null;
        }
        this.currentChallenge = null;
    }
}

/**
 * Handle login form submission with PoW
 */
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const submitButton = loginForm?.querySelector('button[type="submit"]');
    const powClient = new ProofOfWorkClient();

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Computing proof...';
            }

            const username = loginForm.querySelector('input[name="username"]').value;
            const password = loginForm.querySelector('input[name="password"]').value;

            try {
                // Get challenge from server
                const challengeResponse = await fetch('/api/auth/challenge', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username })
                });
                
                const challenge = await challengeResponse.json();

                // Solve challenge
                powClient.solveChallenge(challenge, async (solution) => {
                    try {
                        // Submit login with solution
                        const loginResponse = await fetch('/api/auth/login', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                username,
                                password,
                                nonce: solution.nonce,
                                solution: solution.solution
                            })
                        });

                        const result = await loginResponse.json();
                        
                        if (result.success) {
                            window.location.href = '/dashboard';
                        } else {
                            throw new Error(result.message || 'Login failed');
                        }
                    } catch (error) {
                        alert('Login failed: ' + error.message);
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = 'Login';
                        }
                    }
                });
            } catch (error) {
                alert('Error during login: ' + error.message);
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Login';
                }
            }
        });
    }
});

// CSRF token handling
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/**
 * Add CSRF token to all fetch requests
 */
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    if (csrfToken) {
        options.headers = {
            ...options.headers,
            'X-CSRF-Token': csrfToken
        };
    }
    return originalFetch(url, options);
};
