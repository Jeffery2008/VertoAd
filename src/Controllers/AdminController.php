<?php
namespace Controllers;

use Services\AdService;
use Services\AuthService;
use Utils\Logger;
use Models\AdPosition;
use Models\Advertisement;

class AdminController extends BaseController {
    private $adService;
    private $positionModel;
    private $advertModel;
    private $logger;
    
    public function __construct() {
        parent::__construct();
        $this->adService = new AdService();
        $this->positionModel = new AdPosition();
        $this->advertModel = new Advertisement();
        $this->logger = new Logger();
        
        // Ensure admin authentication
        $this->requireAdmin();
    }

    // [Previous methods remain exactly the same until validateAdvertData]

    /**
     * Validate advertisement data
     */
    private function validateAdvertData($data) {
        $required = ['title', 'type', 'position_id', 'start_date', 'end_date', 'budget'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("Field {$field} is required");
            }
        }
        
        if (!$this->positionModel->exists($data['position_id'])) {
            throw new \Exception('Invalid position selected');
        }
        
        if (!is_numeric($data['budget']) || $data['budget'] <= 0) {
            throw new \Exception('Budget must be a positive number');
        }
        
        $startDate = strtotime($data['start_date']);
        $endDate = strtotime($data['end_date']);
        
        if ($startDate === false || $endDate === false) {
            throw new \Exception('Invalid date format');
        }
        
        if ($startDate >= $endDate) {
            throw new \Exception('End date must be after start date');
        }
        
        return [
            'title' => strip_tags($data['title']),
            'type' => $data['type'],
            'position_id' => (int)$data['position_id'],
            'start_date' => date('Y-m-d', $startDate),
            'end_date' => date('Y-m-d', $endDate),
            'budget' => (float)$data['budget'],
            'targeting' => json_encode($data['targeting'] ?? []),
            'status' => $data['status'] ?? 'pending',
            'content' => $data['content'] ?? '',
            'url' => filter_var($data['url'] ?? '', FILTER_SANITIZE_URL)
        ];
    }

    /**
     * Handle media file upload for advertisements
     */
    private function handleMediaUpload($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new \Exception('No file uploaded');
        }

        // Validate file type
        $allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 
            'video/mp4', 'video/webm'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new \Exception('Invalid file type');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $uploadPath = STORAGE_PATH . '/ads/' . $filename;

        // Create directory if it doesn't exist
        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new \Exception('Failed to move uploaded file');
        }

        return $filename;
    }
}
