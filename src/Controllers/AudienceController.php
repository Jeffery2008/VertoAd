<?php

namespace VertoAD\Core\Controllers;

use VertoAD\Core\Models\UserSegment;
use VertoAD\Core\Models\VisitorProfile;
use VertoAD\Core\Services\AuthService;
use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Logger;

/**
 * AudienceController
 * 
 * Handles user segmentation and audience insights
 */
class AudienceController extends BaseController
{
    private $auth;
    private $logger;
    private $db;
    private $userSegmentModel;
    private $visitorProfileModel;
    
    /**
     * Initialize controller
     */
    public function __construct()
    {
        parent::__construct();
        $this->auth = new AuthService();
        $this->logger = new Logger();
        $this->db = new Database();
        $this->userSegmentModel = new UserSegment();
        $this->visitorProfileModel = new VisitorProfile();
    }
    
    /**
     * Display segments management page
     */
    public function segments()
    {
        // Check authentication
        if (!$this->auth->isAdvertiser() && !$this->auth->isAdmin()) {
            $this->redirect('/login');
        }
        
        $user = $this->auth->getCurrentUser();
        $segments = $this->userSegmentModel->getByUserId($user['id']);
        
        // Get member counts for each segment
        foreach ($segments as $key => $segment) {
            $segmentObj = new UserSegment($segment['id']);
            $segments[$key]['member_count'] = $segmentObj->getMembersCount();
            
            // Parse criteria for display
            $criteria = json_decode($segment['criteria'], true);
            $segments[$key]['criteria_summary'] = $this->formatCriteriaForDisplay($criteria);
        }
        
        $this->renderTemplate('advertiser/segments', [
            'segments' => $segments,
            'user' => $user
        ]);
    }
    
    /**
     * Create or update a segment
     */
    public function saveSegment()
    {
        // Check authentication
        if (!$this->auth->isAdvertiser() && !$this->auth->isAdmin()) {
            $this->redirect('/login');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = $this->auth->getCurrentUser();
            
            // Get segment data from POST
            $segmentId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
            $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
            $isDynamic = filter_input(INPUT_POST, 'is_dynamic', FILTER_VALIDATE_INT) === 1;
            
            // Get criteria from form
            $criteria = [];
            $critFields = $_POST['criteria_field'] ?? [];
            $critOps = $_POST['criteria_operator'] ?? [];
            $critValues = $_POST['criteria_value'] ?? [];
            $critValues2 = $_POST['criteria_value2'] ?? [];
            
            for ($i = 0; $i < count($critFields); $i++) {
                if (!empty($critFields[$i]) && !empty($critOps[$i])) {
                    $criterion = [
                        'field' => $critFields[$i],
                        'operator' => $critOps[$i],
                        'value' => $critValues[$i] ?? null
                    ];
                    
                    // Add second value for 'between' operator
                    if ($critOps[$i] === 'between' && isset($critValues2[$i])) {
                        $criterion['value2'] = $critValues2[$i];
                    }
                    
                    $criteria[] = $criterion;
                }
            }
            
            // Validate input
            if (empty($name)) {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Segment name is required'
                ];
                $this->redirect('/advertiser/segments');
            }
            
            if ($isDynamic && empty($criteria)) {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Dynamic segments require at least one criterion'
                ];
                $this->redirect('/advertiser/segments');
            }
            
            // Prepare segment data
            $segmentData = [
                'name' => $name,
                'description' => $description,
                'user_id' => $user['id'],
                'criteria' => $criteria,
                'is_dynamic' => $isDynamic ? 1 : 0
            ];
            
            // Create or update segment
            if ($segmentId) {
                // Update existing segment
                $segment = new UserSegment($segmentId);
                $result = $segment->update($segmentData);
                
                // If dynamic, update members based on new criteria
                if ($isDynamic && $result) {
                    $segment->updateDynamicMembers();
                }
                
                $message = 'Segment updated successfully';
            } else {
                // Create new segment
                $segmentId = $this->userSegmentModel->create($segmentData);
                
                // If dynamic, update members based on criteria
                if ($isDynamic && $segmentId) {
                    $segment = new UserSegment($segmentId);
                    $segment->updateDynamicMembers();
                }
                
                $message = 'Segment created successfully';
            }
            
