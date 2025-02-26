<?php
namespace VertoAD\Core\Services\Channels;

class SmsChannel extends BaseNotificationChannel {
    /**
     * Get channel type
     * @return string
     */
    public function getType(): string {
        return 'sms';
    }
    
    /**
     * Check if channel is available
     * @return bool
     */
    public function isAvailable(): bool {
        $required = ['api_url', 'api_key', 'api_secret'];
        return $this->validateConfig($required);
    }
    
    /**
     * Send notification
     * @param array $notification
     * @return bool
     */
    public function send(array $notification): bool {
        if (!$this->validate($notification)) {
            return false;
        }
        
        try {
            // Get recipient phone number from user ID
            $phoneNumber = $this->getRecipientPhone($notification['user_id']);
            if (!$phoneNumber) {
                $this->logError("Recipient phone number not found", ['user_id' => $notification['user_id']]);
                return false;
            }
            
            // Prepare SMS content
            $content = $this->prepareSmsContent($notification['content']);
            
            // Send SMS using configured API
            $response = $this->sendSmsRequest([
                'phone' => $phoneNumber,
                'message' => $content,
                'template_id' => $notification['template_id']
            ]);
            
            if (!$response['success']) {
                $this->logError("SMS API error: " . ($response['message'] ?? 'Unknown error'), [
                    'user_id' => $notification['user_id'],
                    'template_id' => $notification['template_id']
                ]);
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->logError("Failed to send SMS: " . $e->getMessage(), [
                'user_id' => $notification['user_id'],
                'template_id' => $notification['template_id']
            ]);
            return false;
        }
    }
    
    /**
     * Get recipient phone number by user ID
     * @param int $userId
     * @return string|null
     */
    private function getRecipientPhone(int $userId): ?string {
        // TODO: Implement user phone lookup from database
        // This should be implemented based on your user system
        return null;
    }
    
    /**
     * Prepare SMS content
     * @param string $content
     * @return string
     */
    private function prepareSmsContent(string $content): string {
        // Remove HTML tags
        $content = strip_tags($content);
        
        // Convert special characters
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Limit content length if needed
        $maxLength = $this->getConfig('max_length', 500);
        if (mb_strlen($content) > $maxLength) {
            $content = mb_substr($content, 0, $maxLength - 3) . '...';
        }
        
        return $content;
    }
    
    /**
     * Send SMS request to API
     * @param array $data
     * @return array
     */
    private function sendSmsRequest(array $data): array {
        $apiUrl = $this->getConfig('api_url');
        $apiKey = $this->getConfig('api_key');
        $apiSecret = $this->getConfig('api_secret');
        
        // Prepare request data
        $requestData = array_merge($data, [
            'api_key' => $apiKey,
            'timestamp' => time(),
            'sign' => $this->generateSign($data, $apiSecret)
        ]);
        
        // Initialize cURL
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($requestData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        // Send request
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Handle response
        if ($error) {
            return [
                'success' => false,
                'message' => "cURL Error: $error"
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => "HTTP Error: $httpCode"
            ];
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            return [
                'success' => false,
                'message' => "Invalid JSON response"
            ];
        }
        
        return [
            'success' => $result['code'] === 0,
            'message' => $result['message'] ?? null,
            'data' => $result['data'] ?? null
        ];
    }
    
    /**
     * Generate API request signature
     * @param array $data
     * @param string $secret
     * @return string
     */
    private function generateSign(array $data, string $secret): string {
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            if ($key !== 'sign' && !is_array($value)) {
                $str .= $key . '=' . $value . '&';
            }
        }
        $str = rtrim($str, '&');
        return md5($str . $secret);
    }
}