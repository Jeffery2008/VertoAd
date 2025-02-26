<?php

namespace VertoAD\Core\Controllers;

use VertoAD\Core\Services\KeyGenerationService;
use VertoAD\Core\Services\KeyRedemptionService;
use VertoAD\Core\Utils\Logger;
use VertoAD\Core\Utils\Validator;

class KeyManagementController {
    private $keyGenService;
    private $keyRedemptionService;
    private $logger;
    private $validator;

    public function __construct() {
        $this->keyGenService = new KeyGenerationService();
        $this->keyRedemptionService = new KeyRedemptionService();
        $this->logger = new Logger('KeyManagementController');
        $this->validator = new Validator();
    }

    /**
     * Display key management dashboard
     */
    public function index() {
        try {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = 10;
            $offset = ($page - 1) * $limit;

            // Get batches for display
            $batches = $this->keyGenService->getBatchList($limit, $offset);
            $totalBatches = $this->keyGenService->countBatches();
            $totalPages = ceil($totalBatches / $limit);

            // Get statistics
            $todayStats = $this->keyRedemptionService->getRedemptionStats([
                'start_date' => date('Y-m-d'),
                'end_date' => date('Y-m-d')
            ]);

            $monthStats = $this->keyRedemptionService->getRedemptionStats([
                'start_date' => date('Y-m-01'),
                'end_date' => date('Y-m-t')
            ]);

            $allTimeStats = $this->keyRedemptionService->getRedemptionStats();

            require_once __DIR__ . '/../../templates/admin/keys.php';
        } catch (\Exception $e) {
            $this->logger->error('Error in key management index', ['error' => $e->getMessage()]);
            $_SESSION['error'] = 'Failed to load key management dashboard';
            header('Location: /admin/dashboard');
            exit;
        }
    }

    /**
     * Generate new batch of keys
     */
    public function generateKeys() {
        try {
            // Validate input
            $this->validator->validate($_POST, [
                'batch_name' => 'required|string|max:100',
                'amount' => 'required|numeric|min:0.01',
                'quantity' => 'required|integer|min:1|max:1000',
                'notes' => 'string|max:500'
            ]);

            $batchName = trim($_POST['batch_name']);
            $amount = floatval($_POST['amount']);
            $quantity = intval($_POST['quantity']);
            $notes = trim($_POST['notes'] ?? '');

            // Generate keys
            $result = $this->keyGenService->generateKeyBatch(
                $quantity,
                $amount,
                $batchName,
                $_SESSION['user_id'],
                $notes
            );

            // Generate Excel file
            $filename = $this->generateExcelFile($result);

            // Set download headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            readfile($filename);
            unlink($filename); // Delete temporary file
            exit;

        } catch (\ValidationException $e) {
            $_SESSION['error'] = 'Invalid input: ' . implode(', ', $e->getErrors());
            header('Location: /admin/keys');
            exit;
        } catch (\Exception $e) {
            $this->logger->error('Error generating keys', ['error' => $e->getMessage()]);
            $_SESSION['error'] = 'Failed to generate keys';
            header('Location: /admin/keys');
            exit;
        }
    }

    /**
     * View specific key batch
     */
    public function viewBatch($batchId) {
        try {
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = 50;
            $offset = ($page - 1) * $limit;

            // Get batch details
            $batch = $this->keyGenService->getBatchSummary($batchId);
            if (!$batch) {
                throw new \Exception('Batch not found');
            }

            // Get keys for this batch
            $keys = $this->keyGenService->getBatchKeys($batchId, $limit, $offset);
            $totalKeys = $batch['total_keys'];
            $totalPages = ceil($totalKeys / $limit);

            require_once __DIR__ . '/../../templates/admin/key_batch.php';
        } catch (\Exception $e) {
            $this->logger->error('Error viewing batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);
            $_SESSION['error'] = 'Failed to load batch details';
            header('Location: /admin/keys');
            exit;
        }
    }

    /**
     * Download keys for a batch
     */
    public function downloadBatch($batchId) {
        try {
            // Get batch details and keys
            $batch = $this->keyGenService->getBatchSummary($batchId);
            if (!$batch) {
                throw new \Exception('Batch not found');
            }

            $keys = $this->keyGenService->getBatchKeys($batchId);
            $filename = $this->generateExcelFile([
                'batch_id' => $batchId,
                'keys' => array_column($keys, 'key_value')
            ]);

            // Set download headers
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            readfile($filename);
            unlink($filename);
            exit;

        } catch (\Exception $e) {
            $this->logger->error('Error downloading batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);
            $_SESSION['error'] = 'Failed to download keys';
            header('Location: /admin/keys/batch/' . $batchId);
            exit;
        }
    }

    /**
     * Revoke a single key
     */
    public function revokeKey($keyId) {
        try {
            $result = $this->keyGenService->revokeKey($keyId, $_SESSION['user_id']);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Error revoking key', [
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Revoke all keys in a batch
     */
    public function revokeBatch($batchId) {
        try {
            $result = $this->keyGenService->revokeBatch($batchId, $_SESSION['user_id']);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error('Error revoking batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate Excel file with keys
     */
    private function generateExcelFile($data) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $sheet->setCellValue('A1', 'Product Key');
        $sheet->setCellValue('B1', 'Amount');
        $sheet->setCellValue('C1', 'Status');

        // Add keys
        $row = 2;
        foreach ($data['keys'] as $key) {
            $sheet->setCellValue('A' . $row, $key);
            $sheet->setCellValue('B' . $row, $data['amount']);
            $sheet->setCellValue('C' . $row, 'Active');
            $row++;
        }

        // Style the spreadsheet
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(35);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);

        // Create temp file
        $filename = tempnam(sys_get_temp_dir(), 'keys_') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($filename);

        return $filename;
    }
}
