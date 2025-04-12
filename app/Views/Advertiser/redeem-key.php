<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redeem Activation Key - VertoAD</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; max-width: 500px; margin: auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { 
            width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; 
            font-family: monospace; text-transform: uppercase;
        }
        button { padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        button:hover { background-color: #218838; }
        .status-message { margin-top: 15px; padding: 10px; border-radius: 4px; display: none; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

    <h1>Redeem Activation Key</h1>
    <p>Enter your activation key (CDKEY) to add duration or credits to your account.</p>

    <form id="redeem-key-form">
        <div class="form-group">
            <label for="activation_key">Activation Key:</label>
            <input type="text" id="activation_key" name="activation_key" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" required>
        </div>
        <!-- PoW Status Placeholder -->
        <div id="pow-status" style="margin-top: 10px; font-weight: bold; display: none;"></div>
        <button type="submit" id="redeem-button">Redeem Key</button>
    </form>

    <div id="status-message" class="status-message"></div>

    <!-- Include PoW Solver Script -->
    <script src="/assets/js/pow.js"></script>

    <script>
        const redeemForm = document.getElementById('redeem-key-form');
        const keyInput = document.getElementById('activation_key');
        const statusMessageDiv = document.getElementById('status-message');
        const redeemButton = document.getElementById('redeem-button');

        // --- Get PoW Data (Injected by PHP) --- 
        const powChallenge = "<?php echo isset($powData['challenge']) ? htmlspecialchars($powData['challenge'], ENT_QUOTES, 'UTF-8') : ''; ?>";
        const powDifficulty = <?php echo isset($powData['difficulty']) ? intval($powData['difficulty']) : 0; ?>;
        
        if (!powChallenge || powDifficulty <= 0) {
             console.error("PoW challenge data not found or invalid.");
             showStatus("Security initialization failed. Please reload the page.", "error");
             redeemButton.disabled = true;
         }

        // --- Authentication --- 
        const authToken = localStorage.getItem('authToken'); 
        if (!authToken) { 
            showStatus('You must be logged in to redeem a key. Redirecting...', 'error');
            setTimeout(() => window.location.href = '/login', 2000);
        }
        const authHeader = authToken ? { 'Authorization': `Bearer ${authToken}` } : {};

        redeemForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            statusMessageDiv.style.display = 'none';
            redeemButton.disabled = true;
            redeemButton.textContent = 'Redeeming...';
            
            const activationKey = keyInput.value.trim().toUpperCase();

            if (!activationKey) {
                showStatus('Please enter an activation key.', 'error');
                 redeemButton.disabled = false;
                 redeemButton.textContent = 'Redeem Key';
                return;
            }
            
            // --- Solve PoW --- 
            if (!powChallenge || powDifficulty <= 0) {
                 showStatus("Security challenge data is missing. Cannot redeem.", "error");
                 redeemButton.disabled = false;
                 redeemButton.textContent = 'Redeem Key';
                 return;
            }
            const powSolved = await attachPoW(redeemForm, powChallenge, powDifficulty, 'pow-status');
             if (!powSolved) {
                 showStatus("Security verification failed. Please try submitting again.", "error");
                 redeemButton.disabled = false;
                 redeemButton.textContent = 'Redeem Key';
                 // TODO: Need new PoW challenge if fails
                 return;
             }
            // --- PoW Solved, Proceed --- 

            const apiUrl = '/api/advertiser/redeem';
            const apiMethod = 'POST';
            const bodyData = { activation_key: activationKey };
            // Add PoW fields from attachPoW to the body data if needed by backend JSON parsing
            // (Alternatively, backend reads from $_POST directly, including hidden fields)
            // Assuming PoWMiddleware reads from $_POST, no need to add here.

            try {
                const response = await fetch(apiUrl, {
                    method: apiMethod,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...authHeader
                    },
                    body: JSON.stringify(bodyData) // Send only the key in JSON
                });

                const result = await response.json();

                if (response.ok) {
                    // Display specific success message based on redemption type if available
                    let successMsg = result.message || 'Key redeemed successfully!';
                    if(result.redeemed_value && result.redeemed_type) {
                        successMsg += ` Type: ${result.redeemed_type}, Value: ${result.redeemed_value}.`;
                    }
                    showStatus(successMsg, 'success');
                    redeemForm.reset(); // Clear the form
                    document.getElementById('pow-status').style.display = 'none'; // Hide PoW status
                     // TODO: Need a new PoW challenge for next attempt
                     redeemButton.disabled = true; // Prevent resubmit until page reload for new challenge
                     redeemButton.textContent = 'Reload page to redeem another';

                } else {
                    showStatus(`Error: ${result.error || 'Failed to redeem key.'} (Status: ${response.status})`, 'error');
                     redeemButton.disabled = false; // Re-enable on error
                     redeemButton.textContent = 'Redeem Key';
                     // TODO: Handle PoW failure - need new challenge
                }
            } catch (error) {
                console.error('Error redeeming key:', error);
                showStatus('An unexpected network error occurred. Please check the console.', 'error');
                 redeemButton.disabled = false; // Re-enable on network error
                 redeemButton.textContent = 'Redeem Key';
            }
        });

        function showStatus(message, type = 'info') {
            statusMessageDiv.textContent = message;
            statusMessageDiv.className = `status-message ${type}`;
            statusMessageDiv.style.display = 'block';
        }
    </script>

</body>
</html> 