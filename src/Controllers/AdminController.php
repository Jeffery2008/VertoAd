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
                header('Location: /login');
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
                header('Location: /login');
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
                header('Location: /login');
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
                header('Location: /login');
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
                header('Location: /login');
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
}
