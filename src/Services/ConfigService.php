<?php

require_once __DIR__ . '/../Utils/Logger.php';
require_once __DIR__ . '/../Utils/Validator.php';
require_once __DIR__ . '/../Models/BaseModel.php';

class ConfigService {
    private $db;
    private $logger;
    private $validator;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->logger = new Logger();
        $this->validator = new Validator();
    }

    /**
     * Get ad types configuration
     */
    public function getAdTypes() {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                name,
                description,
                allowed_formats,
                size_restrictions,
                file_size_limit,
                created_at,
                updated_at
            FROM ad_types
            WHERE is_active = 1
            ORDER BY display_order ASC
        ");

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update ad type configuration
     */
    public function updateAdType($id, $data) {
        try {
            $this->db->beginTransaction();

            // Validate fields
            if (isset($data['name'])) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM ad_types
                    WHERE name = ? AND id != ?
                ");
                $stmt->execute([$data['name'], $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Ad type name already exists');
                }
            }

            // Update fields
            $fields = [];
            $params = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'description', 'allowed_formats', 'size_restrictions', 'file_size_limit'])) {
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
            }
            
            if (empty($fields)) {
                throw new Exception('No valid fields to update');
            }

            $params[] = $id;
            $sql = "UPDATE ad_types SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get system configuration
     */
    public function getSystemConfig() {
        $stmt = $this->db->prepare("
            SELECT 
                config_key,
                config_value,
                data_type,
                is_encrypted
            FROM system_config
            WHERE is_active = 1
        ");

        $stmt->execute();
        $config = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = $row['config_value'];
            
            if ($row['is_encrypted']) {
                $value = $this->decrypt($value);
            }
            
            switch ($row['data_type']) {
                case 'integer':
                    $value = intval($value);
                    break;
                case 'float':
                    $value = floatval($value);
                    break;
                case 'boolean':
                    $value = boolval($value);
                    break;
                case 'json':
                    $value = json_decode($value, true);
                    break;
            }
            
            $config[$row['config_key']] = $value;
        }

        return $config;
    }

    /**
     * Update system configuration
     */
    public function updateSystemConfig($key, $value) {
        try {
            $stmt = $this->db->prepare("
                SELECT data_type, is_encrypted
                FROM system_config
                WHERE config_key = ? AND is_active = 1
            ");
            $stmt->execute([$key]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                throw new Exception('Configuration key not found');
            }

            // Validate and format value based on data type
            switch ($config['data_type']) {
                case 'json':
                    if (!$this->validator->isValidJson($value)) {
                        throw new Exception('Invalid JSON format');
                    }
                    break;
                    
                case 'integer':
                    if (!is_numeric($value)) {
                        throw new Exception('Value must be numeric');
                    }
                    $value = intval($value);
                    break;
                    
                case 'float':
                    if (!is_numeric($value)) {
                        throw new Exception('Value must be numeric');
                    }
                    $value = floatval($value);
                    break;
                    
                case 'boolean':
                    $value = boolval($value);
                    break;
            }

            // Encrypt if needed
            if ($config['is_encrypted']) {
                $value = $this->encrypt($value);
            }

            // Update configuration
            $stmt = $this->db->prepare("
                UPDATE system_config
                SET config_value = ?, updated_at = NOW()
                WHERE config_key = ? AND is_active = 1
            ");
            
            return $stmt->execute([$value, $key]);

        } catch (Exception $e) {
            $this->logger->error('Failed to update system config', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Helper: Encrypt sensitive data
     */
    private function encrypt($value) {
        // TODO: Replace with proper encryption using a secure algorithm
        return openssl_encrypt(
            $value,
            'AES-256-CBC',
            getenv('ENCRYPTION_KEY'),
            0,
            getenv('ENCRYPTION_IV')
        );
    }

    /**
     * Helper: Decrypt sensitive data
     */
    private function decrypt($value) {
        // TODO: Replace with proper decryption using a secure algorithm
        return openssl_decrypt(
            $value,
            'AES-256-CBC',
            getenv('ENCRYPTION_KEY'),
            0,
            getenv('ENCRYPTION_IV')
        );
    }

    /**
     * Helper: Validate deployment rules
     */
    private function validateDeploymentRules($rules) {
        if (!is_array($rules)) {
            return false;
        }
        
        foreach ($rules as $rule) {
            if (!isset($rule['condition']) || !isset($rule['value'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Helper: Validate notification channels
     */
    private function validateNotificationChannels($channels) {
        if (!is_array($channels)) {
            return false;
        }
        
        $validChannels = ['email', 'sms', 'push', 'dashboard'];
        
        foreach ($channels as $channel) {
            if (!in_array($channel, $validChannels)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Helper: Validate notification conditions
     */
    private function validateNotificationConditions($conditions) {
        if (!is_array($conditions)) {
            return false;
        }
        
        foreach ($conditions as $condition) {
            if (!isset($condition['type']) || !isset($condition['value'])) {
                return false;
            }
        }
        
        return true;
    }
}
