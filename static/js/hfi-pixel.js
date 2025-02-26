/**
 * HFI Conversion Tracking Pixel
 * 
 * This script handles tracking conversion events from advertiser websites
 * back to the HFI Utility Center Ad platform.
 */
(function() {
    // Get base URL from the script tag
    var scriptTag = document.currentScript;
    var baseUrl = scriptTag.src.split('/static/')[0];
    
    // Create the tracking function
    window.HFITrack = function(options) {
        var params = options || {};
        
        // Get pixel ID from the script tag attribute
        if (scriptTag.hasAttribute('data-pixel-id')) {
            params.pixel_id = scriptTag.getAttribute('data-pixel-id');
        }
        
        // If no pixel ID is provided, we can't track
        if (!params.pixel_id) {
            console.error('HFI Conversion Tracking Error: Missing pixel_id');
            return false;
        }
        
        // Get tracking values from URL parameters
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('ad_id')) params.ad_id = urlParams.get('ad_id');
        if (urlParams.has('click_id')) params.click_id = urlParams.get('click_id');
        
        // If we don't have an ad_id, check for stored ad_id in cookie
        if (!params.ad_id) {
            var storedAdId = getCookie('hfi_ad_id');
            if (storedAdId) params.ad_id = storedAdId;
        }
        
        // If we don't have a click_id, check for stored click_id in cookie
        if (!params.click_id) {
            var storedClickId = getCookie('hfi_click_id');
            if (storedClickId) params.click_id = storedClickId;
        }
        
        // Store ad_id and click_id in cookies for cross-page tracking
        if (params.ad_id) setCookie('hfi_ad_id', params.ad_id, 30); // 30 days
        if (params.click_id) setCookie('hfi_click_id', params.click_id, 30); // 30 days
        
        // Append query string
        var queryString = Object.keys(params).map(function(key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }).join('&');
        
        // Create the tracking pixel
        var img = new Image(1, 1);
        img.src = baseUrl + '/api/v1/track/conversion?' + queryString;
        img.style.display = 'none';
        document.body.appendChild(img);
        
        return true;
    };
    
    // Helper function to get cookie value
    function getCookie(name) {
        var nameEQ = name + "=";
        var ca = document.cookie.split(';');
        for(var i=0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    // Helper function to set cookie value
    function setCookie(name, value, days) {
        var expires = "";
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + value + expires + "; path=/";
    }
    
    // Set up automatic tracking if specified with pixel attributes
    if (scriptTag.hasAttribute('data-auto-track')) {
        document.addEventListener('DOMContentLoaded', function() {
            var options = {};
            
            // Get value if specified
            if (scriptTag.hasAttribute('data-value')) {
                options.value = scriptTag.getAttribute('data-value');
            }
            
            // Get order ID if specified
            if (scriptTag.hasAttribute('data-order-id')) {
                options.order_id = scriptTag.getAttribute('data-order-id');
            }
            
            window.HFITrack(options);
        });
    }
})(); 