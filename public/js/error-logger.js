/**
 * VertoAD - JavaScript Error Logger
 * 
 * This module captures JavaScript errors and sends them to the server for logging
 * It handles various types of errors including runtime errors, promise rejections,
 * and resource loading failures.
 */

(function() {
    'use strict';
    
    // Configuration
    const config = {
        endpoint: '/api/errors/js',
        throttleLimit: 5,     // Maximum number of errors to send per minute
        stackTraceLimit: 10,  // Maximum number of stack frames to collect
        enabled: true,        // Whether error logging is enabled
        tags: [],             // Custom tags to include with all error reports
        version: '1.0.0'      // Client version
    };
    
    // Error tracking state
    let sentErrors = 0;
    let throttleTimer = null;
    let errorQueue = [];
    
    /**
     * Initialize the error logger
     * @param {Object} options - Configuration options
     */
    function init(options = {}) {
        // Merge provided options with defaults
        Object.assign(config, options);
        
        // Set stack trace limit
        Error.stackTraceLimit = config.stackTraceLimit;
        
        // Register error handlers
        if (config.enabled) {
            // Global error handler
            window.addEventListener('error', handleError, { capture: true });
            
            // Unhandled promise rejection handler
            window.addEventListener('unhandledrejection', handleRejection);
            
            // Console error capture (optional)
            if (config.captureConsoleErrors) {
                captureConsoleErrors();
            }
            
            // Send any queued errors on page unload
            window.addEventListener('beforeunload', flushErrorQueue);
            
            // Start throttle timer
            startThrottleTimer();
            
            console.log('[ErrorLogger] Initialized');
        }
    }
    
    /**
     * Handle runtime errors
     * @param {ErrorEvent} event - Error event
     */
    function handleError(event) {
        // Don't log errors from browser extensions
        if (isExtensionError(event)) {
            return;
        }
        
        const errorData = {
            type: 'runtime',
            message: event.message || 'Unknown error',
            stack: parseStackTrace(event.error ? event.error.stack : ''),
            url: event.filename || window.location.href,
            line: event.lineno || 0,
            column: event.colno || 0,
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            language: navigator.language,
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            referrer: document.referrer,
            tags: config.tags
        };
        
        // Add page context
        enrichWithPageContext(errorData);
        
        // Send error to server
        sendErrorToServer(errorData);
    }
    
    /**
     * Handle unhandled promise rejections
     * @param {PromiseRejectionEvent} event - Rejection event
     */
    function handleRejection(event) {
        const errorData = {
            type: 'promise_rejection',
            message: event.reason ? (event.reason.message || String(event.reason)) : 'Promise rejected',
            stack: event.reason && event.reason.stack ? parseStackTrace(event.reason.stack) : [],
            url: window.location.href,
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            language: navigator.language,
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight
            },
            referrer: document.referrer,
            tags: config.tags
        };
        
        // Add page context
        enrichWithPageContext(errorData);
        
        // Send error to server
        sendErrorToServer(errorData);
    }
    
    /**
     * Manually log an error
     * @param {Error|string} error - Error object or message
     * @param {Object} additionalInfo - Additional error context
     */
    function logError(error, additionalInfo = {}) {
        const errorData = {
            type: 'manual',
            message: error instanceof Error ? error.message : String(error),
            stack: error instanceof Error ? parseStackTrace(error.stack) : [],
            url: window.location.href,
            timestamp: new Date().toISOString(),
            userAgent: navigator.userAgent,
            additionalInfo: additionalInfo,
            tags: config.tags.concat(additionalInfo.tags || [])
        };
        
        // Add page context
        enrichWithPageContext(errorData);
        
        // Send error to server
        sendErrorToServer(errorData);
        
        return errorData;
    }
    
    /**
     * Send error data to the server
     * @param {Object} errorData - Error information
     */
    function sendErrorToServer(errorData) {
        // Check throttle limit
        if (sentErrors >= config.throttleLimit) {
            // Queue the error for later
            errorQueue.push(errorData);
            return;
        }
        
        // Add client version
        errorData.version = config.version;
        
        // Increment sent errors count
        sentErrors++;
        
        // Send to server using fetch API
        fetch(config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(errorData),
            keepalive: true // For sending data even when page is unloading
        }).catch(err => {
            // Fallback to beacon API if fetch fails
            if (navigator.sendBeacon) {
                const blob = new Blob([JSON.stringify(errorData)], {
                    type: 'application/json'
                });
                navigator.sendBeacon(config.endpoint, blob);
            }
        });
    }
    
    /**
     * Parse a stack trace string into an array of stack frames
     * @param {string} stackString - Raw stack trace
     * @returns {Array} Parsed stack frames
     */
    function parseStackTrace(stackString) {
        if (!stackString) return [];
        
        // Different browsers format stack traces differently
        // This is a simple parser that works with most formats
        const stackLines = stackString.split('\n');
        const stackFrames = [];
        
        for (let i = 0; i < stackLines.length; i++) {
            const line = stackLines[i].trim();
            if (!line || line === 'Error' || line.startsWith('Error:')) continue;
            
            let frame = {
                raw: line
            };
            
            // Try to extract function, file, line, and column
            // Chrome format: "at functionName (file:line:column)"
            // Firefox format: "functionName@file:line:column"
            const chromeMatch = line.match(/at\s+(.*?)\s+\((.*?):(\d+):(\d+)\)/);
            const firefoxMatch = line.match(/(.*?)@(.*?):(\d+):(\d+)/);
            
            if (chromeMatch) {
                frame.function = chromeMatch[1];
                frame.file = chromeMatch[2];
                frame.line = parseInt(chromeMatch[3], 10);
                frame.column = parseInt(chromeMatch[4], 10);
            } else if (firefoxMatch) {
                frame.function = firefoxMatch[1];
                frame.file = firefoxMatch[2];
                frame.line = parseInt(firefoxMatch[3], 10);
                frame.column = parseInt(firefoxMatch[4], 10);
            }
            
            stackFrames.push(frame);
            
            // Limit stack frames
            if (stackFrames.length >= config.stackTraceLimit) break;
        }
        
        return stackFrames;
    }
    
    /**
     * Capture console.error calls
     */
    function captureConsoleErrors() {
        const originalConsoleError = console.error;
        
        console.error = function() {
            // Call original console.error
            originalConsoleError.apply(console, arguments);
            
            // Extract error message
            let errorMessage = Array.from(arguments)
                .map(arg => typeof arg === 'string' ? arg : String(arg))
                .join(' ');
            
            // Log as manual error
            logError(new Error(errorMessage), { 
                source: 'console.error',
                arguments: Array.from(arguments).map(arg => String(arg))
            });
        };
    }
    
    /**
     * Start throttle timer
     */
    function startThrottleTimer() {
        // Reset sent errors count every minute
        throttleTimer = setInterval(() => {
            const queuedErrors = errorQueue.splice(0, config.throttleLimit - sentErrors);
            
            // Send queued errors
            queuedErrors.forEach(error => sendErrorToServer(error));
            
            // Reset count
            sentErrors = 0;
        }, 60000); // 1 minute
    }
    
    /**
     * Send all queued errors
     */
    function flushErrorQueue() {
        // Send remaining errors in queue
        errorQueue.forEach(error => {
            const blob = new Blob([JSON.stringify(error)], {
                type: 'application/json'
            });
            navigator.sendBeacon(config.endpoint, blob);
        });
        
        // Clear queue
        errorQueue = [];
        
        // Clear throttle timer
        clearInterval(throttleTimer);
    }
    
    /**
     * Check if an error is from a browser extension
     * @param {ErrorEvent} event - Error event
     * @returns {boolean} True if error is from an extension
     */
    function isExtensionError(event) {
        const filename = event.filename || '';
        return filename.startsWith('chrome-extension://') || 
               filename.startsWith('moz-extension://') ||
               filename.startsWith('safari-extension://');
    }
    
    /**
     * Add additional page context to error data
     * @param {Object} errorData - Error data object
     */
    function enrichWithPageContext(errorData) {
        // Add basic page information
        errorData.page = {
            url: window.location.href,
            title: document.title,
            referrer: document.referrer,
            readyState: document.readyState
        };
        
        // Add performance data if available
        if (window.performance) {
            try {
                const perf = window.performance;
                const timing = perf.timing;
                
                errorData.performance = {
                    memory: perf.memory ? {
                        jsHeapSizeLimit: perf.memory.jsHeapSizeLimit,
                        totalJSHeapSize: perf.memory.totalJSHeapSize,
                        usedJSHeapSize: perf.memory.usedJSHeapSize
                    } : null,
                    timing: timing ? {
                        navigationStart: timing.navigationStart,
                        loadEventEnd: timing.loadEventEnd,
                        domComplete: timing.domComplete,
                        domInteractive: timing.domInteractive,
                        domContentLoadedEventEnd: timing.domContentLoadedEventEnd
                    } : null
                };
                
                // Add navigation type
                if (perf.navigation) {
                    errorData.performance.navigation = {
                        type: perf.navigation.type,
                        redirectCount: perf.navigation.redirectCount
                    };
                }
            } catch (e) {
                // Ignore performance data if it causes errors
            }
        }
    }
    
    // Public API
    window.ErrorLogger = {
        init: init,
        logError: logError,
        config: config
    };
})(); 