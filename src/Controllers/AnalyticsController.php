<?php

namespace VertoAD\Core\Controllers;

use VertoAD\Core\Services\AuthService;
use VertoAD\Core\Models\Advertisement;
use VertoAD\Core\Models\Impression;
use VertoAD\Core\Models\Click;
use VertoAD\Core\Models\Conversion;
use VertoAD\Core\Models\ConversionType;
use VertoAD\Core\Services\AnalyticsCacheService;

/**
 * Analytics Controller
 */
class AnalyticsController extends BaseController
{
    /**
     * @var AuthService $authService
     */
    private $authService;
    
    /**
     * @var Advertisement $advertisementModel
     */
    private $advertisementModel;
    
    /**
     * @var Impression $impressionModel
     */
    private $impressionModel;
    
    /**
     * @var Click $clickModel
     */
    private $clickModel;
    
    /**
     * @var Conversion $conversionModel
     */
    private $conversionModel;
    
    /**
     * @var ConversionType $conversionTypeModel
     */
    private $conversionTypeModel;
    
    /**
     * @var AnalyticsCacheService $cacheService
     */
    private $cacheService;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->authService = new AuthService();
        $this->advertisementModel = new Advertisement($this->db);
        $this->impressionModel = new Impression($this->db);
        $this->clickModel = new Click($this->db);
        $this->conversionModel = new Conversion($this->db);
        $this->conversionTypeModel = new ConversionType($this->db);
        $this->cacheService = new AnalyticsCacheService();
    }
    
    /**
     * Display analytics dashboard
     */
    public function dashboard()
    {
        // Verify user is logged in
        if (!$this->authService->isLoggedIn()) {
            $this->redirect('/login');
        }
        
        $user = $this->authService->getCurrentUser();
        $isAdmin = $user['role'] === 'admin';
        $isAdvertiser = $user['role'] === 'advertiser';
        
        if (!$isAdmin && !$isAdvertiser) {
            $this->redirect('/dashboard');
        }
        
        // Get filter parameters
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $interval = $_GET['interval'] ?? 'daily';
        
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'interval' => $interval
        ];
        
        // Try to get cached dashboard summary
        $dashboardSummary = $this->cacheService->getCachedDashboardSummary($user['id'], $filters);
        
        if (!$dashboardSummary) {
            // Get advertisements based on user role
            if ($isAdmin) {
                $advertisements = $this->advertisementModel->getAll();
            } else {
                $advertisements = $this->advertisementModel->getByAdvertiserId($user['id']);
            }
            
            $analyticsData = [];
            $totalImpressions = 0;
            $totalClicks = 0;
            $totalConversions = 0;
            $totalCost = 0;
            $totalRevenue = 0;
            $geoDistribution = [];
            $deviceDistribution = [];
            
            foreach ($advertisements as $ad) {
                // Try to get cached analytics for this ad
                $adAnalytics = $this->getAdAnalytics($ad['id'], $filters);
                
                $analyticsData[$ad['id']] = $adAnalytics;
                
                // Aggregate totals
                $totalImpressions += $adAnalytics['impressions'];
                $totalClicks += $adAnalytics['clicks'];
                $totalConversions += $adAnalytics['conversions'];
                $totalCost += $adAnalytics['cost'];
                $totalRevenue += $adAnalytics['revenue'];
                
                // Aggregate geo distribution
                foreach ($adAnalytics['geo_distribution'] as $country => $count) {
                    if (!isset($geoDistribution[$country])) {
                        $geoDistribution[$country] = 0;
                    }
                    $geoDistribution[$country] += $count;
                }
                
                // Aggregate device distribution
                foreach ($adAnalytics['device_distribution'] as $device => $count) {
                    if (!isset($deviceDistribution[$device])) {
                        $deviceDistribution[$device] = 0;
                    }
                    $deviceDistribution[$device] += $count;
                }
            }
            
            // Calculate overall metrics
            $totalCtr = $totalImpressions > 0 ? ($totalClicks / $totalImpressions) * 100 : 0;
            $totalConversionRate = $totalClicks > 0 ? ($totalConversions / $totalClicks) * 100 : 0;
            $totalRoi = $totalCost > 0 ? (($totalRevenue - $totalCost) / $totalCost) * 100 : 0;
            
            // Sort geo distribution by count
            arsort($geoDistribution);
            $geoDistribution = array_slice($geoDistribution, 0, 10);
            
            // Sort device distribution by count
            arsort($deviceDistribution);
            
            $dashboardSummary = [
                'advertisements' => $advertisements,
                'analytics_data' => $analyticsData,
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'total_conversions' => $totalConversions,
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'total_ctr' => $totalCtr,
                'total_conversion_rate' => $totalConversionRate,
                'total_roi' => $totalRoi,
                'geo_distribution' => $geoDistribution,
                'device_distribution' => $deviceDistribution,
                'filters' => $filters
            ];
            
            // Cache the dashboard summary
            $this->cacheService->cacheDashboardSummary($user['id'], $dashboardSummary, $filters);
        }
        
        // Get conversion types for filtering
        $conversionTypes = $this->conversionTypeModel->getAll();
        $dashboardSummary['conversion_types'] = $conversionTypes;
        
        $this->render('analytics/dashboard', $dashboardSummary);
    }
    
    /**
     * Get analytics data for a specific ad
     * 
     * @param int $adId Ad ID
     * @param array $filters Filters
     * @return array Analytics data
     */
    private function getAdAnalytics($adId, $filters)
    {
        return $this->cacheService->remember('ad_analytics', $adId, $filters, function() use ($adId, $filters) {
            // Get impressions
            $impressions = $this->impressionModel->getCountByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Get clicks
            $clicks = $this->clickModel->getCountByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Get conversions
            $conversions = $this->conversionModel->getCountByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Get cost
            $cost = $this->impressionModel->getTotalCostByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Get revenue (conversion value)
            $revenue = $this->conversionModel->getTotalValueByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Calculate CTR
            $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
            
            // Calculate conversion rate
            $conversionRate = $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
            
            // Calculate ROI
            $roi = $cost > 0 ? (($revenue - $cost) / $cost) * 100 : 0;
            
            // Get geo distribution
            $geoDistribution = $this->impressionModel->getGeoDistributionByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Get device distribution
            $deviceDistribution = $this->impressionModel->getDeviceDistributionByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            // Get daily data
            $dailyData = $this->getDailyData($adId, $filters);
            
            // Get conversion data by type
            $conversionsByType = $this->conversionModel->getDataByType($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            return [
                'impressions' => $impressions,
                'clicks' => $clicks,
                'conversions' => $conversions,
                'cost' => $cost,
                'revenue' => $revenue,
                'ctr' => $ctr,
                'conversion_rate' => $conversionRate,
                'roi' => $roi,
                'geo_distribution' => $geoDistribution,
                'device_distribution' => $deviceDistribution,
                'daily_data' => $dailyData,
                'conversions_by_type' => $conversionsByType
            ];
        });
    }
    
    /**
     * Get daily analytics data for an ad
     * 
     * @param int $adId Ad ID
     * @param array $filters Filters
     * @return array Daily data
     */
    private function getDailyData($adId, $filters)
    {
        return $this->cacheService->remember('daily_data', $adId, $filters, function() use ($adId, $filters) {
            // Get date range
            $startDate = new \DateTime($filters['start_date']);
            $endDate = new \DateTime($filters['end_date']);
            $interval = new \DateInterval('P1D');
            $dateRange = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));
            
            // Initialize daily data array
            $dailyData = [];
            foreach ($dateRange as $date) {
                $dateStr = $date->format('Y-m-d');
                $dailyData[$dateStr] = [
                    'date' => $dateStr,
                    'impressions' => 0,
                    'clicks' => 0,
                    'conversions' => 0,
                    'cost' => 0,
                    'revenue' => 0,
                    'ctr' => 0,
                    'conversion_rate' => 0,
                    'roi' => 0
                ];
            }
            
            // Get daily impressions
            $dailyImpressions = $this->impressionModel->getDailyDataByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            foreach ($dailyImpressions as $data) {
                if (isset($dailyData[$data['date']])) {
                    $dailyData[$data['date']]['impressions'] = (int)$data['impressions'];
                    $dailyData[$data['date']]['cost'] = (float)$data['total_cost'];
                }
            }
            
            // Get daily clicks
            $dailyClicks = $this->clickModel->getDailyDataByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            foreach ($dailyClicks as $data) {
                if (isset($dailyData[$data['date']])) {
                    $dailyData[$data['date']]['clicks'] = (int)$data['clicks'];
                    
                    // Calculate CTR
                    $impressions = $dailyData[$data['date']]['impressions'];
                    $clicks = $dailyData[$data['date']]['clicks'];
                    $dailyData[$data['date']]['ctr'] = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
                }
            }
            
            // Get daily conversions
            $dailyConversions = $this->conversionModel->getDailyDataByAdId($adId, [
                'start_date' => $filters['start_date'],
                'end_date' => $filters['end_date']
            ]);
            
            foreach ($dailyConversions as $data) {
                if (isset($dailyData[$data['date']])) {
                    $dailyData[$data['date']]['conversions'] = (int)$data['conversions'];
                    $dailyData[$data['date']]['revenue'] = (float)$data['total_value'];
                    
                    // Calculate conversion rate
                    $clicks = $dailyData[$data['date']]['clicks'];
                    $conversions = $dailyData[$data['date']]['conversions'];
                    $dailyData[$data['date']]['conversion_rate'] = $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
                    
                    // Calculate ROI
                    $cost = $dailyData[$data['date']]['cost'];
                    $revenue = $dailyData[$data['date']]['revenue'];
                    $dailyData[$data['date']]['roi'] = $cost > 0 ? (($revenue - $cost) / $cost) * 100 : 0;
                }
            }
            
            // Convert to indexed array
            return array_values($dailyData);
        });
    }
    
    /**
     * Export analytics data as CSV
     */
    public function exportCsv()
    {
        // Verify user is logged in
        if (!$this->authService->isLoggedIn()) {
            $this->redirect('/login');
        }
        
        $user = $this->authService->getCurrentUser();
        $isAdmin = $user['role'] === 'admin';
        $isAdvertiser = $user['role'] === 'advertiser';
        
        if (!$isAdmin && !$isAdvertiser) {
            $this->redirect('/dashboard');
        }
        
        // Get filter parameters
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $interval = $_GET['interval'] ?? 'daily';
        
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'interval' => $interval
        ];
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Ad ID', 'Ad Title', 'Date', 'Impressions', 'Clicks', 'Conversions',
            'CTR (%)', 'Conversion Rate (%)', 'Cost', 'Revenue', 'ROI (%)'
        ]);
        
        // Get advertisements based on user role
        if ($isAdmin) {
            $advertisements = $this->advertisementModel->getAll();
        } else {
            $advertisements = $this->advertisementModel->getByAdvertiserId($user['id']);
        }
        
        foreach ($advertisements as $ad) {
            // Get daily data for this ad
            $dailyData = $this->getDailyData($ad['id'], $filters);
            
            foreach ($dailyData as $data) {
                fputcsv($output, [
                    $ad['id'],
                    $ad['title'],
                    $data['date'],
                    $data['impressions'],
                    $data['clicks'],
                    $data['conversions'],
                    number_format($data['ctr'], 2),
                    number_format($data['conversion_rate'], 2),
                    number_format($data['cost'], 2),
                    number_format($data['revenue'], 2),
                    number_format($data['roi'], 2)
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Display conversion analytics
     */
    public function conversionAnalytics()
    {
        // Verify user is logged in
        if (!$this->authService->isLoggedIn()) {
            $this->redirect('/login');
        }
        
        $user = $this->authService->getCurrentUser();
        $isAdmin = $user['role'] === 'admin';
        $isAdvertiser = $user['role'] === 'advertiser';
        
        if (!$isAdmin && !$isAdvertiser) {
            $this->redirect('/dashboard');
        }
        
        // Get filter parameters
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $conversionTypeId = $_GET['conversion_type_id'] ?? null;
        
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'conversion_type_id' => $conversionTypeId
        ];
        
        // Get advertisements based on user role
        if ($isAdmin) {
            $advertisements = $this->advertisementModel->getAll();
        } else {
            $advertisements = $this->advertisementModel->getByAdvertiserId($user['id']);
        }
        
        $conversionData = [];
        
        foreach ($advertisements as $ad) {
            // Try to get cached conversion data for this ad
            $adConversions = $this->cacheService->getCachedConversionsByAdId($ad['id'], $filters);
            
            if (!$adConversions) {
                $adConversions = $this->conversionModel->getByAdId($ad['id'], $filters);
                $this->cacheService->cacheConversionsByAdId($ad['id'], $adConversions, $filters);
            }
            
            $conversionData[$ad['id']] = [
                'ad' => $ad,
                'conversions' => $adConversions,
                'total_count' => count($adConversions),
                'total_value' => array_sum(array_column($adConversions, 'conversion_value')),
                'conversion_rate' => $this->conversionModel->calculateConversionRate($ad['id'], $filters)
            ];
        }
        
        // Get conversion types for filtering
        $conversionTypes = $this->conversionTypeModel->getAll();
        
        $this->render('analytics/conversions', [
            'conversion_data' => $conversionData,
            'conversion_types' => $conversionTypes,
            'filters' => $filters
        ]);
    }
    
    /**
     * Display ROI analytics
     */
    public function roiAnalytics()
    {
        // Verify user is logged in
        if (!$this->authService->isLoggedIn()) {
            $this->redirect('/login');
        }
        
        $user = $this->authService->getCurrentUser();
        $isAdmin = $user['role'] === 'admin';
        $isAdvertiser = $user['role'] === 'advertiser';
        
        if (!$isAdmin && !$isAdvertiser) {
            $this->redirect('/dashboard');
        }
        
        // Get filter parameters
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        // Get advertisements based on user role
        if ($isAdmin) {
            $advertisements = $this->advertisementModel->getAll();
        } else {
            $advertisements = $this->advertisementModel->getByAdvertiserId($user['id']);
        }
        
        $roiData = [];
        
        foreach ($advertisements as $ad) {
            // Try to get cached ROI data for this ad
            $adRoi = $this->cacheService->getCachedRoiAnalyticsByAdId($ad['id'], $filters);
            
            if (!$adRoi) {
                // Get impressions and cost
                $impressions = $this->impressionModel->getCountByAdId($ad['id'], $filters);
                $cost = $this->impressionModel->getTotalCostByAdId($ad['id'], $filters);
                
                // Get clicks
                $clicks = $this->clickModel->getCountByAdId($ad['id'], $filters);
                
                // Get conversions and revenue
                $conversions = $this->conversionModel->getCountByAdId($ad['id'], $filters);
                $revenue = $this->conversionModel->getTotalValueByAdId($ad['id'], $filters);
                
                // Calculate metrics
                $ctr = $impressions > 0 ? ($clicks / $impressions) * 100 : 0;
                $conversionRate = $clicks > 0 ? ($conversions / $clicks) * 100 : 0;
                $roi = $cost > 0 ? (($revenue - $cost) / $cost) * 100 : 0;
                $cpc = $clicks > 0 ? $cost / $clicks : 0;
                $cpa = $conversions > 0 ? $cost / $conversions : 0;
                
                $adRoi = [
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'cost' => $cost,
                    'revenue' => $revenue,
                    'ctr' => $ctr,
                    'conversion_rate' => $conversionRate,
                    'roi' => $roi,
                    'cpc' => $cpc,
                    'cpa' => $cpa
                ];
                
                $this->cacheService->cacheRoiAnalyticsByAdId($ad['id'], $adRoi, $filters);
            }
            
            $roiData[$ad['id']] = [
                'ad' => $ad,
                'metrics' => $adRoi
            ];
        }
        
        $this->render('analytics/roi', [
            'roi_data' => $roiData,
            'filters' => $filters
        ]);
    }
} 