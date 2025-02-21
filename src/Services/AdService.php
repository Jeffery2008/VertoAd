<?php

require_once __DIR__ . '/../Models/Advertisement.php';
require_once __DIR__ . '/../Models/AdPosition.php';
require_once __DIR__ . '/../Utils/Logger.php';

class AdService {
    private $adModel;
    private $positionModel;
    private $logger;

    public function __construct() {
        $this->adModel = new Advertisement();
        $this->positionModel = new AdPosition();
        $this->logger = new Logger();
    }

    /**
     * Get the best matching ad for a given position and targeting parameters
     */
    public function getAd($positionId, array $targeting) {
        try {
            // Get position details
            $position = $this->positionModel->findById($positionId);
            if (!$position) {
                throw new Exception("Position not found: {$positionId}");
            }

            // Get all active ads for this position
            $ads = $this->adModel->findActiveByPosition($positionId);
            if (empty($ads)) {
                return null;
            }

            // Apply targeting filters
            $matchingAds = $this->filterAdsByTargeting($ads, $targeting);
            if (empty($matchingAds)) {
                return null;
            }

            // Select winning ad based on competition rules
            $winningAd = $this->selectWinningAd($matchingAds);
            if (!$winningAd) {
                return null;
            }

            return $winningAd;

        } catch (Exception $e) {
            $this->logger->error('Error getting ad', [
                'position_id' => $positionId,
                'targeting' => $targeting,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Filter ads based on targeting criteria
     */
    private function filterAdsByTargeting(array $ads, array $targeting) {
        return array_filter($ads, function($ad) use ($targeting) {
            // Check format matching
            if (!empty($targeting['format']) && $ad['format'] !== $targeting['format']) {
                return false;
            }

            // Check device targeting
            if (!empty($ad['device_targeting'])) {
                $deviceType = $this->getDeviceType($targeting['user_agent']);
                if (!in_array($deviceType, $ad['device_targeting'])) {
                    return false;
                }
            }

            // Check geo targeting
            if (!empty($ad['geo_targeting'])) {
                $countryCode = $this->getCountryFromIP($targeting['ip_address']);
                if (!in_array($countryCode, $ad['geo_targeting'])) {
                    return false;
                }
            }

            // Add more targeting filters as needed

            return true;
        });
    }

    /**
     * Select winning ad based on competition rules
     */
    private function selectWinningAd(array $ads) {
        // Sort by bid amount (highest first)
        usort($ads, function($a, $b) {
            return $b['bid_amount'] - $a['bid_amount'];
        });

        // Return highest bidding ad
        return reset($ads);
    }

    /**
     * Record an ad impression
     */
    public function recordImpression(array $data) {
        try {
            // Validate ad exists
            $ad = $this->adModel->findById($data['ad_id']);
            if (!$ad) {
                throw new Exception("Ad not found: {$data['ad_id']}");
            }

            // Insert impression record
            $this->adModel->insertImpression([
                'ad_id' => $data['ad_id'],
                'timestamp' => $data['timestamp'],
                'url' => $data['url'],
                'user_agent' => $data['user_agent'],
                'ip_address' => $data['ip_address'],
                'device_type' => $data['device_type'],
                'viewport' => json_encode($data['viewport']),
                'position' => $data['position']
            ]);

            // Update ad impression count
            $this->adModel->incrementImpressions($data['ad_id']);

        } catch (Exception $e) {
            $this->logger->error('Error recording impression', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Record a viewable impression
     */
    public function recordViewability(array $data) {
        try {
            // Insert viewability record
            $this->adModel->insertViewability([
                'ad_id' => $data['ad_id'],
                'timestamp' => $data['timestamp'],
                'url' => $data['url'],
                'user_agent' => $data['user_agent'],
                'device_type' => $data['device_type'],
                'viewport' => json_encode($data['viewport'])
            ]);

            // Update ad viewability metrics
            $this->adModel->incrementViewableImpressions($data['ad_id']);

        } catch (Exception $e) {
            $this->logger->error('Error recording viewability', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Record an ad click
     */
    public function recordClick(array $data) {
        try {
            // Insert click record
            $this->adModel->insertClick([
                'ad_id' => $data['ad_id'],
                'timestamp' => $data['timestamp'],
                'url' => $data['url'],
                'user_agent' => $data['user_agent'],
                'ip_address' => $data['ip_address'],
                'device_type' => $data['device_type']
            ]);

            // Update ad click count
            $this->adModel->incrementClicks($data['ad_id']);

        } catch (Exception $e) {
            $this->logger->error('Error recording click', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get country code from IP address
     */
    private function getCountryFromIP($ip) {
        // Add IP geolocation logic here
        // For now, return default
        return 'US';
    }

    /**
     * Get device type from user agent
     */
    private function getDeviceType($userAgent) {
        $tablet = "/tablet|ipad|playbook|silk/i";
        $mobile = "/mobile|android|iphone|phone|opera mini|iemobile/i";
        
        if (preg_match($tablet, $userAgent)) {
            return 'tablet';
        } else if (preg_match($mobile, $userAgent)) {
            return 'mobile';
        } else {
            return 'desktop';
        }
    }
}
