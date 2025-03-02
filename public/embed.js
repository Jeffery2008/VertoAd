/**
 * VertoAD - Ad Embedding Script
 * Usage: <div class="verto-ad" data-size="300x250"></div>
 */

(function() {
    // Configuration
    const config = {
        baseUrl: window.location.protocol + '//' + window.location.hostname + (window.location.port ? ':' + window.location.port : ''),
        sizes: {
            '300x250': { width: 300, height: 250 },
            '728x90': { width: 728, height: 90 },
            '160x600': { width: 160, height: 600 }
        }
    };

    // Create and inject iframe
    function createAdFrame(element, size) {
        const iframe = document.createElement('iframe');
        iframe.style.width = size.width + 'px';
        iframe.style.height = size.height + 'px';
        iframe.style.border = 'none';
        iframe.style.overflow = 'hidden';
        iframe.scrolling = 'no';
        
        // Add a random query parameter to prevent caching
        const cacheBuster = Math.random().toString(36).substring(7);
        iframe.src = `${config.baseUrl}/api/serve?size=${size.width}x${size.height}&cb=${cacheBuster}`;
        
        element.appendChild(iframe);
    }

    // Initialize ads
    function initAds() {
        const adElements = document.getElementsByClassName('verto-ad');
        
        for (let element of adElements) {
            const sizeAttr = element.getAttribute('data-size');
            const size = config.sizes[sizeAttr];
            
            if (size) {
                createAdFrame(element, size);
            } else {
                console.error(`Invalid ad size: ${sizeAttr}`);
            }
        }
    }

    // Load ads when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAds);
    } else {
        initAds();
    }
})(); 