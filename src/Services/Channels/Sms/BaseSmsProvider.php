<?php
namespace VertoAD\Core\Services\Channels\Sms;

use VertoAD\Core\Utils\Logger;

abstract class BaseSmsProvider implements SmsProviderInterface {
    protected $config;
    protected $logger;
    protected $lastError;
    
    /**
     * Constructor
     * @param array $config Provider configuration
     */
    public function __construct(array $config = []) {
        $this->config = $config;
        $this->logger = new Logger('sms_provider_' . $this->getName());
    }
    
    /**
     * Validate provider configuration
     * @return bool
     */
    public function validateConfig(): bool {
        $required = $this->getRequiredConfig();
        foreach ($required as $field) {
            if (!isset($this->config[$field]) || empty($this->config[$field])) {
                $this->lastError = "Missing required config: {$field}";
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get configuration value
     * @param string $key Configuration key
     * @param mixed $default Default value
     * @return mixed
     */
    protected function getConfig(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Log error message
     * @param string $message Error message
     * @param array $context Additional context
     */
    protected function logError(string $message, array $context = []): void {
        $this->lastError = $message;
        $this->logger->error($message, $context);
    }
    
    /**
     * Get last error message
     * @return string|null
     */
    public function getLastError(): ?string {
        return $this->lastError;
    }
    
    /**
     * Make HTTP request
     * @param string $url Request URL
     * @param array $data Request data
     * @param string $method HTTP method
     * @param array $headers HTTP headers
     * @return array ['success' => bool, 'data' => mixed, 'message' => string]
     */
    protected function makeRequest(string $url, array $data = [], string $method = 'POST', array $headers = []): array {
        try {
            $ch = curl_init($url);
            
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ];
            
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
            }
            
            if (!empty($headers)) {
                $options[CURLOPT_HTTPHEADER] = $headers;
            }
            
            curl_setopt_array($ch, $options);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'message' => "cURL Error: {$error}",
                    'data' => null
                ];
            }
            
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'message' => "HTTP Error: {$httpCode}",
                    'data' => null
                ];
            }
            
            return [
                'success' => true,
                'data' => $response,
                'message' => null
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Request failed: " . $e->getMessage(),
                'data' => null
            ];
        }
    }
} 