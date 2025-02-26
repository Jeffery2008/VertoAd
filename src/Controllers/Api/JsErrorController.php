<?php

namespace VertoAD\Core\Controllers\Api;

use VertoAD\Core\Utils\ErrorLogger;
use VertoAD\Core\Utils\Response;

/**
 * JsErrorController - Handles JavaScript errors reported from the client-side
 */
class JsErrorController {
    
    /**
     * Handle incoming JavaScript error reports
     */
    public function logError() {
        // Check for POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::json([
                'success' => false,
                'message' => 'Method not allowed'
            ], 405);
            return;
        }
        
        // Get JSON body
        $jsonBody = file_get_contents('php://input');
        $errorData = json_decode($jsonBody, true);
        
        // Validate input
        if (!$errorData || !isset($errorData['message'])) {
            Response::json([
                'success' => false,
                'message' => 'Invalid JSON data'
            ], 400);
            return;
        }
        
        // Format error message
        $errorMessage = $this->formatErrorMessage($errorData);
        
        // Determine severity based on error type
        $severity = $this->determineSeverity($errorData);
        
        // Log the JavaScript error through the central error logger
        ErrorLogger::logJsError(
            $errorMessage,
            $errorData['url'] ?? $errorData['page']['url'] ?? null,
            $errorData['line'] ?? null, 
            $errorData['userAgent'] ?? null,
            $severity,
            [
                'js_error_data' => $errorData,
                'client_version' => $errorData['version'] ?? 'unknown'
            ]
        );
        
        // Return success response
        Response::json([
            'success' => true,
            'message' => 'Error logged successfully'
        ]);
    }
    
    /**
     * Format a readable error message from JavaScript error data
     * 
     * @param array $errorData JS error data
     * @return string Formatted error message
     */
    private function formatErrorMessage(array $errorData) {
        $message = $errorData['message'] ?? 'Unknown JavaScript error';
        $type = $errorData['type'] ?? 'runtime';
        $url = $errorData['url'] ?? ($errorData['page']['url'] ?? 'unknown');
        $line = $errorData['line'] ?? 'unknown';
        $column = $errorData['column'] ?? 'unknown';
        
        // Format based on error type
        switch ($type) {
            case 'promise_rejection':
                return "Unhandled Promise Rejection: {$message}";
                
            case 'resource_error':
                $resource = $errorData['resource'] ?? 'unknown';
                return "Failed to load resource: {$resource} - {$message}";
                
            case 'manual':
                return "Manual JS Error: {$message}";
                
            case 'runtime':
            default:
                if ($line !== 'unknown' && $column !== 'unknown') {
                    return "JavaScript Error: {$message} at {$url} (line {$line}, column {$column})";
                } else {
                    return "JavaScript Error: {$message} at {$url}";
                }
        }
    }
    
    /**
     * Determine error severity based on error type and content
     * 
     * @param array $errorData JS error data
     * @return string Error severity
     */
    private function determineSeverity(array $errorData) {
        $type = $errorData['type'] ?? 'runtime';
        $message = $errorData['message'] ?? '';
        
        // Critical errors
        $criticalPatterns = [
            'security violation',
            'csrf token',
            'memory leak',
            'out of memory',
            'authentication failed',
            'authorization failed',
            'XSS detected'
        ];
        
        foreach ($criticalPatterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return ErrorLogger::SEVERITY_CRITICAL;
            }
        }
        
        // High severity errors
        if ($type === 'promise_rejection' && 
            (stripos($message, 'api') !== false || 
             stripos($message, 'fetch') !== false || 
             stripos($message, 'xhr') !== false)) {
            return ErrorLogger::SEVERITY_HIGH;
        }
        
        // Default severities by type
        switch ($type) {
            case 'promise_rejection':
                return ErrorLogger::SEVERITY_MEDIUM;
                
            case 'resource_error':
                // Resource errors for important assets (JS, CSS) are high severity
                $url = $errorData['url'] ?? '';
                if (preg_match('/\.(js|css)(\?|$)/', $url)) {
                    return ErrorLogger::SEVERITY_HIGH;
                }
                return ErrorLogger::SEVERITY_MEDIUM;
                
            case 'manual':
                // Use provided severity or default to medium
                return $errorData['severity'] ?? ErrorLogger::SEVERITY_MEDIUM;
                
            case 'runtime':
            default:
                return ErrorLogger::SEVERITY_MEDIUM;
        }
    }
} 