            if ($segmentId) {
                $_SESSION['flash'] = [
                    'type' => 'success',
                    'message' => $message
                ];
            } else {
                $_SESSION['flash'] = [
                    'type' => 'danger',
                    'message' => 'Error saving segment'
                ];
            }
        }
        
        $this->redirect('/advertiser/segments');
    }
    
    /**
     * Delete a segment
     * 
     * @param int $id Segment ID
     */
    public function deleteSegment($id)
    {
        // Check authentication
        if (!$this->auth->isAdvertiser() && !$this->auth->isAdmin()) {
            $this->redirect('/login');
        }
        
        $user = $this->auth->getCurrentUser();
        
        // Verify ownership
        $segment = new UserSegment($id);
        $segmentData = $segment->find($id);
        
        if (!$segmentData || $segmentData['user_id'] != $user['id']) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Segment not found or you do not have permission to delete it'
            ];
            $this->redirect('/advertiser/segments');
        }
        
        // Delete segment
        if ($segment->delete()) {
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Segment deleted successfully'
            ];
        } else {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Error deleting segment'
            ];
        }
        
        $this->redirect('/advertiser/segments');
    }
    
    /**
     * Display segment members
     * 
     * @param int $id Segment ID
     */
    public function segmentMembers($id)
    {
        // Check authentication
        if (!$this->auth->isAdvertiser() && !$this->auth->isAdmin()) {
            $this->redirect('/login');
        }
        
        $user = $this->auth->getCurrentUser();
        
        // Verify ownership
        $segment = new UserSegment($id);
        $segmentData = $segment->find($id);
        
        if (!$segmentData || $segmentData['user_id'] != $user['id']) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Segment not found or you do not have permission to view it'
            ];
            $this->redirect('/advertiser/segments');
        }
        
        // Get page parameters
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        // Get members
        $members = $segment->getMembers($limit, $offset);
        $totalCount = $segment->getMembersCount();
        
        // Calculate total pages
        $totalPages = ceil($totalCount / $limit);
        
        $this->renderTemplate('advertiser/segment_members', [
            'segment' => $segmentData,
            'members' => $members,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'user' => $user
        ]);
    }
    
    /**
     * Display audience insights dashboard
     */
    public function insights()
    {
        // Check authentication
        if (!$this->auth->isAdvertiser() && !$this->auth->isAdmin()) {
            $this->redirect('/login');
        }
        
        $user = $this->auth->getCurrentUser();
        
        // Get filter parameters
        $startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-30 days'));
        $endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
        $segmentId = filter_input(INPUT_GET, 'segment_id', FILTER_VALIDATE_INT) ?: null;
        
        // Get user segments for filter dropdown
        $segments = $this->userSegmentModel->getByUserId($user['id']);
        
        // Get audience insights data
        // Visitor trends over time
        $visitorTrends = VisitorProfile::getVisitorCountByDate($startDate, $endDate);
        
        // Location distribution
        $locationDistribution = VisitorProfile::getTopLocations(10);
        
        // Device distribution
        $deviceDistribution = VisitorProfile::getDeviceDistribution();
        
        // Browser distribution
        $browserDistribution = VisitorProfile::getBrowserDistribution();
        
        // Calculate summary metrics
        $totalVisitors = 0;
        $newVisitors = 0;
        foreach ($visitorTrends as $day) {
            $newVisitors += $day['new_visitors'];
        }
        
        // Get total visitors from profiles
        try {
            $query = "SELECT COUNT(*) as count FROM visitor_profiles";
            $result = $this->db->fetchOne($query);
            $totalVisitors = $result['count'] ?? 0;
        } catch (\Exception $e) {
            $this->logger->error('Error getting total visitors: ' . $e->getMessage());
        }
        
        // Calculate returning visitors
        $returningVisitors = $totalVisitors - $newVisitors;
        if ($returningVisitors < 0) $returningVisitors = 0;
        
        $this->renderTemplate('advertiser/audience_insights', [
            'segments' => $segments,
            'selectedSegmentId' => $segmentId,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'visitorTrends' => $visitorTrends,
            'locationDistribution' => $locationDistribution,
            'deviceDistribution' => $deviceDistribution,
            'browserDistribution' => $browserDistribution,
            'totalVisitors' => $totalVisitors,
            'newVisitors' => $newVisitors,
            'returningVisitors' => $returningVisitors,
            'user' => $user
        ]);
    }
    
    /**
     * Record a tracking pixel view with visitor data
     */
    public function trackVisitor()
    {
        // This is an API endpoint, so we'll return JSON
        header('Content-Type: application/json');
        
        try {
            // Get visitor data from request
            $visitorId = $_REQUEST['visitor_id'] ?? null;
            
            // If no visitor ID provided, generate one
            if (!$visitorId) {
                $visitorId = $this->generateVisitorId();
            }
            
            // Get referrer
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            
            // Get UTM parameters
            $utmSource = $_REQUEST['utm_source'] ?? null;
            $utmMedium = $_REQUEST['utm_medium'] ?? null;
            $utmCampaign = $_REQUEST['utm_campaign'] ?? null;
            
            // Get IP and geo info
            $ipAddress = $_SERVER['REMOTE_ADDR'];
            $geoInfo = $this->getGeoInfoFromIp($ipAddress);
            
            // Get device info
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $deviceInfo = $this->getDeviceInfoFromUserAgent($userAgent);
            
            // Prepare visitor data
            $visitorData = [
                'visitor_id' => $visitorId,
                'last_seen' => date('Y-m-d H:i:s'),
                'last_referrer' => $referrer,
                'last_utm_source' => $utmSource,
                'last_utm_medium' => $utmMedium,
                'last_utm_campaign' => $utmCampaign,
                'geo_country' => $geoInfo['country'] ?? null,
                'geo_region' => $geoInfo['region'] ?? null,
                'geo_city' => $geoInfo['city'] ?? null,
                'device_type' => $deviceInfo['device_type'] ?? null,
                'browser' => $deviceInfo['browser'] ?? null,
                'os' => $deviceInfo['os'] ?? null,
                'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null
            ];
            
            // Check if visitor exists
            $visitorProfile = new VisitorProfile();
            $existingProfile = $visitorProfile->find($visitorId);
            
            if ($existingProfile) {
                // Update existing profile
                $visitorData['visit_count'] = $existingProfile['visit_count'] + 1;
                $visitorData['total_page_views'] = $existingProfile['total_page_views'] + 1;
                
                // Preserve first_seen data
                $visitorData['first_seen'] = $existingProfile['first_seen'];
                $visitorData['first_referrer'] = $existingProfile['first_referrer'] ?? $referrer;
                $visitorData['first_utm_source'] = $existingProfile['first_utm_source'] ?? $utmSource;
                $visitorData['first_utm_medium'] = $existingProfile['first_utm_medium'] ?? $utmMedium;
                $visitorData['first_utm_campaign'] = $existingProfile['first_utm_campaign'] ?? $utmCampaign;
            } else {
                // New visitor
                $visitorData['first_seen'] = date('Y-m-d H:i:s');
                $visitorData['first_referrer'] = $referrer;
                $visitorData['first_utm_source'] = $utmSource;
                $visitorData['first_utm_medium'] = $utmMedium;
                $visitorData['first_utm_campaign'] = $utmCampaign;
                $visitorData['visit_count'] = 1;
                $visitorData['total_page_views'] = 1;
            }
            
            // Create or update visitor profile
            $visitorProfile->createOrUpdate($visitorData);
            
            // Record page view event
            $eventData = [
                'visitor_id' => $visitorId,
                'event_type' => 'page_view',
                'page_url' => $referrer,
                'referrer' => $_REQUEST['ref'] ?? null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent
            ];
            
            $visitorProfile->recordEvent($eventData);
            
            // Return visitor ID for client-side storage
            echo json_encode([
                'success' => true,
                'visitor_id' => $visitorId
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Error tracking visitor: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Failed to track visitor'
            ]);
        }
        
        exit;
    }
    
    /**
     * Format segment criteria for display
     * 
     * @param array $criteria Criteria array
     * @return string Formatted criteria
     */
    private function formatCriteriaForDisplay($criteria)
    {
        if (empty($criteria)) {
            return 'No criteria defined';
        }
        
        $formatted = [];
        
        foreach ($criteria as $criterion) {
            $field = $criterion['field'] ?? '';
            $operator = $criterion['operator'] ?? '';
            $value = $criterion['value'] ?? '';
            $value2 = $criterion['value2'] ?? '';
            
            // Format field for display
            $fieldDisplay = str_replace('_', ' ', $field);
            $fieldDisplay = ucwords($fieldDisplay);
            
            // Format operator for display
            switch ($operator) {
                case 'equals':
                    $operatorDisplay = 'is';
                    break;
                case 'not_equals':
                    $operatorDisplay = 'is not';
                    break;
                case 'contains':
                    $operatorDisplay = 'contains';
                    break;
                case 'starts_with':
                    $operatorDisplay = 'starts with';
                    break;
                case 'greater_than':
                    $operatorDisplay = 'is greater than';
                    break;
                case 'less_than':
                    $operatorDisplay = 'is less than';
                    break;
                case 'between':
                    $operatorDisplay = 'is between';
                    break;
                case 'in':
                    $operatorDisplay = 'is one of';
                    break;
                case 'exists':
                    $operatorDisplay = 'exists';
                    break;
                case 'not_exists':
                    $operatorDisplay = 'does not exist';
                    break;
                default:
                    $operatorDisplay = $operator;
            }
            
            // Format value for display
            if ($operator === 'between' && !empty($value2)) {
                $valueDisplay = "{$value} and {$value2}";
            } elseif ($operator === 'in' && is_array($value)) {
                $valueDisplay = implode(', ', $value);
            } elseif ($operator === 'exists' || $operator === 'not_exists') {
                $valueDisplay = '';
            } else {
                $valueDisplay = $value;
            }
            
            // Combine all parts
            if (empty($valueDisplay)) {
                $formatted[] = "{$fieldDisplay} {$operatorDisplay}";
            } else {
                $formatted[] = "{$fieldDisplay} {$operatorDisplay} {$valueDisplay}";
            }
        }
        
        return implode(' AND ', $formatted);
    }
    
    /**
     * Generate a unique visitor ID
     * 
     * @return string Visitor ID
     */
    private function generateVisitorId()
    {
        return md5(uniqid('visitor_', true) . $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . time());
    }
    
    /**
     * Get geographic information from IP address
     * 
     * @param string $ip IP address
     * @return array Geographic info
     */
    private function getGeoInfoFromIp($ip)
    {
        try {
            // Use pconline API for geolocation
            $url = "https://whois.pconline.com.cn/ipJson.jsp?ip={$ip}&json=true";
            $response = file_get_contents($url);
            
            if ($response) {
                $data = json_decode($response, true);
                
                if ($data && isset($data['pro'])) {
                    return [
                        'country' => 'CN', // Default to China for this API
                        'region' => $data['pro'],
                        'city' => $data['city']
                    ];
                }
            }
            
            // Fallback for non-Chinese IPs or if API fails
            return [
                'country' => null,
                'region' => null,
                'city' => null
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error getting geo info: ' . $e->getMessage());
            return [
                'country' => null,
                'region' => null,
                'city' => null
            ];
        }
    }
    
    /**
     * Get device information from user agent
     * 
     * @param string $userAgent User agent string
     * @return array Device info
     */
    private function getDeviceInfoFromUserAgent($userAgent)
    {
        $deviceType = 'desktop';
        $browser = 'unknown';
        $os = 'unknown';
        
        if (!$userAgent) {
            return [
                'device_type' => $deviceType,
                'browser' => $browser,
                'os' => $os
            ];
        }
        
        // Detect device type
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $userAgent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($userAgent, 0, 4))) {
            $deviceType = 'mobile';
        } elseif (preg_match('/tablet|ipad|playbook|silk|android(?!.*mobile)/i', $userAgent)) {
            $deviceType = 'tablet';
        }
        
        // Detect browser
        if (preg_match('/chrome|chromium|crios/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/firefox|fxios/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/msie|trident/i', $userAgent)) {
            $browser = 'Internet Explorer';
        } elseif (preg_match('/edge|edg/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/opera|opr/i', $userAgent)) {
            $browser = 'Opera';
        }
        
        // Detect OS
        if (preg_match('/windows|win32|win64/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
            $os = 'Mac OS';
        } elseif (preg_match('/android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
            $os = 'iOS';
        } elseif (preg_match('/linux/i', $userAgent)) {
            $os = 'Linux';
        }
        
        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os
        ];
    }
} 