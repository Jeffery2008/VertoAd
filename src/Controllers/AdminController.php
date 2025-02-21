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
}
