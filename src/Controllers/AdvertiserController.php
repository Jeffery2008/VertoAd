<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\KeyRedemptionService;
use App\Services\AccountService;
use App\Services\KeyGenerationService;

class AdvertiserController extends BaseController
{
    private $keyRedemptionService;
    private $accountService;
    private $authService; // Add AuthService

    public function __construct()
    {
        parent::__construct();

        // Initialize services
        $this->keyRedemptionService = new KeyRedemptionService(
            $this->logger,
            new KeyGenerationService($this->db, $this->logger),
            new AccountService($this->db, $this->logger),
            $this->db
        );
        $this->accountService = new AccountService($this->db, $this->logger);
        $this->authService = new AuthService(); // Initialize AuthService
    }

    /**
     * Display the key activation form page
     */
    public function showActivatePage()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->redirect('/login?redirect=' . urlencode('/advertiser/activate'));
            return;
        }

        $user = $this->auth->getUser();

        // Get recent activations for display
        $activations = $this->keyRedemptionService->getUserActivationHistory($user->getId(), 3);

        $this->render('advertiser/activate', [
            'user' => $user,
            'activations' => $activations
        ]);
    }

    /**
     * Display the activation success page
     */
    public function showActivationSuccess()
    {
        // Check if user is authenticated
        if (!$this->auth->check()) {
            $this->redirect('/login');
            return;
        }

        // Validate amount parameter
        $amount = filter_input(INPUT_GET, 'amount', FILTER_VALIDATE_FLOAT);
        if ($amount === false) {
            // Redirect back to activation page if amount is invalid
            $this->redirect('/advertiser/activate');
            return;
        }

        $user = $this->auth->getUser();
        $balance = $this->accountService->getBalance($user->getId());

        $this->render('advertiser/activation-success', [
            'user' => $user,
            'amount' => $amount,
            'previousBalance' => $balance - $amount,
            'newBalance' => $balance
        ]);
    }

    /**
     * Display the advertiser dashboard
     */
    public function dashboard() {
        // Verify advertiser access
        if (!$this->authService->isAdvertiser()) {
            header('Location: /login'); // Redirect to login if not advertiser
            exit;
        }

        // For now, just render the dashboard template with a welcome message
        $advertiser = $this->authService->getCurrentUser(); // Get current advertiser user data
        require_once __DIR__ . '/../../templates/advertiser/dashboard.php';
    }

    /**
     * List all ads for the current advertiser
     */
    public function listAds() {
        // Verify advertiser access
        if (!$this->authService->isAdvertiser()) {
            header('Location: /login'); // Redirect to login if not advertiser
            exit;
        }

        $advertiser = $this->authService->getCurrentUser();
        
        // Fetch ads for the advertiser from the database
        $advertisementModel = new \App\Models\Advertisement();
        $stmt = $this->db->prepare("
            SELECT a.*, p.name as position_name, p.width, p.height 
            FROM advertisements a
            JOIN ad_positions p ON a.position_id = p.id
            WHERE a.advertiser_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$advertiser['id']]);
        $advertisements = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        require_once __DIR__ . '/../../templates/advertiser/ads_list.php';
    }

    /**
     * Display ad creation form (GET) or handle ad creation (POST)
     */
    public function createAd() {
        // Verify advertiser access
        if (!$this->authService->isAdvertiser()) {
            header('Location: /login');
            exit;
        }

        $advertiser = $this->authService->getCurrentUser();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validate and sanitize input
            $adData = [
                'position_id' => filter_input(INPUT_POST, 'position_id', FILTER_VALIDATE_INT),
                'title' => filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING),
                'content' => json_encode([
                    'html' => '',  // Will be populated when using the canvas
                    'canvas_data' => '' // Will be populated when using the canvas
                ]),
                'start_date' => filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING),
                'end_date' => filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING) ?: null,
                'budget' => filter_input(INPUT_POST, 'budget', FILTER_VALIDATE_FLOAT),
                'bid_amount' => filter_input(INPUT_POST, 'bid_amount', FILTER_VALIDATE_FLOAT),
                'advertiser_id' => $advertiser['id'],
                'status' => 'pending'
            ];

            // Validate required fields
            if (!$adData['position_id'] || !$adData['title'] || 
                !$adData['start_date'] || !$adData['budget'] || !$adData['bid_amount']) {
                $_SESSION['error'] = 'Please fill in all required fields';
                header('Location: /advertiser/create-ad');
                exit;
            }

            try {
                // Use the Advertisement model to create the ad
                $advertisementModel = new \App\Models\Advertisement();
                $adId = $advertisementModel->create($adData);
                
                if ($adId) {
                    // Initialize AdTargeting model
                    $adTargetingModel = new \App\Models\AdTargeting();
                    
                    // Handle location targeting
                    $locations = isset($_POST['locations']) ? $_POST['locations'] : [];
                    if (!empty($locations)) {
                        foreach ($locations as $location) {
                            if (!empty($location)) {
                                $adTargetingModel->addTargeting($adId, 'location', $location);
                            }
                        }
                    }
                    
                    // Handle device targeting
                    $devices = isset($_POST['devices']) ? $_POST['devices'] : [];
                    if (!empty($devices)) {
                        foreach ($devices as $device) {
                            if (!empty($device)) {
                                $adTargetingModel->addTargeting($adId, 'device', $device);
                            }
                        }
                    }
                    
                    // Handle day-of-week targeting
                    $days = isset($_POST['days']) ? $_POST['days'] : [];
                    if (!empty($days)) {
                        foreach ($days as $day) {
                            if (!empty($day)) {
                                $adTargetingModel->addTargeting($adId, 'day', $day);
                            }
                        }
                    }
                    
                    // Handle time range targeting
                    $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
                    $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';
                    
                    // Only add time targeting if both start and end times are provided
                    if (!empty($startTime) && !empty($endTime)) {
                        $adTargetingModel->addTargeting($adId, 'time_start', $startTime);
                        $adTargetingModel->addTargeting($adId, 'time_end', $endTime);
                    }
                    
                    // Handle browser targeting
                    $browsers = isset($_POST['browsers']) ? $_POST['browsers'] : [];
                    if (!empty($browsers)) {
                        foreach ($browsers as $browser) {
                            if (!empty($browser)) {
                                $adTargetingModel->addTargeting($adId, 'browser', $browser);
                            }
                        }
                    }
                    
                    // Handle OS targeting
                    $osystems = isset($_POST['os']) ? $_POST['os'] : [];
                    if (!empty($osystems)) {
                        foreach ($osystems as $os) {
                            if (!empty($os)) {
                                $adTargetingModel->addTargeting($adId, 'os', $os);
                            }
                        }
                    }
                    
                    $_SESSION['success'] = 'Advertisement created successfully. Now you can design it using the canvas tool.';
                    header('Location: /advertiser/canvas/' . $adId);
                    exit;
                } else {
                    throw new \Exception('Failed to create advertisement');
                }
            } catch (\Exception $e) {
                $this->logger->error('Error creating advertisement: ' . $e->getMessage());
                $_SESSION['error'] = 'Error creating advertisement. Please try again.';
                header('Location: /advertiser/create-ad');
                exit;
            }
        }

        // Get available ad positions for the form
        $stmt = $this->db->prepare("SELECT * FROM ad_positions WHERE status = 'active'");
        $stmt->execute();
        $positions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get common locations for targeting options
        $commonLocations = [
            'US' => 'United States',
            'CA' => 'Canada',
            'UK' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'JP' => 'Japan',
            'AU' => 'Australia',
            'BR' => 'Brazil',
            'IN' => 'India',
            'CN' => 'China'
        ];

        // Display the create ad form
        require_once __DIR__ . '/../../templates/advertiser/ads_create.php';
    }

    /**
     * Display the ad canvas tool for designing an ad
     * 
     * @param int $adId The ID of the advertisement to design
     */
    public function adCanvas($adId = null) {
        // Verify advertiser access
        if (!$this->authService->isAdvertiser()) {
            header('Location: /login');
            exit;
        }

        if (!$adId) {
            $_SESSION['error'] = 'Ad ID is required';
            header('Location: /advertiser/ads');
            exit;
        }

        // Verify the ad exists and belongs to this advertiser
        $advertiser = $this->authService->getCurrentUser();
        $advertisementModel = new \App\Models\Advertisement();
        $ad = $advertisementModel->find($adId);

        if (!$ad || $ad['advertiser_id'] != $advertiser['id']) {
            $_SESSION['error'] = 'Advertisement not found or access denied';
            header('Location: /advertiser/ads');
            exit;
        }

        // Get ad position details for canvas dimensions
        $stmt = $this->db->prepare("SELECT * FROM ad_positions WHERE id = ?");
        $stmt->execute([$ad['position_id']]);
        $position = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$position) {
            $_SESSION['error'] = 'Ad position not found';
            header('Location: /advertiser/ads');
            exit;
        }

        // If form is submitted (saving canvas data)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $html = filter_input(INPUT_POST, 'html_content', FILTER_UNSAFE_RAW);
            $canvasData = filter_input(INPUT_POST, 'canvas_data', FILTER_UNSAFE_RAW);
            
            // Update the ad content
            $content = json_encode([
                'html' => $html,
                'canvas_data' => $canvasData
            ]);
            
            $advertisementModel->update($adId, [
                'content' => $content,
                'status' => 'pending' // Reset to pending for review
            ]);
            
            $_SESSION['success'] = 'Advertisement design saved successfully';
            header('Location: /advertiser/ads');
            exit;
        }

        // Load any existing content for the ad
        $content = json_decode($ad['content'], true) ?: ['html' => '', 'canvas_data' => ''];

        // Pass data to the canvas view
        $adData = [
            'id' => $ad['id'],
            'title' => $ad['title'],
            'width' => $position['width'],
            'height' => $position['height'],
            'html' => $content['html'],
            'canvas_data' => $content['canvas_data']
        ];

        require_once __DIR__ . '/../../templates/advertiser/canvas.php';
    }
}
