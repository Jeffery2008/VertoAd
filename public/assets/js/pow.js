/**
 * Simple Proof-of-Work (PoW) Client-Side Solver
 */

// Function to calculate SHA-256 hash (requires browser support for SubtleCrypto)
async function sha256(message) {
    // encode as UTF-8
    const msgBuffer = new TextEncoder().encode(message);
    // hash the message
    const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
    // convert ArrayBuffer to Array
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    // convert bytes to hex string
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex;
}

/**
 * Solves a PoW challenge.
 * 
 * @param {string} challenge A server-provided challenge string.
 * @param {number} difficulty Number of leading zeros required in the hash.
 * @returns {Promise<{nonce: number, hash: string}|null>} Object with nonce and hash if solved, null otherwise.
 */
async function solvePoW(challenge, difficulty) {
    console.log(`Starting PoW challenge: ${challenge}, difficulty: ${difficulty}`);
    const targetPrefix = '0'.repeat(difficulty);
    let nonce = 0;
    const maxNonce = 1000000; // Limit attempts to prevent freezing browser

    try {
        while (nonce < maxNonce) {
            const attemptString = challenge + nonce;
            const hash = await sha256(attemptString);

            if (hash.startsWith(targetPrefix)) {
                console.log(`PoW solved! Nonce: ${nonce}, Hash: ${hash}`);
                return { nonce: nonce, hash: hash };
            }
            nonce++;
            // Optional: yield control briefly to prevent blocking UI too much
            // if (nonce % 10000 === 0) {
            //     await new Promise(resolve => setTimeout(resolve, 0)); 
            // }
        }
        console.warn(`PoW challenge failed after ${maxNonce} attempts.`);
        return null; // Failed to solve within limits
    } catch (error) {
        console.error("Error during PoW calculation:", error);
        // Fallback or indicate error - e.g., if crypto.subtle is unavailable
        if (error.message.includes("SubtleCrypto") || error.message.includes("TextEncoder")) {
             alert("Your browser does not support the required features for security verification (SubtleCrypto).");
        }
        return null;
    }
}

/**
 * Attaches PoW fields to a form before submission.
 * 
 * @param {HTMLFormElement} form The form being submitted.
 * @param {string} challenge The PoW challenge string.
 * @param {number} difficulty The PoW difficulty.
 * @param {string} statusElementId Optional ID of an element to display status.
 * @returns {Promise<boolean>} True if PoW solved and fields added, false otherwise.
 */
async function attachPoW(form, challenge, difficulty, statusElementId = null) {
    const statusElement = statusElementId ? document.getElementById(statusElementId) : null;
    if (statusElement) {
        statusElement.textContent = 'Performing security verification...';
        statusElement.style.display = 'block';
        statusElement.style.color = 'orange';
    }

    // Remove existing PoW fields if any
    form.querySelectorAll('input[name="pow_challenge"], input[name="pow_nonce"]').forEach(el => el.remove());

    const solution = await solvePoW(challenge, difficulty);

    if (solution) {
        const challengeInput = document.createElement('input');
        challengeInput.type = 'hidden';
        challengeInput.name = 'pow_challenge';
        challengeInput.value = challenge;
        form.appendChild(challengeInput);

        const nonceInput = document.createElement('input');
        nonceInput.type = 'hidden';
        nonceInput.name = 'pow_nonce';
        nonceInput.value = solution.nonce;
        form.appendChild(nonceInput);
        
        if (statusElement) {
             statusElement.textContent = 'Verification complete.';
             statusElement.style.color = 'green';
             // Optionally hide after a delay
             // setTimeout(() => { statusElement.style.display = 'none'; }, 1500);
        }
        return true;
    } else {
        if (statusElement) {
             statusElement.textContent = 'Security verification failed. Please try again.';
             statusElement.style.color = 'red';
        }
        alert('Security verification failed. This might be due to browser limitations or a temporary issue. Please try reloading the page.');
        return false;
    }
} 