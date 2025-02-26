<?php

namespace App\Controllers;

use App\Models\Conversion;
use App\Models\ConversionType;
use App\Models\User;
use App\Models\Advertisement;
use App\Utils\Cache;
use App\Utils\Request;
use App\Utils\Response;
use App\Utils\Session;
use App\Utils\Validator;

/**
 * ConversionController
 * 
 * Handles conversion tracking, pixel generation, and conversion analytics
 */
class ConversionController
{
    /**
     * @var Conversion $conversionModel
     */
    private $conversionModel;
    
    /**
     * @var ConversionType $typeModel
     */
    private $typeModel;
    
    /**
     * @var Cache $cache
     */
    private $cache;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->conversionModel = new Conversion();
        $this->typeModel = new ConversionType();
        $this->cache = new Cache();
    }
    
    /**
     * Display conversion types management page
     */
    public function conversionTypes()
    {
        // Verify admin access
        if (!Session::isAdmin()) {
            Response::redirect('/login');
            return;
        }
        
        $types = $this->typeModel->getAll();
        
        // Get conversion counts by type for statistics
        $counts = $this->typeModel->getConversionCountsByType();
        
        // Create a lookup map for easy display
        $countsMap = [];
        foreach ($counts as $count) {
            $countsMap[$count['id']] = $count;
        }
        
        require_once TEMPLATES_PATH . '/admin/conversion_types.php';
    }
    
    /**
     * Save a conversion type (create or update)
     */
    public function saveConversionType()
    {
        // Verify admin access and POST request
        if (!Session::isAdmin() || !Request::isPost()) {
            Response::redirect('/admin/conversion-types');
            return;
        }
        
        $id = Request::post('id');
        $data = [
            'name' => Request::post('name'),
            'description' => Request::post('description'),
            'value_type' => Request::post('value_type'),
            'default_value' => (float)Request::post('default_value')
        ];
        
        // Validate inputs
        $validator = new Validator();
        $validator->required('name', $data['name'], 'Name is required')
                  ->maxLength('name', $data['name'], 100, 'Name must be less than 100 characters')
                  ->inList('value_type', $data['value_type'], ['fixed', 'variable'], 'Invalid value type');
        
        if (!$validator->isValid()) {
            Session::setFlash('error', $validator->getFirstError());
            Response::redirect('/admin/conversion-types');
            return;
        }
        
        if ($id) {
            // Update existing
            $type = new ConversionType($id);
            $success = $type->update($data);
            $message = 'Conversion type updated successfully';
        } else {
            // Create new
            $success = $this->typeModel->create($data);
            $message = 'Conversion type created successfully';
        }
        
        if ($success) {
            Session::setFlash('success', $message);
        } else {
            Session::setFlash('error', 'Failed to save conversion type');
        }
        
        Response::redirect('/admin/conversion-types');
    }
    
    /**
     * Delete a conversion type
     * 
     * @param int $id Conversion type ID
     */
    public function deleteConversionType($id)
    {
        // Verify admin access
        if (!Session::isAdmin()) {
            Response::redirect('/admin/conversion-types');
            return;
        }
        
        $type = new ConversionType($id);
        if ($type->delete()) {
            Session::setFlash('success', 'Conversion type deleted successfully');
        } else {
            Session::setFlash('error', 'Cannot delete conversion type that is in use');
        }
        
        Response::redirect('/admin/conversion-types');
    }
    
    /**
     * Display conversion pixel management page for advertiser
     */
    public function conversionPixels()
    {
        // Verify user is logged in
        if (!Session::isLoggedIn()) {
            Response::redirect('/login');
            return;
        }
        
        $userId = Session::getUserId();
        
        // Get user's conversion pixels
        $sql = "SELECT p.*, ct.name as type_name 
                FROM conversion_pixels p
                JOIN conversion_types ct ON p.conversion_type_id = ct.id
                WHERE p.user_id = ?
                ORDER BY p.name";
                
        $db = \App\Utils\Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $pixels = $stmt->fetchAll();
        
        // Get available conversion types for the dropdown
        $conversionTypes = $this->typeModel->getAll();
        
        require_once TEMPLATES_PATH . '/advertiser/conversion_pixels.php';
    }
    
    /**
     * Generate a new conversion tracking pixel
     */
    public function generatePixel()
    {
        // Verify user is logged in and POST request
        if (!Session::isLoggedIn() || !Request::isPost()) {
            Response::redirect('/advertiser/conversion-pixels');
            return;
        }
        
        $userId = Session::getUserId();
        $data = [
            'name' => Request::post('name'),
            'conversion_type_id' => (int)Request::post('conversion_type_id')
        ];
        
        // Validate inputs
        $validator = new Validator();
        $validator->required('name', $data['name'], 'Name is required')
                  ->maxLength('name', $data['name'], 100, 'Name must be less than 100 characters')
                  ->required('conversion_type_id', $data['conversion_type_id'], 'Conversion type is required');
        
        if (!$validator->isValid()) {
            Session::setFlash('error', $validator->getFirstError());
            Response::redirect('/advertiser/conversion-pixels');
            return;
        }
        
        // Generate a unique pixel ID
        $pixelId = bin2hex(random_bytes(16)); // 32 character hex string
        
        // Save to database
        $sql = "INSERT INTO conversion_pixels
                (user_id, name, pixel_id, conversion_type_id, is_active)
                VALUES (?, ?, ?, ?, 1)";
                
        $db = \App\Utils\Database::getConnection();
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([
            $userId, 
            $data['name'], 
            $pixelId, 
            $data['conversion_type_id']
        ]);
        
        if ($success) {
            Session::setFlash('success', 'Conversion pixel created successfully');
        } else {
            Session::setFlash('error', 'Failed to create conversion pixel');
        }
        
        Response::redirect('/advertiser/conversion-pixels');
    }
    
    /**
     * Delete a conversion tracking pixel
     * 
     * @param string $pixelId Pixel ID
     */
    public function deletePixel($pixelId)
    {
        // Verify user is logged in
        if (!Session::isLoggedIn()) {
            Response::redirect('/advertiser/conversion-pixels');
            return;
        }
        
        $userId = Session::getUserId();
        
        // Delete the pixel
        $sql = "DELETE FROM conversion_pixels
                WHERE pixel_id = ? AND user_id = ?";
                
        $db = \App\Utils\Database::getConnection();
        $stmt = $db->prepare($sql);
        $success = $stmt->execute([$pixelId, $userId]);
        
        if ($success) {
            Session::setFlash('success', 'Conversion pixel deleted successfully');
        } else {
            Session::setFlash('error', 'Failed to delete conversion pixel');
        }
        
        Response::redirect('/advertiser/conversion-pixels');
    }
    
    /**
     * Record a conversion from a tracking pixel
     * 
     * This is the endpoint that the pixel JS will call
     */
    public function recordPixelConversion()
    {
        // Allow CORS for pixel tracking
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        // Get pixel ID from query string
        $pixelId = Request::get('pixel_id');
        if (!$pixelId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing pixel ID']);
            exit;
        }
        
        // Get ad ID and click ID if available
        $adId = Request::get('ad_id');
        $clickId = Request::get('click_id');
        $orderId = Request::get('order_id');
        $value = Request::get('value');
        
        // Look up the pixel to get the conversion type
        $sql = "SELECT p.*, ct.value_type, ct.default_value
                FROM conversion_pixels p
                JOIN conversion_types ct ON p.conversion_type_id = ct.id
                WHERE p.pixel_id = ? AND p.is_active = 1";
                
        $db = \App\Utils\Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([$pixelId]);
        $pixel = $stmt->fetch();
        
        if (!$pixel) {
            http_response_code(404);
            echo json_encode(['error' => 'Invalid or inactive pixel']);
            exit;
        }
        
        // If no ad_id provided but we have a click_id, try to look up the ad_id
        if (!$adId && $clickId) {
            $sql = "SELECT ad_id FROM ad_clicks WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$clickId]);
            $click = $stmt->fetch();
            
            if ($click) {
                $adId = $click['ad_id'];
            }
        }
        
        // If we still don't have an ad_id, we can't record the conversion
        if (!$adId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing ad ID']);
            exit;
        }
        
        // Get visitor identifier (use session cookie if available, or generate one)
        $visitorId = $_COOKIE['visitor_id'] ?? md5($_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT'] . time());
        
        // If no value provided but conversion type is variable, use the default
        if (($value === null || $value === '') && $pixel['value_type'] === 'variable') {
            $value = $pixel['default_value'];
        }
        
        // Prepare conversion data
        $conversionData = [
            'ad_id' => $adId,
            'click_id' => $clickId,
            'conversion_type_id' => $pixel['conversion_type_id'],
            'visitor_id' => $visitorId,
            'order_id' => $orderId,
            'value' => $value,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'referrer' => $_SERVER['HTTP_REFERER'] ?? null
        ];
        
        // Record the conversion
        $conversionId = $this->conversionModel->track($conversionData);
        
        if ($conversionId) {
            // Return success response
            echo json_encode([
                'success' => true,
                'conversion_id' => $conversionId
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to record conversion']);
        }
        
        exit;
    }
    
    /**
     * Display conversion analytics for an advertiser
     */
    public function advertiserConversions()
    {
        // Verify user is logged in
        if (!Session::isLoggedIn()) {
            Response::redirect('/login');
            return;
        }
        
        $userId = Session::getUserId();
        
        // Get filter parameters
        $startDate = Request::get('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = Request::get('end_date', date('Y-m-d'));
        $typeId = Request::get('type_id');
        
        // Get all user's ads
        $adModel = new Advertisement();
        $ads = $adModel->getByAdvertiserId($userId);
        
        // Get conversion types for filter
        $types = $this->typeModel->getAll();
        
        // Get summary metrics
        $totalConversions = 0;
        $totalValue = 0;
        $conversionRate = 0;
        $totalClicks = 0;
        
        // Build ad IDs array
        $adIds = array_column($ads, 'id');
        
        if (!empty($adIds)) {
            // Get total clicks
            $sql = "SELECT COUNT(*) as total_clicks 
                    FROM ad_clicks 
                    WHERE ad_id IN (" . implode(',', array_fill(0, count($adIds), '?')) . ")
                    AND created_at BETWEEN ? AND ?";
                    
            $params = array_merge($adIds, [$startDate, $endDate]);
            
            $db = \App\Utils\Database::getConnection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $clicksResult = $stmt->fetch();
            $totalClicks = (int)$clicksResult['total_clicks'];
            
            // Get total conversions and value
            $sql = "SELECT COUNT(*) as total_conversions, SUM(value) as total_value
                    FROM conversions
                    WHERE ad_id IN (" . implode(',', array_fill(0, count($adIds), '?')) . ")
                    AND conversion_time BETWEEN ? AND ?";
                    
            if ($typeId) {
                $sql .= " AND conversion_type_id = ?";
                $params = array_merge($adIds, [$startDate, $endDate, $typeId]);
            } else {
                $params = array_merge($adIds, [$startDate, $endDate]);
            }
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            
            $totalConversions = (int)$result['total_conversions'];
            $totalValue = (float)$result['total_value'];
            
            // Calculate conversion rate
            if ($totalClicks > 0) {
                $conversionRate = ($totalConversions / $totalClicks) * 100;
            }
            
            // Get conversions by day for chart
            $sql = "SELECT DATE(conversion_time) as date, COUNT(*) as conversions, SUM(value) as value
                    FROM conversions
                    WHERE ad_id IN (" . implode(',', array_fill(0, count($adIds), '?')) . ")
                    AND conversion_time BETWEEN ? AND ?";
                    
            if ($typeId) {
                $sql .= " AND conversion_type_id = ?";
            }
            
            $sql .= " GROUP BY DATE(conversion_time)
                      ORDER BY date ASC";
                    
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $dailyData = $stmt->fetchAll();
            
            // Get conversions by type for pie chart
            $sql = "SELECT ct.name, COUNT(c.id) as conversions, SUM(c.value) as value
                    FROM conversions c
                    JOIN conversion_types ct ON c.conversion_type_id = ct.id
                    WHERE c.ad_id IN (" . implode(',', array_fill(0, count($adIds), '?')) . ")
                    AND c.conversion_time BETWEEN ? AND ?
                    GROUP BY ct.name
                    ORDER BY conversions DESC";
                    
            $params = array_merge($adIds, [$startDate, $endDate]);
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $typeData = $stmt->fetchAll();
            
            // Get recent conversions
            $sql = "SELECT c.*, a.title as ad_title, ct.name as type_name
                    FROM conversions c
                    JOIN advertisements a ON c.ad_id = a.id
                    JOIN conversion_types ct ON c.conversion_type_id = ct.id
                    WHERE c.ad_id IN (" . implode(',', array_fill(0, count($adIds), '?')) . ")
                    AND c.conversion_time BETWEEN ? AND ?";
                    
            if ($typeId) {
                $sql .= " AND c.conversion_type_id = ?";
                $params = array_merge($adIds, [$startDate, $endDate, $typeId]);
            } else {
                $params = array_merge($adIds, [$startDate, $endDate]);
            }
            
            $sql .= " ORDER BY c.conversion_time DESC LIMIT 50";
            
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $recentConversions = $stmt->fetchAll();
        } else {
            $dailyData = [];
            $typeData = [];
            $recentConversions = [];
        }
        
        require_once TEMPLATES_PATH . '/advertiser/conversions.php';
    }
    
    /**
     * Get the tracking pixel JavaScript code
     */
    public function getPixelCode($pixelId)
    {
        // Verify user is logged in
        if (!Session::isLoggedIn()) {
            Response::redirect('/login');
            return;
        }
        
        $userId = Session::getUserId();
        
        // Get the pixel details
        $sql = "SELECT p.*, ct.name as type_name 
                FROM conversion_pixels p
                JOIN conversion_types ct ON p.conversion_type_id = ct.id
                WHERE p.pixel_id = ? AND p.user_id = ?";
                
        $db = \App\Utils\Database::getConnection();
        $stmt = $db->prepare($sql);
        $stmt->execute([$pixelId, $userId]);
        $pixel = $stmt->fetch();
        
        if (!$pixel) {
            Session::setFlash('error', 'Invalid pixel ID');
            Response::redirect('/advertiser/conversion-pixels');
            return;
        }
        
        $conversionType = $this->typeModel->find($pixel['conversion_type_id']);
        
        // Set page variables
        $pixelCode = $this->generatePixelJsCode($pixel);
        $pixelName = $pixel['name'];
        $typeName = $pixel['type_name'];
        
        require_once TEMPLATES_PATH . '/advertiser/pixel_code.php';
    }
    
    /**
     * Generate the JavaScript code for a conversion pixel
     * 
     * @param array $pixel Pixel data
     * @return string JavaScript code
     */
    private function generatePixelJsCode($pixel)
    {
        $baseUrl = URL_ROOT;
        $pixelId = $pixel['pixel_id'];
        
        $jsCode = <<<EOT
<!-- HFI Conversion Tracking Pixel -->
<script type="text/javascript">
(function() {
    // Create the base tracking function
    window.HFITrack = window.HFITrack || function(options) {
        var params = options || {};
        params.pixel_id = '{$pixelId}';
        
        // Get tracking values from URL parameters
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('ad_id')) params.ad_id = urlParams.get('ad_id');
        if (urlParams.has('click_id')) params.click_id = urlParams.get('click_id');
        
        // Append query string
        var queryString = Object.keys(params).map(function(key) {
            return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }).join('&');
        
        // Create the tracking pixel
        var img = new Image(1, 1);
        img.src = '{$baseUrl}/api/v1/track/conversion?' + queryString;
        img.style.display = 'none';
        document.body.appendChild(img);
        
        return true;
    };
    
    // Set up automatic tracking if specified with pixel attributes
    if (document.currentScript && document.currentScript.hasAttribute('data-auto-track')) {
        document.addEventListener('DOMContentLoaded', function() {
            var options = {};
            
            // Get value if specified
            if (document.currentScript.hasAttribute('data-value')) {
                options.value = document.currentScript.getAttribute('data-value');
            }
            
            // Get order ID if specified
            if (document.currentScript.hasAttribute('data-order-id')) {
                options.order_id = document.currentScript.getAttribute('data-order-id');
            }
            
            window.HFITrack(options);
        });
    }
})();
</script>
<!-- End HFI Conversion Tracking Pixel -->
EOT;
        
        return $jsCode;
    }
} 