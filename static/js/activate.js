/**
 * Product Key Activation JavaScript
 * Handles UI interactions and API calls for key activation
 */
document.addEventListener('DOMContentLoaded', function() {
    const activationForm = document.getElementById('activation-form');
    const keySegments = document.querySelectorAll('.key-segment');
    const submitButton = document.querySelector('button[type="submit"]');
    const errorMessage = document.getElementById('activationError');
    
    // Auto-focus first segment on load
    if (keySegments.length > 0) {
        keySegments[0].focus();
    }
    
    // Handle key input and navigation
    keySegments.forEach((segment, index) => {
        // Handle input
        segment.addEventListener('input', function(e) {
            // Convert to uppercase and remove invalid characters
            this.value = this.value.replace(/[^2-9A-HJ-NP-Z]/g, '').toUpperCase();
            
            // Auto-advance to next segment when complete
            if (this.value.length === 5 && index < keySegments.length - 1) {
                keySegments[index + 1].focus();
            }
            
            // Enable/disable submit button based on completion
            submitButton.disabled = !isKeyComplete();
        });
        
        // Handle keyboard navigation
        segment.addEventListener('keydown', function(e) {
            switch (e.key) {
                case 'Backspace':
                    // Move to previous segment if current is empty
                    if (this.value.length === 0 && index > 0) {
                        keySegments[index - 1].focus();
                    }
                    break;
                
                case 'ArrowLeft':
                    // Move to previous segment
                    if (index > 0) {
                        keySegments[index - 1].focus();
                    }
                    break;
                
                case 'ArrowRight':
                    // Move to next segment
                    if (index < keySegments.length - 1) {
                        keySegments[index + 1].focus();
                    }
                    break;
                
                case 'v':
                    // Handle paste
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        handlePaste();
                    }
                    break;
            }
        });
    });
    
    // Handle paste functionality
    async function handlePaste() {
        try {
            const text = await navigator.clipboard.readText();
            const cleaned = text.replace(/[^2-9A-HJ-NP-Z-]/g, '').toUpperCase();
            const parts = cleaned.split('-');
            
            // If we have a properly formatted key
            if (parts.length === 5 && parts.every(part => part.length === 5)) {
                parts.forEach((part, i) => {
                    keySegments[i].value = part;
                });
                keySegments[4].focus();
                submitButton.disabled = !isKeyComplete();
            }
        } catch (error) {
            console.error('Failed to read clipboard:', error);
        }
    }
    
    // Check if key is complete
    function isKeyComplete() {
        return Array.from(keySegments).every(segment => 
            segment.value.length === 5 && /^[2-9A-HJ-NP-Z]{5}$/.test(segment.value)
        );
    }
    
    // Handle form submission
    activationForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!isKeyComplete()) {
            showError('Please enter a valid product key');
            return;
        }
        
        // Disable form during submission
        submitButton.disabled = true;
        submitButton.innerHTML = '<span class="loading-spinner"></span> Activating...';
        
        try {
            // Construct the full key
            const key = Array.from(keySegments)
                .map(segment => segment.value)
                .join('-');
            
            // Send activation request
            const response = await fetch('/api/v1/activate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ key })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Redirect to success page
                window.location.href = `/advertiser/activation-success?amount=${result.amount}`;
            } else {
                showError(result.error || 'Activation failed. Please try again.');
                submitButton.disabled = !isKeyComplete();
            }
        } catch (error) {
            showError('Network error occurred. Please check your connection and try again.');
            console.error('Activation error:', error);
        } finally {
            submitButton.innerHTML = 'Activate';
        }
    });
    
    // Show error message with shake animation
    function showError(message) {
        errorMessage.textContent = message;
        errorMessage.style.display = 'block';
        
        // Apply shake animation
        errorMessage.classList.remove('shake');
        void errorMessage.offsetWidth; // Force reflow
        errorMessage.classList.add('shake');
        
        // Play error sound
        const errorSound = new Audio('/static/sounds/error.mp3');
        errorSound.volume = 0.3;
        errorSound.play().catch(() => {}); // Ignore if sound fails to play
    }
});
