<?php
require_once '../../src/Services/AdService.php';
require_once '../../src/Models/AdPosition.php';
require_once '../../src/Utils/Logger.php';
require_once '../../config/config.php';

class AdServer {
    private $adService;
    private $logger;
    
    public function __construct() {
        $this->adService = new AdService();
        $this->logger = new Logger();
    }
    
    public function serve() {
        try {
            // Get request data
            $position_id = $_GET['position_id'] ?? null;
            $requestData = $this->getRequestData();
            
            // Validate position ID
            if (!$position_id) {
                throw new Exception('Position ID is required', 400);
            }
            
            // Get matching ad
            $ad = $this->selectAd($position_id, $requestData);
            if (!$ad) {
                throw new Exception('No eligible ad found', 404);
            }
            
            // Record impression
            $impression_id = $this->recordImpression($ad, $requestData);
            
            // Format response
            $response = $this->formatAdResponse($ad, $impression_id);
            
            // Return success response
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            echo json_encode([
                'status' => 'success',
                'data' => $response
            ]);
            
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function getRequestData(): array {
        // Parse request body
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true) ?: [];
        
        // Get device info from user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $deviceInfo = $this->parseUserAgent($userAgent);
        
        // Get IP and geo info
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $geoInfo = $this->getGeoInfo($ipAddress);
        
        return [
            'client_info' => [
                'device' => $deviceInfo,
                'geo' => $geoInfo
            ],
            'placement' => $data['placement'] ?? null,
            'context' => $data['context'] ?? []
        ];
    }
    
    private function parseUserAgent(string $userAgent): array {
        $deviceType = 'desktop';
        if (preg_match('/(tablet|ipad)/i', $userAgent)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/(mobile|iphone|android)/i', $userAgent)) {
            $deviceType = 'mobile';
        }
        
        $os = 'unknown';
        if (preg_match('/windows/i', $userAgent)) {
            $os = 'windows';
        } elseif (preg_match('/macintosh|mac os/i', $userAgent)) {
            $os = 'macos';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'android';
        } elseif (preg_match('/iphone|ipad/i', $userAgent)) {
            $os = 'ios';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'linux';
        }
        
        $browser = 'unknown';
        if (preg_match('/edge/i', $userAgent)) {
            $browser = 'edge';
        } elseif (preg_match('/chrome/i', $userAgent)) {
            $browser = 'chrome';
        } elseif (preg_match('/firefox/i', $userAgent)) {
            $browser = 'firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
            $browser = 'safari';
        }
        
        return [
            'type' => $deviceType,
            'os' => $os,
            'browser' => $browser
        ];
    }
    
    private function getGeoInfo(string $ipAddress): array {
        // Simple IP to country mapping for demo
        // In production, use a proper IP geolocation service
        return [
            'ip' => $ipAddress,
            'country' => 'US', // Default for demo
            'region' => null,
            'city' => null
        ];
    }
    
    private function selectAd(string $position_id, array $requestData): ?array {
        // Get eligible ads for the position
        $eligibleAds = $this->adService->getEligibleAds($position_id, [
            'status' => 'active',
            'current_date' => date('Y-m-d'),
            'device_type' => $requestData['client_info']['device']['type'] ?? null,
            'country' => $requestData['client_info']['geo']['country'] ?? null
        ]);
        
        if (empty($eligibleAds)) {
            return null;
        }
        
        // Score and rank ads based on various factors
        $scoredAds = array_map(function($ad) use ($requestData) {
            $score = $this->calculateAdScore($ad, $requestData);
            return ['ad' => $ad, 'score' => $score];
        }, $eligibleAds);
        
        // Sort by score
        usort($scoredAds, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        // Return the highest scoring ad
        return $scoredAds[0]['ad'];
    }
    
    private function calculateAdScore(array $ad, array $requestData): float {
        $score = 0;
        
        // Base CTR score (0-50 points)
        $ctr = $ad['impressions'] > 0 ? ($ad['clicks'] / $ad['impressions']) : 0;
        $score += $ctr * 50;
        
        // Budget utilization (0-20 points)
        // Prefer ads that haven't used much of their budget
        $budgetUsageRatio = $ad['spent'] / $ad['budget'];
        $score += (1 - $budgetUsageRatio) * 20;
        
        // Targeting match score (0-30 points)
        $targetingScore = $this->calculateTargetingScore($ad, $requestData);
        $score += $targetingScore * 30;
        
        return $score;
    }
    
    private function calculateTargetingScore(array $ad, array $requestData): float {
        $matches = 0;
        $totalCriteria = 0;
        
        // Device type targeting
        if (!empty($ad['targeting']['device_types'])) {
            $totalCriteria++;
            if (in_array($requestData['client_info']['device']['type'], $ad['targeting']['device_types'])) {
                $matches++;
            }
        }
        
        // Geographic targeting
        if (!empty($ad['targeting']['countries'])) {
            $totalCriteria++;
            if (in_array($requestData['client_info']['geo']['country'], $ad['targeting']['countries'])) {
                $matches++;
            }
        }
        
        // Context targeting
        if (!empty($ad['targeting']['context']) && !empty($requestData['context'])) {
            $totalCriteria++;
            $contextMatches = array_intersect_key($ad['targeting']['context'], $requestData['context']);
            if (!empty($contextMatches)) {
                $matches++;
            }
        }
        
        return $totalCriteria > 0 ? $matches / $totalCriteria : 1;
    }
    
    private function recordImpression(array $ad, array $requestData): string {
        $impression_id = bin2hex(random_bytes(16));
        
        $this->adService->recordImpression([
            'ad_id' => $ad['id'],
            'impression_id' => $impression_id,
            'position_id' => $ad['position_id'],
            'device_type' => $requestData['client_info']['device']['type'],
            'device_os' => $requestData['client_info']['device']['os'],
            'browser' => $requestData['client_info']['device']['browser'],
            'ip_address' => $requestData['client_info']['geo']['ip'],
            'country' => $requestData['client_info']['geo']['country'],
            'region' => $requestData['client_info']['geo']['region'],
            'city' => $requestData['client_info']['geo']['city']
        ]);
        
        return $impression_id;
    }
    
    private function formatAdResponse(array $ad, string $impression_id): array {
        return [
            'status' => 'success',
            'id' => $ad['id'],
            'impression_id' => $impression_id,
            'type' => $ad['type'],
            'content' => $ad['content'],
            'position' => [
                'id' => $ad['position_id'],
                'width' => $ad['position_width'],
                'height' => $ad['position_height']
            ],
            'click_url' => $this->generateClickUrl($ad['id'], $impression_id)
        ];
    }
    
    private function generateClickUrl(string $ad_id, string $impression_id): string {
        $baseUrl = getConfig('app.url') . '/track/click.php';
        return sprintf(
            '%s?ad_id=%s&impression_id=%s',
            $baseUrl,
            urlencode($ad_id),
            urlencode($impression_id)
        );
    }
    
    private function handleError(Exception $e) {
        $statusCode = $e->getCode() ?: 500;
        $message = $e->getMessage() ?: 'Internal server error';
        
        $this->logger->error('Error serving ad: ' . $message, [
            'code' => $statusCode,
            'trace' => $e->getTraceAsString()
        ]);
        
        http_response_code($statusCode);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
    }
}

// Handle request
$server = new AdServer();
$server->serve();
