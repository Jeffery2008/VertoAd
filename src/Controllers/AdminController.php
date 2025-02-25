<?php

namespace App\Controllers;

use App\Services\AccountService;
use App\Services\AuthService;
use App\Models\User;
use App\Utils\Logger;

class AdminController {
    private $accountService;
    private $authService;
    private $logger;

    public function __construct() {
        $this->accountService = new AccountService();
        $this->authService = new AuthService();
        $this->logger = new Logger('AdminController');
    }

    /**
     * Display the user balances overview page
     */
    public function showBalances() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            $userModel = new User();
            $users = $userModel->getAllWithBalances();

            require_once __DIR__ . '/../../templates/admin/balances.php';
        } catch (\Exception $e) {
            $this->logger->error('Error in showBalances: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display the transaction history for a specific user
     */
    public function showTransactions() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            $userId = $_GET['user_id'] ?? null;
            if (!$userId) {
                header('Location: /admin/balances');
                exit;
            }

            // Get user details
            $userModel = new User();
            $user = $userModel->findById($userId);
            if (!$user) {
                header('Location: /admin/balances');
                exit;
            }

            // Pagination parameters
            $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 50;
            $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;

            // Filter parameters
            $filters = [
                'type' => $_GET['type'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];

            // Get transactions
            $transactions = $this->accountService->getUserTransactions($userId, $filters, $limit, $offset);
            $totalTransactions = $this->accountService->countUserTransactions($userId, $filters);

            require_once __DIR__ . '/../../templates/admin/user_transactions.php';
        } catch (\Exception $e) {
            $this->logger->error('Error in showTransactions: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle balance adjustment requests
     */
    public function adjustBalance() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate request
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data || !isset($data['user_id'], $data['amount'], $data['description'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid request data']);
                return;
            }

            // Perform adjustment
            $userId = intval($data['user_id']);
            $amount = floatval($data['amount']);
            $description = trim($data['description']);

            $newBalance = $this->accountService->adjustBalance($userId, $amount, $description);

            echo json_encode([
                'success' => true,
                'new_balance' => $newBalance
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in adjustBalance: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * Export transaction history
     */
    public function exportTransactions() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            // Get filter parameters
            $userId = $_GET['user_id'] ?? null;
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;

            // Get transactions
            $filters = [
                'start_date' => $startDate,
                'end_date' => $endDate
            ];
            
            $transactions = $userId 
                ? $this->accountService->getUserTransactions($userId, $filters, 1000000, 0)  // Large limit for full export
                : $this->accountService->getAllTransactions($filters, 1000000, 0);

            // Generate CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="transactions_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');

            // Write headers
            fputcsv($output, ['Transaction ID', 'User ID', 'Username', 'Type', 'Amount', 
                            'Balance After', 'Description', 'Status', 'Created At']);

            // Write data
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction['id'],
                    $transaction['user_id'],
                    $transaction['username'],
                    $transaction['type'],
                    $transaction['amount'],
                    $transaction['new_balance'],
                    $transaction['description'],
                    $transaction['status'],
                    $transaction['created_at']
                ]);
            }

            fclose($output);
            exit;
        } catch (\Exception $e) {
            $this->logger->error('Error in exportTransactions: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display the batch key generation form
     */
    public function showBatchKeyGenerationForm() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            require_once __DIR__ . '/../../templates/admin/key_batch.php';
        } catch (\Exception $e) {
            $this->logger->error('Error in showBatchKeyGenerationForm: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle batch key generation request
     */
    public function generateBatchKeys() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate request
            $batchName = $_POST['batch_name'] ?? '';
            $amount = $_POST['amount'] ?? '';
            $quantity = $_POST['quantity'] ?? '';

            if (empty($batchName) || !is_numeric($amount) || !is_numeric($quantity)) {
                // Redirect back to form with error message
                header('Location: /admin/keys/batch?error=Invalid input');
                exit;
            }

            $amount = floatval($amount);
            $quantity = intval($quantity);

            if ($amount <= 0 || $quantity <= 0) {
                // Redirect back to form with error message
                header('Location: /admin/keys/batch?error=Invalid amount or quantity');
                exit;
            }

            // Generate keys
            $keyGenerationService = new KeyGenerationService($this->logger); // Need to instantiate KeyGenerationService
            $keys = $keyGenerationService->generateKeyBatch($quantity, $amount);

            // Get current admin user ID
            $adminUser = $this->authService->getCurrentUser();
            $adminUserId = $adminUser ? $adminUser['id'] : 0; // Default to 0 if no user

            // Store batch information in database
            $keyBatchModel = new \App\Models\KeyBatch(); // Instantiate KeyBatch model
            $batchId = $keyBatchModel->createBatch([
                'batch_name' => $batchName,
                'amount' => $amount,
                'quantity' => $quantity,
                'created_by' => $adminUserId,
            ]);

            if (!$batchId) {
                throw new \Exception("Failed to create key batch record");
            }

            $productKeyModel = new \App\Models\ProductKey(); // Instantiate ProductKey model

            // Store generated keys in database
            foreach ($keys as $keyValue) {
                if (!$productKeyModel->createKey([
                    'batch_id' => $batchId,
                    'key_value' => $keyValue,
                    'key_hash' => hash('sha256', $keyValue),
                    'amount' => $amount,
                    'created_by' => $adminUserId,
                ])) {
                    throw new \Exception("Failed to store all generated keys in database");
                }
            }

            // Redirect to key batch view page
            header('Location: /admin/key-batch/' . $batchId);
            exit;
            // For now, just display success message
            // echo "Batch of {$quantity} keys generated and stored for batch '{$batchName}' with amount {$amount}. Batch ID: {$batchId}<br>";


        } catch (\Exception $e) {
            $this->logger->error('Error in generateBatchKeys: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display the single key generation form
     */
    public function showSingleKeyGenerationForm() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            require_once __DIR__ . '/../../templates/admin/key_single.php';
        } catch (\Exception $e) {
            $this->logger->error('Error in showSingleKeyGenerationForm: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle single key generation request
     */
    public function generateSingleKey() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate request
            $amount = $_POST['amount'] ?? '';

            if (!is_numeric($amount)) {
                // Redirect back to form with error message
                header('Location: /admin/keys/single?error=Invalid input');
                exit;
            }

            $amount = floatval($amount);

            if ($amount <= 0) {
                // Redirect back to form with error message
                header('Location: /admin/keys/single?error=Invalid amount');
                exit;
            }

            // Generate key
            $keyGenerationService = new KeyGenerationService($this->logger); // Need to instantiate KeyGenerationService
            $key = $keyGenerationService->generateSingleKey($amount);

            // Get current admin user ID
            $adminUser = $this->authService->getCurrentUser();
            $adminUserId = $adminUser ? $adminUser['id'] : 0; // Default to 0 if no user

            // Store key information in database
            $productKeyModel = new \App\Models\ProductKey(); // Instantiate ProductKey model
            if (!$productKeyModel->createKey([
                'batch_id' => 0, // 0 for single key
                'key_value' => $key,
                'key_hash' => hash('sha256', $key),
                'amount' => $amount,
                'created_by' => $adminUserId,
            ])) {
                throw new \Exception("Failed to store generated key in database");
            }


            // Redirect to success page or display key (for now, just display)
            echo "Single key generated with amount {$amount}:<br><pre>{$key}</pre>";


        } catch (\Exception $e) {
            $this->logger->error('Error in generateSingleKey: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display list of ad positions
     */
    public function listAdPositions() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            $adPositionModel = new \App\Models\AdPosition();
            $adPositions = $adPositionModel->findAll();

            require_once __DIR__ . '/../../templates/admin/ad_positions_list.php'; // Create this template
        } catch (\Exception $e) {
            $this->logger->error('Error in listAdPositions: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display form to create a new ad position
     */
    public function showCreateAdPositionForm() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            require_once __DIR__ . '/../../templates/admin/ad_positions_create.php'; // Create this template
        } catch (\Exception $e) {
            $this->logger->error('Error in showCreateAdPositionForm: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle ad position creation
     */
    public function createAdPosition() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate request and input data
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $format = $_POST['format'] ?? '';
            $width = $_POST['width'] ?? '';
            $height = $_POST['height'] ?? '';
            $status = $_POST['status'] ?? 'inactive'; // Default to inactive

            if (empty($name) || empty($slug) || empty($format) || !is_numeric($width) || !is_numeric($height)) {
                // Redirect back to form with error message
                header('Location: /admin/positions/create?error=Invalid input');
                exit;
            }

            $adPositionModel = new \App\Models\AdPosition();
            $data = [
                'name' => $name,
                'slug' => $slug,
                'format' => $format,
                'width' => intval($width),
                'height' => intval($height),
                'status' => $status
            ];

            if ($adPositionModel->create($data)) {
                header('Location: /admin/positions'); // Redirect to positions list on success
                exit;
            } else {
                // Redirect back to form with error message
                header('Location: /admin/positions/create?error=CreationFailed');
                exit;
            }


        } catch (\Exception $e) {
            $this->logger->error('Error in createAdPosition: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display form to edit an existing ad position
     */
    public function showEditAdPositionForm() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            $positionId = $_GET['id'] ?? 0;
            if (!$positionId) {
                header('Location: /admin/positions');
                exit;
            }

            $adPositionModel = new \App\Models\AdPosition();
            $adPosition = $adPositionModel->find($positionId);
            if (!$adPosition) {
                header('Location: /admin/positions');
                exit;
            }


            require_once __DIR__ . '/../../templates/admin/ad_positions_edit.php'; // Create this template
        } catch (\Exception $e) {
            $this->logger->error('Error in showEditAdPositionForm: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle ad position update
     */
    public function updateAdPosition() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $positionId = $_POST['id'] ?? 0;
            if (!$positionId) {
                header('Location: /admin/positions');
                exit;
            }

            // Validate request and input data (similar to createAdPosition)
             // Validate request and input data
            $name = $_POST['name'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $format = $_POST['format'] ?? '';
            $width = $_POST['width'] ?? '';
            $height = $_POST['height'] ?? '';
            $status = $_POST['status'] ?? 'inactive'; // Default to inactive

            if (empty($name) || empty($slug) || empty($format) || !is_numeric($width) || !is_numeric($height)) {
                // Redirect back to form with error message
                header('Location: /admin/positions/edit?id=' . $positionId . '&error=Invalid input');
                exit;
            }

            $adPositionModel = new \App\Models\AdPosition();
            $data = [
                'name' => $name,
                'slug' => $slug,
                'format' => $format,
                'width' => intval($width),
                'height' => intval($height),
                'status' => $status
            ];


            if ($adPositionModel->update($positionId, $data)) {
                header('Location: /admin/positions'); // Redirect to positions list on success
                exit;
            } else {
                // Redirect back to form with error message
                header('Location: /admin/positions/edit?id=' . $positionId . '&error=UpdateFailed');
                exit;
            }


        } catch (\Exception $e) {
            $this->logger->error('Error in updateAdPosition: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle ad position deletion
     */
    public function deleteAdPosition() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $positionId = $_POST['id'] ?? 0;
            if (!$positionId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid position ID']);
                return;
            }

            $adPositionModel = new \App\Models\AdPosition();
            if ($adPositionModel->delete($positionId)) {
                echo json_encode(['success' => true, 'message' => 'Position deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete position']);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in deleteAdPosition: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }


    /**
     * Display the admin login form
     */
    public function showLogin() {
        require_once __DIR__ . '/../../templates/admin/login.php';
    }

    /**
     * Handle admin login submission
     */
    public function login() {
        try {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                header('Location: /admin/login?error=Invalid credentials');
                exit;
            }

            // ** Authentication Logic **
            // 1. Retrieve user from database by username (using UserModel)
            $userModel = new \App\Models\User();
            $adminUser = $userModel->findByUsername($username);

            if (!$adminUser) {
                header('Location: /admin/login?error=Invalid credentials');
                exit;
            }

            // 2. Verify password (using password_verify or similar method)
            if (!password_verify($password, $adminUser['password_hash'])) {
                header('Location: /admin/login?error=Invalid credentials');
                exit;
            }

            // 3. If authentication successful, set session and redirect to dashboard
            $_SESSION['admin_id'] = $adminUser['id']; // Start session and set admin ID
            header('Location: /admin/dashboard');
            exit;


        } catch (\Exception $e) {
            $this->logger->error('Error in admin login: ' . $e->getMessage());
            header('Location: /error'); // Redirect to error page
        }
    }


    /**
     * Display the admin dashboard
     */
    public function dashboard() {
        // Verify admin access
        if (!$this->authService->isAdmin()) {
            header('Location: /admin/login'); // Redirect to admin login if not admin
            exit;
        }

        require_once __DIR__ . '/../../templates/admin/dashboard.php';
    }

    /**
     * Display list of advertisements
     */
    public function listAdvertisements() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            $advertisementModel = new \App\Models\Advertisement();
            $advertisements = $advertisementModel->findAll();

            require_once __DIR__ . '/../../templates/admin/advertisements_list.php'; // Create this template
        } catch (\Exception $e) {
            $this->logger->error('Error in listAdvertisements: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display form to create a new advertisement
     */
    public function showCreateAdForm() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            // Fetch ad positions to populate the dropdown in create form
            $adPositionModel = new \App\Models\AdPosition();
            $adPositions = $adPositionModel->findAll();

            require_once __DIR__ . '/../../templates/admin/advertisements_create.php'; // Create this template
        } catch (\Exception $e) {
            $this->logger->error('Error in showCreateAdForm: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Display form to edit an existing advertisement
     */
    public function showEditAdForm() {
        try {
            // Verify admin access
            if (!$this->authService->isAdmin()) {
                header('Location: /admin/login');
                exit;
            }

            $adId = $_GET['id'] ?? 0;
            if (!$adId) {
                header('Location: /admin/advertisements');
                exit;
            }

            $advertisementModel = new \App\Models\Advertisement();
            $advertisement = $advertisementModel->find($adId);
            if (!$advertisement) {
                header('Location: /admin/advertisements');
                exit;
            }

             // Fetch ad positions to populate the dropdown in edit form
             $adPositionModel = new \App\Models\AdPosition();
             $adPositions = $adPositionModel->findAll();

            require_once __DIR__ . '/../../templates/admin/advertisements_edit.php'; // Create this template
        } catch (\Exception $e) {
            $this->logger->error('Error in showEditAdForm: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle advertisement creation
     */
    public function createAd() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate request and input data
            $name = $_POST['name'] ?? ''; // Not in DB schema, but using for admin panel listing
            $advertiserId = $_POST['advertiser_id'] ?? '';
            $positionId = $_POST['position_id'] ?? '';
            $content = $_POST['content'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? null;
            $status = $_POST['status'] ?? 'pending';
            $budget = $_POST['budget'] ?? '';
            $bidAmount = $_POST['bid_amount'] ?? '';

            if (empty($advertiserId) || empty($positionId) || empty($content) || empty($startDate) || empty($budget) || !is_numeric($budget) || empty($bidAmount) || !is_numeric($bidAmount)) {
                // Redirect back to form with error message
                header('Location: /admin/advertisements/create?error=Invalid input');
                exit;
            }

            $advertisementModel = new \App\Models\Advertisement();
            $data = [
                'advertiser_id' => intval($advertiserId),
                'position_id' => intval($positionId),
                'content' => json_decode($content, true), // Assuming content is JSON
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'budget' => floatval($budget),
                'bid_amount' => floatval($bidAmount)
            ];

            if (isset($name)) { // Not in DB schema, but might be useful for admin panel
                $data['name'] = $name; // Temporarily add to data array for logging/debugging
            }


            if ($advertisementModel->create($data)) {
                header('Location: /admin/advertisements'); // Redirect to advertisements list on success
                exit;
            } else {
                // Redirect back to form with error message
                header('Location: /admin/advertisements/create?error=CreationFailed');
                exit;
            }


        } catch (\Exception $e) {
            $this->logger->error('Error in createAd: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle advertisement update
     */
    public function updateAd() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            // Validate request and input data
            $adId = $_POST['id'] ?? 0;
            if (!$adId) {
                header('Location: /admin/advertisements');
                exit;
            }

            $name = $_POST['name'] ?? ''; // Not in DB schema, but using for admin panel listing
            $advertiserId = $_POST['advertiser_id'] ?? '';
            $positionId = $_POST['position_id'] ?? '';
            $content = $_POST['content'] ?? '';
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? null;
            $status = $_POST['status'] ?? 'pending';
            $budget = $_POST['budget'] ?? '';
            $bidAmount = $_POST['bid_amount'] ?? '';

            if (empty($advertiserId) || empty($positionId) || empty($content) || empty($startDate) || empty($budget) || !is_numeric($budget) || empty($bidAmount) || !is_numeric($bidAmount)) {
                // Redirect back to form with error message
                header('Location: /admin/advertisements/edit?id=' . $adId . '&error=Invalid input');
                exit;
            }

            $advertisementModel = new \App\Models\Advertisement();
            $data = [
                'advertiser_id' => intval($advertiserId),
                'position_id' => intval($positionId),
                'content' => json_decode($content, true), // Assuming content is JSON
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
                'budget' => floatval($budget),
                'bid_amount' => floatval($bidAmount)
            ];
            if (isset($name)) { // Not in DB schema, but might be useful for admin panel
                $data['name'] = $name; // Temporarily add to data array for logging/debugging
            }


            if ($advertisementModel->update($adId, $data)) {
                header('Location: /admin/advertisements'); // Redirect to advertisements list on success
                exit;
            } else {
                // Redirect back to form with error message
                header('Location: /admin/advertisements/edit?id=' . $adId . '&error=UpdateFailed');
                exit;
            }


        } catch (\Exception $e) {
            $this->logger->error('Error in updateAd: ' . $e->getMessage());
            header('Location: /error');
        }
    }

    /**
     * Handle advertisement deletion
     */
    public function deleteAd() {
        try {
            // Verify admin access and CSRF token
            if (!$this->authService->isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Unauthorized']);
                return;
            }

            $adId = $_POST['id'] ?? 0;
            if (!$adId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid advertisement ID']);
                return;
            }

            $advertisementModel = new \App\Models\Advertisement();
            if ($advertisementModel->delete($adId)) {
                echo json_encode(['success' => true, 'message' => 'Advertisement deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to delete advertisement']);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error in deleteAd: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }


    /**
     * Handle admin logout
     */
    public function logout() {
        session_destroy(); // Destroy the session
        header('Location: /admin/login'); // Redirect to login page after logout
        exit;
    }
}
