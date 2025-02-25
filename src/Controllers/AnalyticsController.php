<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Models\Advertisement;
use App\Models\Impression;
use App\Models\Click;

class AnalyticsController extends BaseController
{
    private $authService;
    private $advertisementModel;
    private $impressionModel;
    private $clickModel;

    public function __construct()
    {
        parent::__construct();
        
        // Initialize services and models
        $this->authService = new AuthService();
        $this->advertisementModel = new Advertisement();
        $this->impressionModel = new Impression();
        $this->clickModel = new Click();
    }

    /**
     * Display the analytics dashboard
     */
    public function dashboard()
    {
        // Verify admin or advertiser access
        if (!$this->authService->isAdmin() && !$this->authService->isAdvertiser()) {
            header('Location: /login');
            exit;
        }

        // Get current user
        $user = $this->authService->getCurrentUser();
        $isAdmin = $this->authService->isAdmin();

        // Get filter parameters
        $startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-30 days'));
        $endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
        $interval = filter_input(INPUT_GET, 'interval', FILTER_SANITIZE_STRING) ?: 'day';

        // Prepare filter options
        $filterOptions = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];

        // Get advertisements based on user role
        if ($isAdmin) {
            $advertisements = $this->advertisementModel->getAll();
        } else {
            $advertisements = $this->advertisementModel->getByAdvertiserId($user['id']);
        }

        // Initialize analytics data
        $analyticsData = [];

        foreach ($advertisements as $ad) {
            $adId = $ad['id'];
            
            // Get impression data
            $impressions = $this->impressionModel->getAggregatedByDate($adId, $interval, $filterOptions);
            
            // Get click data
            $clicks = $this->clickModel->getAggregatedByDate($adId, $interval, $filterOptions);
            
            // Get total metrics
            $totalImpressions = $this->impressionModel->getCountByAdId($adId, $filterOptions);
            $totalClicks = $this->clickModel->getCountByAdId($adId, $filterOptions);
            $ctr = $this->clickModel->getCtrByAdId($adId, $filterOptions);
            
            // Get geographic distribution
            $geoDistribution = $this->impressionModel->getGeoDistribution($adId);
            
            // Get device distribution
            $deviceDistribution = $this->impressionModel->getDeviceDistribution($adId);
            
            // Calculate total cost
            $totalCost = 0;
            foreach ($impressions as $imp) {
                $totalCost += $imp['total_cost'];
            }
            
            // Combine data for this ad
            $analyticsData[$adId] = [
                'ad' => $ad,
                'impressions' => $impressions,
                'clicks' => $clicks,
                'total_impressions' => $totalImpressions,
                'total_clicks' => $totalClicks,
                'ctr' => $ctr,
                'total_cost' => $totalCost,
                'geo_distribution' => $geoDistribution,
                'device_distribution' => $deviceDistribution
            ];
        }

        // Get summary metrics
        $summaryMetrics = $this->calculateSummaryMetrics($analyticsData);

        // Pass data to view
        require_once __DIR__ . '/../../templates/analytics/dashboard.php';
    }

    /**
     * Calculate summary metrics from analytics data
     * 
     * @param array $analyticsData Analytics data by ad
     * @return array Summary metrics
     */
    private function calculateSummaryMetrics($analyticsData)
    {
        $summary = [
            'total_impressions' => 0,
            'total_clicks' => 0,
            'total_cost' => 0,
            'average_ctr' => 0,
            'geo_distribution' => [],
            'device_distribution' => []
        ];

        foreach ($analyticsData as $data) {
            // Add up totals
            $summary['total_impressions'] += $data['total_impressions'];
            $summary['total_clicks'] += $data['total_clicks'];
            $summary['total_cost'] += $data['total_cost'];

            // Aggregate geographic distribution
            foreach ($data['geo_distribution'] as $geo) {
                $key = $geo['location_country'] . '|' . $geo['location_region'] . '|' . $geo['location_city'];
                if (!isset($summary['geo_distribution'][$key])) {
                    $summary['geo_distribution'][$key] = 0;
                }
                $summary['geo_distribution'][$key] += $geo['count'];
            }

            // Aggregate device distribution
            foreach ($data['device_distribution'] as $device) {
                if (!isset($summary['device_distribution'][$device['device_type']])) {
                    $summary['device_distribution'][$device['device_type']] = 0;
                }
                $summary['device_distribution'][$device['device_type']] += $device['count'];
            }
        }

        // Calculate average CTR
        if ($summary['total_impressions'] > 0) {
            $summary['average_ctr'] = round(($summary['total_clicks'] / $summary['total_impressions']) * 100, 2);
        }

        // Sort and limit geographic distribution
        arsort($summary['geo_distribution']);
        $summary['geo_distribution'] = array_slice($summary['geo_distribution'], 0, 10, true);

        // Sort device distribution
        arsort($summary['device_distribution']);

        return $summary;
    }

    /**
     * Export analytics data as CSV
     */
    public function exportCsv()
    {
        // Verify admin or advertiser access
        if (!$this->authService->isAdmin() && !$this->authService->isAdvertiser()) {
            header('Location: /login');
            exit;
        }

        // Get filter parameters
        $startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d', strtotime('-30 days'));
        $endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'Ad ID',
            'Ad Title',
            'Date',
            'Impressions',
            'Clicks',
            'CTR',
            'Cost'
        ]);
        
        // Get user's advertisements
        $user = $this->authService->getCurrentUser();
        if ($this->authService->isAdmin()) {
            $advertisements = $this->advertisementModel->getAll();
        } else {
            $advertisements = $this->advertisementModel->getByAdvertiserId($user['id']);
        }
        
        // Write data for each ad
        foreach ($advertisements as $ad) {
            $impressions = $this->impressionModel->getAggregatedByDate(
                $ad['id'],
                'day',
                ['start_date' => $startDate, 'end_date' => $endDate]
            );
            
            foreach ($impressions as $imp) {
                $clicks = $this->clickModel->getCountByAdId(
                    $ad['id'],
                    ['start_date' => $imp['date'], 'end_date' => $imp['date']]
                );
                
                $ctr = $imp['impressions'] > 0 ? 
                    round(($clicks / $imp['impressions']) * 100, 2) : 0;
                
                fputcsv($output, [
                    $ad['id'],
                    $ad['title'],
                    $imp['date'],
                    $imp['impressions'],
                    $clicks,
                    $ctr . '%',
                    number_format($imp['total_cost'], 2)
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
} 