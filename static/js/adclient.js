class AdClient {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.options = {
            position: options.position || '',
            format: options.format || 'display',
            autoRefresh: options.autoRefresh || false,
            refreshInterval: options.refreshInterval || 30000,
            onLoad: options.onLoad || null,
            onError: options.onError || null,
            onClick: options.onClick || null
        };

        this.adData = null;
        this.refreshTimer = null;
        this.viewabilityObserver = null;
        this.initialized = false;

        this.initialize();
    }

    async initialize() {
        if (!this.container) {
            throw new Error('Container element not found');
        }

        // Set container styles
        this.container.style.position = 'relative';
        this.container.style.overflow = 'hidden';

        // Create tracking iframe
        this.trackingFrame = document.createElement('iframe');
        this.trackingFrame.style.display = 'none';
        this.container.appendChild(this.trackingFrame);

        // Initialize viewability tracking
        this.initializeViewabilityTracking();

        // Load initial ad
        await this.loadAd();

        // Set up auto-refresh if enabled
        if (this.options.autoRefresh) {
            this.startAutoRefresh();
        }

        this.initialized = true;
    }

    async loadAd() {
        try {
            // Get ad data from server
            const response = await fetch('/api/v1/serve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    position: this.options.position,
                    format: this.options.format,
                    url: window.location.href,
                    referrer: document.referrer,
                    viewport: {
                        width: window.innerWidth,
                        height: window.innerHeight
                    },
                    screen: {
                        width: screen.width,
                        height: screen.height
                    }
                })
            });

            if (!response.ok) {
                throw new Error('Failed to load ad');
            }

            this.adData = await response.json();

            // Create ad iframe
            await this.renderAd();

            // Track impression
            this.trackImpression();

            // Call onLoad callback if provided
            if (this.options.onLoad) {
                this.options.onLoad(this.adData);
            }

        } catch (error) {
            console.error('Error loading ad:', error);
            if (this.options.onError) {
                this.options.onError(error);
            }
        }
    }

    async renderAd() {
        // Remove existing ad iframe if any
        if (this.adFrame) {
            this.adFrame.remove();
        }

        // Create new iframe
        this.adFrame = document.createElement('iframe');
        this.adFrame.style.width = '100%';
        this.adFrame.style.height = '100%';
        this.adFrame.style.border = 'none';
        this.adFrame.setAttribute('scrolling', 'no');
        this.adFrame.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-popups');
        
        // Add click event listener
        this.adFrame.addEventListener('click', () => this.handleClick());

        // Insert iframe into container
        this.container.appendChild(this.adFrame);

        // Write ad content to iframe
        const html = this.generateAdHTML();
        this.adFrame.contentDocument.open();
        this.adFrame.contentDocument.write(html);
        this.adFrame.contentDocument.close();
    }

    generateAdHTML() {
        return `
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body {
                        margin: 0;
                        padding: 0;
                        overflow: hidden;
                    }
                    .ad-container {
                        width: 100%;
                        height: 100%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    img {
                        max-width: 100%;
                        max-height: 100%;
                        object-fit: contain;
                    }
                </style>
            </head>
            <body>
                <div class="ad-container">
                    <img src="${this.adData.image_url}" alt="Advertisement">
                </div>
                <script>
                    // Add click tracking
                    document.querySelector('.ad-container').addEventListener('click', function(e) {
                        e.preventDefault();
                        window.parent.postMessage({ type: 'adClick' }, '*');
                    });
                </script>
            </body>
            </html>
        `;
    }

    initializeViewabilityTracking() {
        // Create IntersectionObserver for viewability tracking
        this.viewabilityObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Ad is viewable
                        this.trackViewability();
                    }
                });
            },
            {
                threshold: [0.5] // 50% visibility threshold
            }
        );

        // Start observing the container
        this.viewabilityObserver.observe(this.container);
    }

    trackImpression() {
        // Send impression tracking request
        const trackingUrl = `/api/v1/track?type=impression&ad_id=${this.adData.id}`;
        this.trackingFrame.src = trackingUrl;
    }

    trackViewability() {
        // Send viewability tracking request
        const trackingUrl = `/api/v1/track?type=viewable&ad_id=${this.adData.id}`;
        this.trackingFrame.src = trackingUrl;
    }

    handleClick() {
        // Track click
        const trackingUrl = `/api/v1/track?type=click&ad_id=${this.adData.id}`;
        this.trackingFrame.src = trackingUrl;

        // Call onCLick callback if provided
        if (this.options.onClick) {
            this.options.onClick(this.adData);
        }

        // Open ad URL in new tab
        window.open(this.adData.click_url, '_blank');
    }

    startAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }

        this.refreshTimer = setInterval(() => {
            // Only refresh if the ad is visible
            const rect = this.container.getBoundingClientRect();
            const isVisible = (
                rect.top >= 0 &&
                rect.left >= 0 &&
                rect.bottom <= window.innerHeight &&
                rect.right <= window.innerWidth
            );

            if (isVisible) {
                this.loadAd();
            }
        }, this.options.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    destroy() {
        // Stop auto-refresh
        this.stopAutoRefresh();

        // Stop viewability tracking
        if (this.viewabilityObserver) {
            this.viewabilityObserver.disconnect();
        }

        // Remove frames
        if (this.adFrame) {
            this.adFrame.remove();
        }
        if (this.trackingFrame) {
            this.trackingFrame.remove();
        }

        // Clear data
        this.adData = null;
        this.initialized = false;
    }
}
