/**
 * HFI Conversion Tracking Pixel
 * 
 * This script handles conversion tracking for the VertoAD Ad platform.
 * It should be included on advertiser websites to track conversions.
 */
(function() {
    // Configuration
    const VertoAD_TRACKING_URL = 'https://your-domain.com/api/v1/track/conversion';
    const COOKIE_NAME = 'vertoad_visitor_id';
    const COOKIE_EXPIRY = 30; // days
    
    /**
     * Get or create visitor ID from cookie
     * 
     * @returns {string} Visitor ID
     */
    function getVisitorId() {
        let visitorId = getCookie(COOKIE_NAME);
        
        if (!visitorId) {
            visitorId = generateVisitorId();
            setCookie(COOKIE_NAME, visitorId, COOKIE_EXPIRY);
        }
        
        return visitorId;
    }
    
    /**
     * Generate a random visitor ID
     * 
     * @returns {string} Generated visitor ID
     */
    function generateVisitorId() {
        return 'v_' + Math.random().toString(36).substring(2, 15) + 
               Math.random().toString(36).substring(2, 15) + 
               '_' + Date.now();
    }
    
    /**
     * Set a cookie
     * 
     * @param {string} name Cookie name
     * @param {string} value Cookie value
     * @param {number} days Expiry in days
     */
    function setCookie(name, value, days) {
        let expires = '';
        
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        
        document.cookie = name + '=' + encodeURIComponent(value) + expires + '; path=/; SameSite=Lax';
    }
    
    /**
     * Get a cookie value
     * 
     * @param {string} name Cookie name
     * @returns {string|null} Cookie value or null if not found
     */
    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        
        return null;
    }
    
    /**
     * Get URL parameter value
     * 
     * @param {string} name Parameter name
     * @returns {string|null} Parameter value or null if not found
     */
    function getUrlParam(name) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    
    /**
     * Track a conversion
     * 
     * @param {string} pixelId Conversion pixel ID
     * @param {Object} options Additional tracking options
     */
    function trackConversion(pixelId, options = {}) {
        if (!pixelId) {
            console.error('VertoAD Pixel Error: Missing pixel ID');
            return;
        }
        
        const visitorId = getVisitorId();
        const clickId = getUrlParam('vertoad_click_id') || getCookie('vertoad_click_id');
        
        // Prepare tracking data
        const data = {
            pixel_id: pixelId,
            visitor_id: visitorId,
            click_id: clickId || null,
            order_id: options.orderId || null,
            value: options.value || null
        };
        
        // Send tracking request
        const img = new Image(1, 1);
        img.style.display = 'none';
        img.onload = function() {
            if (typeof options.onSuccess === 'function') {
                options.onSuccess();
            }
        };
        img.onerror = function() {
            console.error('VertoAD Pixel Error: Failed to track conversion');
            if (typeof options.onError === 'function') {
                options.onError();
            }
        };
        
        // Build query string
        const queryString = Object.keys(data)
            .filter(key => data[key] !== null && data[key] !== undefined)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(data[key]))
            .join('&');
        
        img.src = VertoAD_TRACKING_URL + '?' + queryString;
        document.body.appendChild(img);
    }
    
    /**
     * Auto-track conversion if pixel ID is provided in script tag
     */
    function autoTrack() {
        // Find the script tag
        const scripts = document.getElementsByTagName('script');
        let pixelId = null;
        let value = null;
        let orderId = null;
        
        for (let i = 0; i < scripts.length; i++) {
            const script = scripts[i];
            
            if (script.src && script.src.includes('vertoad-pixel.js')) {
                // Parse data attributes
                pixelId = script.getAttribute('data-pixel-id');
                value = script.getAttribute('data-value');
                orderId = script.getAttribute('data-order-id');
                break;
            }
        }
        
        if (pixelId) {
            // Track conversion with provided data
            trackConversion(pixelId, {
                value: value,
                orderId: orderId
            });
        }
    }
    
    // Store click ID from URL parameter if present
    const clickId = getUrlParam('vertoad_click_id');
    if (clickId) {
        setCookie('vertoad_click_id', clickId, 30);
    }
    
    // Expose global functions
    window.VertoADPixel = {
        trackConversion: trackConversion,
        getVisitorId: getVisitorId
    };
    
    // Auto-track if the script has a pixel ID attribute
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(autoTrack, 1);
    } else {
        document.addEventListener('DOMContentLoaded', autoTrack);
    }
})(); 