<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <h1>Register</h1>

    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" action="/register" id="register-form">
        <div class="form-group">
            <label for="role">Register As:</label>
            <select id="role" name="role" required>
                <option value="advertiser">Advertiser</option>
                <option value="publisher">Publisher</option>
            </select>
        </div>

        <!-- PoW Status Placeholder -->
        <div id="pow-status" style="margin-top: 10px; font-weight: bold; display: none;"></div>

        <div>
            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
        </div>
        <div>
            <label for="password_confirmation">Confirm Password:</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required>
        </div>
        <div>
            <button type="submit" id="register-button">Register</button>
        </div>
    </form>

    <div id="status-message" class="status-message"></div>

    <!-- Include PoW Solver Script -->
    <script src="/assets/js/pow.js"></script>

    <script>
        const registerForm = document.getElementById('register-form');
        const statusMessageDiv = document.getElementById('status-message');
        const registerButton = document.getElementById('register-button');

        // Get PoW challenge data passed from PHP (ensure this is done securely)
        // IMPORTANT: This assumes PHP injects these variables into the JS scope.
        // Use data attributes or a dedicated JS config object for better practice.
        const powChallenge = "<?php echo isset($powData['challenge']) ? htmlspecialchars($powData['challenge'], ENT_QUOTES, 'UTF-8') : ''; ?>";
        const powDifficulty = <?php echo isset($powData['difficulty']) ? intval($powData['difficulty']) : 0; ?>;
        
        if (!powChallenge || powDifficulty <= 0) {
             console.error("PoW challenge data not found or invalid.");
             showStatus("Security initialization failed. Please reload the page.", "error");
             registerButton.disabled = true;
         }

        registerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            statusMessageDiv.style.display = 'none';
            registerButton.disabled = true;
            registerButton.textContent = 'Registering...';

            // --- Solve PoW before submitting --- 
            if (!powChallenge || powDifficulty <= 0) {
                 showStatus("Security challenge data is missing. Cannot register.", "error");
                 registerButton.disabled = false;
                 registerButton.textContent = 'Register';
                 return;
            }
            const powSolved = await attachPoW(registerForm, powChallenge, powDifficulty, 'pow-status');
            if (!powSolved) {
                showStatus("Security verification failed. Please try submitting again.", "error");
                registerButton.disabled = false;
                registerButton.textContent = 'Register';
                 // Regenerate challenge? Might be complex here. Reload might be best.
                return;
            }
            // --- PoW Solved, Proceed with API Call --- 

            const formData = new FormData(registerForm);
            const data = {};
            formData.forEach((value, key) => data[key] = value);

            const apiUrl = '/api/auth/register';

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data) // Includes hidden pow_challenge and pow_nonce
                });

                const result = await response.json();

                if (response.ok) {
                    showStatus('Registration successful! Redirecting to login...', 'success');
                    setTimeout(() => window.location.href = '/login', 2000);
                } else {
                    showStatus(`Error: ${result.error || 'Registration failed.'}`, 'error');
                     // Re-enable form, might need new PoW challenge
                     registerButton.disabled = false;
                     registerButton.textContent = 'Register';
                     // TODO: Consider how to handle failed registration + PoW (needs new challenge)
                }
            } catch (error) {
                console.error('Error during registration:', error);
                showStatus('An unexpected network error occurred. Please try again.', 'error');
                registerButton.disabled = false;
                registerButton.textContent = 'Register';
            }
        });

        function showStatus(message, type = 'info') {
            // ... (showStatus function remains the same) ...
             statusMessageDiv.textContent = message;
             statusMessageDiv.className = `status-message ${type}`;
             statusMessageDiv.style.display = 'block';
        }
    </script>

</body>
</html> 