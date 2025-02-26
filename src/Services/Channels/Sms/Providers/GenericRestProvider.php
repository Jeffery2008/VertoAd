<?php
namespace VertoAD\Core\Services\Channels\Sms\Providers;

use VertoAD\Core\Services\Channels\Sms\BaseSmsProvider;

class GenericRestProvider extends BaseSmsProvider {
    /**
     * Get provider name
     * @return string
     */
    public function getName(): string {
        return 'generic_rest';
    }
    
    /**
     * Get required configuration fields
     * @return array
     */
    public function getRequiredConfig(): array {
        return [
            'api_url',
            'api_key',
            'api_secret'
        ];
    }
    
    /**
     * Send SMS message
     * @param string $phoneNumber
     * @param string $message
     * @param array $options
     * @return array
     */
    public function send(string $phoneNumber, string $message, array $options = []): array {
        if (!$this->validateConfig()) {
            return [
                'success' => false,
                'message' => $this->getLastError(),
                'data' => null
            ];
        }
        
        // Prepare request data
        $data = array_merge($options, [
            'phone' => $phoneNumber,
            'message' => $message,
            'api_key' => $this->getConfig('api_key'),
            'timestamp' => time(),
        ]);
        
        // Add signature if required
        if ($this->getConfig('use_signature', true)) {
            $data['sign'] = $this->generateSignature($data);
        }
        
        // Make API request
        $response = $this->makeRequest(
            $this->getConfig('api_url'),
            $data,
            'POST',
            $this->getRequestHeaders()
        );
        
        if (!$response['success']) {
            return $response;
        }
        
        // Parse API response
        $result = json_decode($response['data'], true);
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response',
                'data' => null
            ];
        }
        
        return [
            'success' => $result['code'] === 0 || $result['code'] === '0',
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null
        ];
    }
    
    /**
     * Generate request signature
     * @param array $data
     * @return string
     */
    protected function generateSignature(array $data): string {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ($key !== 'sign' && !is_array($value)) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = rtrim($str, '&');
        return md5($str . $this->getConfig('api_secret'));
    }
    
    /**
     * Get request headers
     * @return array
     */
    protected function getRequestHeaders(): array {
        return [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
            'X-API-Key: ' . $this->getConfig('api_key')
        ];
    }
} 