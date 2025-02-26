<?php
namespace VertoAD\Core\Services\Channels;

use VertoAD\Core\Services\Channels\Sms\SmsProviderInterface;
use VertoAD\Core\Services\Channels\Sms\Providers\GenericRestProvider;

class SmsChannel extends BaseNotificationChannel {
    private $provider;
    
    /**
     * Constructor
     * @param array $config
     */
    public function __construct(array $config = []) {
        parent::__construct($config);
        $this->initializeProvider();
    }
    
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
        return $this->provider && $this->provider->validateConfig();
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
            // Get recipient phone number
            $phoneNumber = $this->getRecipientPhone($notification['user_id']);
            if (!$phoneNumber) {
                $this->logError("Recipient phone number not found", ['user_id' => $notification['user_id']]);
                return false;
            }
            
            // Prepare SMS content
            $content = $this->prepareSmsContent($notification['content']);
            
            // Send SMS using provider
            $response = $this->provider->send($phoneNumber, $content, [
                'template_id' => $notification['template_id'],
                'title' => $notification['title']
            ]);
            
            if (!$response['success']) {
                $this->logError("SMS provider error: " . ($response['message'] ?? 'Unknown error'), [
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
     * Initialize SMS provider
     */
    private function initializeProvider(): void {
        $providerClass = $this->getConfig('provider_class', GenericRestProvider::class);
        
        try {
            if (!class_exists($providerClass)) {
                throw new \Exception("SMS provider class not found: {$providerClass}");
            }
            
            $provider = new $providerClass($this->config);
            
            if (!($provider instanceof SmsProviderInterface)) {
                throw new \Exception("Invalid SMS provider class: {$providerClass}");
            }
            
            $this->provider = $provider;
            
        } catch (\Exception $e) {
            $this->logError("Failed to initialize SMS provider: " . $e->getMessage());
            // Fallback to default provider
            $this->provider = new GenericRestProvider($this->config);
        }
    }
    
    /**
     * Get recipient phone number
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
}