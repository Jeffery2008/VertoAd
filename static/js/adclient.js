/**
 * MCP Ad Client Library
 * Lightweight client-side library for serving and tracking ads
 */

(function(window) {
    'use strict';

    // Configuration
    const config = {
        baseUrl: '', // Will be set during initialization
        defaultRefreshInterval: 30000 // 30 seconds
    };

    // Ad position registry
    const positions = new Map();

    /**
     * Main client class
     */
    class MCPAdClient {
        constructor(options = {}) {
            this.baseUrl = options.baseUrl || window.location.origin;
            config.baseUrl = this.baseUrl;
        }

        /**
         * Initialize ad position
         * @param {string} elementId - DOM element ID to render ad
         * @param {number} positionId - Ad position ID from MCP system
         * @param {Object} options - Additional options
         */
        initPosition(elementId, positionId, options = {}) {
            const element = document.getElementById(elementId);
            if (!element) {
                console.error(`Element ${elementId} not found`);
                return;
            }

            const position = new AdPosition(element, positionId, options);
            positions.set(elementId, position);
            position.load();

            return position;
        }

        /**
         * Remove ad position
         */
        removePosition(elementId) {
            const position = positions.get(elementId);
            if (position) {
                position.destroy();
                positions.delete(elementId);
            }
        }
    }

    /**
     * Ad Position class
     */
    class AdPosition {
        constructor(element, positionId, options) {
            this.element = element;
            this.positionId = positionId;
            this.options = {
                refreshInterval: options.refreshInterval || config.defaultRefreshInterval,
                autoReload: options.autoReload !== false,
                template: options.template || defaultTemplate
            };

            this.currentAds = [];
            this.refreshTimer = null;
            
            // Bind event handlers
            this.handleClick = this.handleClick.bind(this);
            this.element.addEventListener('click', this.handleClick);
        }

        /**
         * Load ads for this position
         */
        async load() {
            try {
                const response = await fetch(
                    `${config.baseUrl}/api/v1/serve.php?position_id=${this.positionId}`,
                    {
                        credentials: 'include'
                    }
                );

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load ads');
                }

                this.currentAds = result.data;
                this.render();

                // Set up auto-refresh if enabled
                if (this.options.autoReload) {
                    this.scheduleRefresh();
                }
            } catch (error) {
                console.error('Failed to load ads:', error);
                this.element.innerHTML = ''; // Clear on error
            }
        }

        /**
         * Render ads in the container
         */
        render() {
            if (!this.currentAds || this.currentAds.length === 0) {
                this.element.innerHTML = '';
                return;
            }

            // Apply template to each ad
            const html = this.currentAds.map(ad => {
                return this.options.template(ad);
            }).join('');

            this.element.innerHTML = html;

            // Add click tracking to all links
            const links = this.element.getElementsByTagName('a');
            for (let link of links) {
                link.setAttribute('data-mcp-track', 'true');
            }
        }

        /**
         * Handle click events for tracking
         */
        async handleClick(event) {
            const target = event.target.closest('[data-mcp-track]');
            if (!target) return;

            // Find the clicked ad
            const adContainer = target.closest('[data-mcp-ad-id]');
            if (!adContainer) return;

            const adId = adContainer.getAttribute('data-mcp-ad-id');
            event.preventDefault();

            try {
                // Send click tracking request
                const response = await fetch(`${config.baseUrl}/api/v1/serve.php`, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ad_id=${adId}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Continue with the navigation
                const href = target.getAttribute('href');
                if (href) {
                    window.location.href = href;
                }
            } catch (error) {
                console.error('Failed to track click:', error);
                // Still navigate even if tracking fails
                const href = target.getAttribute('href');
                if (href) {
                    window.location.href = href;
                }
            }
        }

        /**
         * Schedule next refresh
         */
        scheduleRefresh() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
            }
            this.refreshTimer = setTimeout(() => {
                this.load();
            }, this.options.refreshInterval);
        }

        /**
         * Clean up position
         */
        destroy() {
            if (this.refreshTimer) {
                clearTimeout(this.refreshTimer);
            }
            this.element.removeEventListener('click', this.handleClick);
            this.element.innerHTML = '';
        }
    }

    /**
     * Default ad template
     */
    function defaultTemplate(ad) {
        const content = JSON.parse(ad.content);
        return `
            <div class="mcp-ad" data-mcp-ad-id="${ad.id}">
                <a href="${content.url}" target="_blank" data-mcp-track="true">
                    ${content.type === 'image' 
                        ? `<img src="${content.image_url}" alt="${ad.title}" style="width:${ad.width}px;height:${ad.height}px">` 
                        : content.html
                    }
                </a>
            </div>
        `;
    }

    // Export to window
    window.MCPAdClient = MCPAdClient;

})(window);
