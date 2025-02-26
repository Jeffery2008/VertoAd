<?php
namespace VertoAD\Core\Services\Channels\Sms;

interface SmsProviderInterface {
    /**
     * Send SMS message
     * @param string $phoneNumber Recipient phone number
     * @param string $message Message content
     * @param array $options Additional options for the provider
     * @return array ['success' => bool, 'message' => string, 'data' => mixed]
     */
    public function send(string $phoneNumber, string $message, array $options = []): array;
    
    /**
     * Get provider name
     * @return string
     */
    public function getName(): string;
    
    /**
     * Validate provider configuration
     * @return bool
     */
    public function validateConfig(): bool;
    
    /**
     * Get required configuration fields
     * @return array
     */
    public function getRequiredConfig(): array;
} 