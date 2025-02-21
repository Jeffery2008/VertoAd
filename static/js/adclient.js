/**
 * Ad Client JavaScript
 * Handles ad loading, display, and tracking in iframes
 */
class AdClient {
    constructor(options = {}) {
        this.serverUrl = options.serverUrl || '/api/v1';
        this.adPositionId = options.positionId;
        this.containerElement = options.container;
        this.refreshInterval = options.refreshInterval || 30000; // 30 seconds
        this.trackViewability = options.trackViewability !== false;
        this.adInstance = null;
        this.viewabilityThreshold = 0.5; // 50% visibility required
        this.viewabilityTime = 1000; // 1 second of visibility required
        this.isViewable = false;
        this.viewableStartTime = null;
        this.viewabilityObserver = null;
        
        // Initialize
        this.init();
    }
    
    async init() {
        // Create iframe for ad
        this.iframe = document.createElement('iframe');
        this.iframe.style.width = '100%';
        this.iframe.style.height = '100%';
        this.iframe.style.border = 'none';
        this.iframe.style.overflow = 'hidden';
        this.containerElement.appendChild(this.iframe);
        
        // Set up viewability tracking
        if (this.trackViewability) {
            this.setupViewabilityTracking();
        }
        
        // Load initial ad
        await this.loadAd();
        
        // Set up refresh interval
        if (this.refreshInterval > 0) {
            setInterval(() => this.loadAd(), this.refreshInterval);
        }
    }
    
    async loadAd() {
        try {
            // Request ad from server
            const response = await fetch(`${this.serverUrl}/serve?position=${this.adPositionId}`, {
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                throw new Error(`Failed to load ad: ${response.statusText}`);
            }
            
            const adData = await response.json();
            
            if (!adData.success || !adData.ad) {
                console.warn('No ad available for position:', this.adPositionId);
                return;
            }
            
            // Record impression
            this.recordImpression(adData.ad.id);
            
            // Update iframe content
            this.adInstance = adData.ad;
            const html = this.generateAdHtml(adData.ad);
            
            // Write to iframe
            const doc = this.iframe.contentDocument || this.iframe.contentWindow.document;
            doc.open();
            doc.write(html);
            doc.close();
            
            // Add click tracking
            doc.body.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleClick(e);
            });
            
        } catch (error) {
            console.error('Error loading ad:', error);
        }
    }
    
    generateAdHtml(ad) {
        return `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        overflow: hidden;
                        width: 100%;
                        height: 100%;
                    }
                    .ad-container {
                        width: 100%;
                        height: 100%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        cursor: pointer;
                    }
                    img {
                        max-width: 100%;
                        max-height: 100%;
                        object-fit: contain;
                    }
                </style>
            </head>
            <body>
                <div class="ad-container" data-ad-id="${ad.id}">
                    ${ad.content}
                </div>
            </body>
            </html>
        `;
    }
    
    async recordImpression(adId) {
        try {
            const data = {
                ad_id: adId,
                position_id: this.adPositionId,
                url: window.location.href,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                }
            };
            
            await fetch(`${this.serverUrl}/track`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
        } catch (error) {
            console.error('Error recording impression:', error);
        }
    }
    
    async recordClick(adId) {
        try {
            const data = {
                ad_id: adId,
                position_id: this.adPositionId,
                url: window.location.href
            };
            
            await fetch(`${this.serverUrl}/track/click`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
        } catch (error) {
            console.error('Error recording click:', error);
        }
    }
    
    handleClick(event) {
        if (this.adInstance) {
            // Record click
            this.recordClick(this.adInstance.id);
            
            // Open ad URL in new tab
            window.open(this.adInstance.click_url, '_blank');
        }
    }
    
    setupViewabilityTracking() {
        // Use Intersection Observer to track visibility
        this.viewabilityObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    const isVisible = entry.intersectionRatio >= this.viewabilityThreshold;
                    
                    if (isVisible && !this.isViewable) {
                        // Ad became viewable
                        this.viewableStartTime = Date.now();
                        this.isViewable = true;
                        
                        // Check if still viewable after minimum time
                        setTimeout(() => {
                            if (this.isViewable && this.adInstance) {
                                this.recordViewability(this.adInstance.id);
                            }
                        }, this.viewabilityTime);
                        
                    } else if (!isVisible && this.isViewable) {
                        // Ad is no longer viewable
                        this.isViewable = false;
                        this.viewableStartTime = null;
                    }
                });
            },
            {
                threshold: [this.viewabilityThreshold]
            }
        );
        
        this.viewabilityObserver.observe(this.iframe);
    }
    
    async recordViewability(adId) {
        try {
            const data = {
                ad_id: adId,
                position_id: this.adPositionId,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                visible_time: Date.now() - this.viewableStartTime
            };
            
            await fetch(`${this.serverUrl}/track/viewability`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
        } catch (error) {
            console.error('Error recording viewability:', error);
        }
    }
    
    // Cleanup resources
    destroy() {
        if (this.viewabilityObserver) {
            this.viewabilityObserver.disconnect();
        }
        if (this.iframe && this.iframe.parentNode) {
            this.iframe.parentNode.removeChild(this.iframe);
        }
    }
}

// Example usage:
// new AdClient({
//     serverUrl: 'https://ads.example.com/api/v1',
//     positionId: '12345',
//     container: document.getElementById('ad-container'),
//     refreshInterval: 30000,
//     trackViewability: true
// });
