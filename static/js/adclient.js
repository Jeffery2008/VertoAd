/**
 * HFI Ad Client Library
 * Embeds advertisements into websites
 */

(function(window, document) {
    'use strict';
    
    // Configuration
    var config = {
        apiBaseUrl: 'https://yourdomain.com/api/v1', // Replace with your actual domain
        scriptId: 'hfi-ad-client',
        trackImpression: true,
        trackClicks: true
    };
    
    // Get the script URL to determine the API base URL
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
        var src = scripts[i].src;
        if (src && src.indexOf('adclient.js') !== -1) {
            // Extract the domain from the script src
            var urlParts = src.split('/');
            urlParts.pop(); // Remove adclient.js
            urlParts.pop(); // Remove 'js'
            urlParts.pop(); // Remove 'static'
            config.apiBaseUrl = urlParts.join('/') + '/api/v1';
            config.scriptId = scripts[i].id || config.scriptId;
            
            // Check for data attributes on the script tag
            if (scripts[i].dataset) {
                if (scripts[i].dataset.trackImpression !== undefined) {
                    config.trackImpression = scripts[i].dataset.trackImpression !== 'false';
                }
                if (scripts[i].dataset.trackClicks !== undefined) {
                    config.trackClicks = scripts[i].dataset.trackClicks !== 'false';
                }
            }
            break;
        }
    }
    
    /**
     * Main HFI Ad Client
     */
    var HFIAdClient = {
        // Store impression IDs for each ad
        impressions: {},
        
        /**
         * Initialize the client
         */
        init: function() {
            // Find all ad placeholders
            this.findAndLoadAds();
            
            // Register for window resize events
            window.addEventListener('resize', this.handleResize.bind(this));
        },
        
        /**
         * Find all ad containers and load ads into them
         */
        findAndLoadAds: function() {
            var adContainers = document.querySelectorAll('.hfi-ad');
            for (var i = 0; i < adContainers.length; i++) {
                this.loadAd(adContainers[i]);
            }
        },
        
        /**
         * Load an ad into a container
         * 
         * @param {HTMLElement} container The ad container element
         */
        loadAd: function(container) {
            // Skip if already loaded
            if (container.dataset.loaded === 'true') {
                return;
            }
            
            // Get position ID from data attribute
            var positionId = container.dataset.positionId;
            if (!positionId) {
                console.error('HFI Ad: Missing position ID');
                return;
            }
            
            // Mark as loading
            container.dataset.loaded = 'loading';
            
            // Make API request to get the ad
            this.fetchAd(positionId, function(error, response) {
                if (error || !response.success) {
                    console.error('HFI Ad: Failed to load ad', error);
                    container.dataset.loaded = 'error';
                    return;
                }
                
                // Render the ad
                this.renderAd(container, response.ad);
                
                // Mark as loaded
                container.dataset.loaded = 'true';
                
                // Track impression
                if (config.trackImpression && response.ad && response.ad.id) {
                    this.trackImpression(container, response.ad.id);
                }
            }.bind(this));
        },
        
        /**
         * Fetch an ad from the API
         * 
         * @param {string} positionId The ad position ID
         * @param {function} callback Callback function(error, response)
         */
        fetchAd: function(positionId, callback) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', config.apiBaseUrl + '/serve.php?position_id=' + positionId, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            callback(null, response);
                        } catch (e) {
                            callback('Invalid response', null);
                        }
                    } else {
                        callback('HTTP error: ' + xhr.status, null);
                    }
                }
            };
            xhr.send();
        },
        
        /**
         * Render an ad into a container
         * 
         * @param {HTMLElement} container The ad container
         * @param {object} ad The ad data
         */
        renderAd: function(container, ad) {
            if (!ad) {
                container.innerHTML = '<div class="hfi-ad-empty">No ad available</div>';
                return;
            }
            
            // Store the ad ID on the container
            container.dataset.adId = ad.id;
            
            // Set container size
            container.style.width = ad.width + 'px';
            container.style.height = ad.height + 'px';
            container.style.position = 'relative';
            container.style.overflow = 'hidden';
            
            // Process HTML to add click tracking
            var html = ad.html;
            if (config.trackClicks) {
                html = this.processAdHtmlForClickTracking(html, ad.id, ad.destination_url);
            }
            
            // Insert ad HTML
            container.innerHTML = html;
            
            // Add click handlers after rendering
            if (config.trackClicks) {
                this.setupClickTracking(container, ad.id, ad.destination_url);
            }
            
            // Record viewability events
            this.setupViewabilityTracking(container, ad.id);
        },
        
        /**
         * Process ad HTML to wrap links with click tracking
         * 
         * @param {string} html The ad HTML
         * @param {string} adId The ad ID
         * @param {string} destinationUrl The ad destination URL
         * @return {string} Processed HTML
         */
        processAdHtmlForClickTracking: function(html, adId, destinationUrl) {
            // Create a temporary element to parse the HTML
            var tempElement = document.createElement('div');
            tempElement.innerHTML = html;
            
            // Find all links
            var links = tempElement.getElementsByTagName('a');
            for (var i = 0; i < links.length; i++) {
                var link = links[i];
                var href = link.getAttribute('href');
                
                // If there's a link with an href attribute
                if (href) {
                    // Store the original URL
                    link.setAttribute('data-original-url', href);
                    
                    // Make link open in a new tab for better user experience
                    if (!link.getAttribute('target')) {
                        link.setAttribute('target', '_blank');
                    }
                    
                    // Add click event handler
                    link.setAttribute('onclick', 'return HFIAdClient.handleAdClick(this, event);');
                }
            }
            
            return tempElement.innerHTML;
        },
        
        /**
         * Set up click tracking for an ad container
         * 
         * @param {HTMLElement} container The ad container
         * @param {string} adId The ad ID
         * @param {string} destinationUrl The ad destination URL
         */
        setupClickTracking: function(container, adId, destinationUrl) {
            // Handle container clicks if there are no links inside
            var hasLinks = container.getElementsByTagName('a').length > 0;
            
            if (!hasLinks) {
                container.style.cursor = 'pointer';
                container.addEventListener('click', function(event) {
                    this.recordClick(adId, destinationUrl);
                    window.open(destinationUrl, '_blank');
                }.bind(this));
            }
        },
        
        /**
         * Handle ad click
         * 
         * @param {HTMLElement} element The clicked element
         * @param {Event} event The click event
         * @return {boolean} Whether to allow the default action
         */
        handleAdClick: function(element, event) {
            // Get data attributes
            var adId = element.closest('.hfi-ad').dataset.adId;
            var originalUrl = element.getAttribute('data-original-url');
            
            if (adId && originalUrl) {
                // Prevent default action (we'll handle it)
                event.preventDefault();
                
                // Record the click
                this.recordClick(adId, originalUrl);
                
                // Open the destination URL in a new tab
                window.open(originalUrl, '_blank');
                return false;
            }
            
            // Allow default action if not an ad link
            return true;
        },
        
        /**
         * Record a click event
         * 
         * @param {string} adId The ad ID
         * @param {string} destinationUrl The destination URL
         */
        recordClick: function(adId, destinationUrl) {
            // Get the impression ID if available
            var impressionId = this.impressions[adId];
            
            // Prepare the tracking URL
            var trackingUrl = config.apiBaseUrl + '/click.php?';
            
            if (impressionId) {
                trackingUrl += 'impression_id=' + encodeURIComponent(impressionId);
            } else {
                trackingUrl += 'ad_id=' + encodeURIComponent(adId);
            }
            
            trackingUrl += '&url=' + encodeURIComponent(destinationUrl);
            
            // Send the tracking request (use image to avoid CORS issues)
            var img = new Image();
            img.src = trackingUrl;
            
            // Log the click
            console.log('HFI Ad: Click tracked', {
                adId: adId,
                impressionId: impressionId,
                destinationUrl: destinationUrl
            });
        },
        
        /**
         * Track an impression
         * 
         * @param {HTMLElement} container The ad container
         * @param {string} adId The ad ID
         */
        trackImpression: function(container, adId) {
            // Create tracking URL
            var trackingUrl = config.apiBaseUrl + '/track.php?ad_id=' + encodeURIComponent(adId);
            
            // Send an AJAX request to track the impression
            var xhr = new XMLHttpRequest();
            xhr.open('GET', trackingUrl, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success && response.impression_id) {
                            // Store the impression ID for click tracking
                            this.impressions[adId] = response.impression_id;
                            container.dataset.impressionId = response.impression_id;
                        }
                    } catch (e) {
                        console.error('HFI Ad: Error parsing impression response', e);
                    }
                }
            }.bind(this);
            xhr.send();
        },
        
        /**
         * Set up viewability tracking
         * 
         * @param {HTMLElement} container The ad container
         * @param {string} adId The ad ID
         */
        setupViewabilityTracking: function(container, adId) {
            // Simple viewability detection
            // In a future update, this will track when ads are actually viewable
            // and for how long
        },
        
        /**
         * Handle window resize events
         */
        handleResize: function() {
            // In a future update, this will handle responsive ads
        }
    };
    
    // Initialize on DOM ready or immediately if already loaded
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(function() { HFIAdClient.init(); }, 1);
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            HFIAdClient.init();
        });
    }
    
    // Expose to window for public API
    window.HFIAdClient = HFIAdClient;
    
})(window, document);

/**
 * Usage Example:
 * 
 * <!-- Include the script -->
 * <script src="https://yourdomain.com/static/js/adclient.js" id="hfi-ad-client"></script>
 * 
 * <!-- Create an ad container -->
 * <div class="hfi-ad" data-position-id="123"></div>
 */
