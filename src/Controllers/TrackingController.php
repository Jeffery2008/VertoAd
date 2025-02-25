<?php

namespace App\Controllers;

use App\Models\Impression;
use App\Models\Click;
use App\Models\Advertisement;
use App\Utils\DeviceDetector;

class TrackingController extends BaseController
{
    private $impressionModel;
    private $clickModel;
    private $advertisementModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->impressionModel = new Impression();
        $this->clickModel = new Click();
        $this->advertisementModel = new Advertisement();
    }

    /**
     * Record an impression for an ad
     */
    public function recordImpression()
    {
        // Get parameters
        $adId = filter_input(INPUT_GET, 'ad_id', FILTER_VALIDATE_INT);
        $positionId = filter_input(INPUT_GET, 'position_id', FILTER_VALIDATE_INT);
        $viewerId = filter_input(INPUT_GET, 'viewer_id', FILTER_SANITIZE_STRING) ?: $this->generateViewerId();
        
        // Validate required parameters
        if (!$adId || !$positionId) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing required parameters']);
            exit;
        }
        
        // Verify that the ad exists and is active
        $ad = $this->advertisementModel->find($adId);
        if (!$ad || $ad['status'] !== 'active') {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Advertisement not found or not active']);
            exit;
        }
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Get location data from IP (using the specified API)
        $locationData = $this->getLocationFromIP($ipAddress);
        
        // Get device data from user agent
        $deviceData = $this->detectDeviceFromUserAgent($userAgent);
        
        // Calculate cost based on ad's bid amount
        $cost = $ad['bid_amount'] / 1000; // Cost per thousand impressions
        
        // Prepare impression data
        $impressionData = [
            'ad_id' => $adId,
            'position_id' => $positionId,
            'viewer_id' => $viewerId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'cost' => $cost,
            'location_province' => $locationData['province'] ?? null,
            'location_city' => $locationData['city'] ?? null,
            'device_type' => $deviceData['device_type'] ?? null,
            'browser' => $deviceData['browser'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Record the impression
        $impressionId = $this->impressionModel->record($impressionData);
        
        if ($impressionId) {
            // Serve a transparent pixel image
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Failed to record impression']);
            exit;
        }
    }
    
    /**
     * Record a click for an ad
     */
    public function recordClick()
    {
        // Get parameters
        $impressionId = filter_input(INPUT_GET, 'impression_id', FILTER_VALIDATE_INT);
        $viewerId = filter_input(INPUT_GET, 'viewer_id', FILTER_SANITIZE_STRING);
        $targetUrl = filter_input(INPUT_GET, 'url', FILTER_SANITIZE_URL);
        
        // Validate required parameters
        if (!$impressionId || !$targetUrl) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Missing required parameters']);
            exit;
        }
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare click data
        $clickData = [
            'impression_id' => $impressionId,
            'viewer_id' => $viewerId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Record the click
        $clickId = $this->clickModel->record($clickData);
        
        if ($clickId) {
            // Redirect to the target URL
            header('Location: ' . $targetUrl);
            exit;
        } else {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['error' => 'Failed to record click']);
            exit;
        }
    }
    
    /**
     * Generate a unique viewer ID if not provided
     * 
     * @return string The generated viewer ID
     */
    private function generateViewerId()
    {
        return uniqid('viewer_', true);
    }
    
    /**
     * Get location data from IP address using the specified API
     * 
     * @param string $ipAddress The IP address to look up
     * @return array Location data with province and city
     */
    private function getLocationFromIP($ipAddress)
    {
        // Use the specified API: https://whois.pconline.com.cn/ipJson.jsp?ip=$ipAddress&json=true
        $url = "https://whois.pconline.com.cn/ipJson.jsp?ip={$ipAddress}&json=true";
        
        try {
            $response = file_get_contents($url);
            if ($response === false) {
                throw new \Exception('Failed to fetch location data');
            }
            
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to parse location data');
            }
            
            return [
                'province' => $data['pro'] ?? null,
                'city' => $data['city'] ?? null
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error fetching location data: ' . $e->getMessage());
            return [
                'province' => null,
                'city' => null
            ];
        }
    }
    
    /**
     * Detect device and browser information from user agent
     * 
     * @param string $userAgent The user agent string
     * @return array Device and browser information
     */
    private function detectDeviceFromUserAgent($userAgent)
    {
        // Simple device detection - should be replaced with a more robust solution
        $deviceType = 'unknown';
        $browser = 'unknown';
        
        // Detect device type
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
            $deviceType = 'mobile';
            
            // Further refine mobile detection
            if (preg_match('/iPad/i', $userAgent)) {
                $deviceType = 'tablet';
            }
        } else {
            $deviceType = 'desktop';
        }
        
        // Detect browser
        if (preg_match('/MSIE|Trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        }
        
        return [
            'device_type' => $deviceType,
            'browser' => $browser
        ];
    }
} 