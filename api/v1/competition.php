<?php

require_once __DIR__ . '/../../src/Services/AuthService.php';
require_once __DIR__ . '/../../src/Services/CompetitionService.php';
require_once __DIR__ . '/../../src/Utils/Logger.php';

header('Content-Type: application/json');

$auth = new AuthService();
$competitionService = new CompetitionService();
$logger = new Logger();

try {
    // Require authentication and admin role for all competition endpoints
    $token = $auth->validateRequest();
    if (!$token || $token['role'] !== 'admin') {
        throw new Exception('Permission denied: Admin access required', 403);
    }

    // Handle request based on action
    $action = $_GET['action'] ?? 'position';
    
    switch ($action) {
        case 'position':
            handlePositionCompetition($competitionService);
            break;
            
        case 'industry':
            handleIndustryAnalysis($competitionService);
            break;
            
        case 'trends':
            handlePriceTrends($competitionService);
            break;
            
        case 'insights':
            handleCompetitiveInsights($competitionService);
            break;
            
        default:
            throw new Exception('Invalid action', 400);
    }
} catch (Exception $e) {
    // Log error
    $logger->error('Competition API error', [
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);

    // Return error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle position competition metrics request
 */
function handlePositionCompetition($competitionService) {
    // Validate position ID
    if (!isset($_GET['id'])) {
        throw new Exception('Position ID is required', 400);
    }
    
    $positionId = intval($_GET['id']);
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    // Get competition metrics
    $metrics = $competitionService->getPositionCompetition($positionId, $startDate, $endDate);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $metrics
    ]);
}

/**
 * Handle industry analysis request
 */
function handleIndustryAnalysis($competitionService) {
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    // Get industry analysis
    $analysis = $competitionService->getIndustryAnalysis($startDate, $endDate);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $analysis
    ]);
}

/**
 * Handle price trends request
 */
function handlePriceTrends($competitionService) {
    $positionId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $period = $_GET['period'] ?? 'daily';
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    
    // Validate period
    $validPeriods = ['hourly', 'daily', 'weekly', 'monthly'];
    if (!in_array($period, $validPeriods)) {
        throw new Exception('Invalid period. Valid options: ' . implode(', ', $validPeriods), 400);
    }
    
    // Get price trends
    $trends = $competitionService->getPriceTrends($positionId, $period, $startDate, $endDate);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $trends
    ]);
}

/**
 * Handle competitive insights request
 */
function handleCompetitiveInsights($competitionService) {
    // Validate position ID
    if (!isset($_GET['id'])) {
        throw new Exception('Position ID is required', 400);
    }
    
    $positionId = intval($_GET['id']);
    
    // Get competitive insights
    $insights = $competitionService->getCompetitiveInsights($positionId);
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $insights
    ]);
}
