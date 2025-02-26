# SMS Provider System Documentation

## Overview

The SMS Provider System is a flexible and extensible framework for integrating different SMS service providers into the notification system. It provides a standardized way to implement and use various SMS services while maintaining consistent error handling and logging.

## Architecture

The system consists of four main components:

1. **SmsProviderInterface**: The contract that all SMS providers must implement
2. **BaseSmsProvider**: An abstract base class providing common functionality
3. **Concrete Provider Classes**: Specific implementations for different SMS services
4. **SmsChannel**: The main channel class that uses the provider system

## Core Components

### 1. SmsProviderInterface

The interface defines the required methods for any SMS provider:

```php
interface SmsProviderInterface {
    public function send(string $phoneNumber, string $message, array $options = []): array;
    public function getName(): string;
    public function validateConfig(): bool;
    public function getRequiredConfig(): array;
}
```

### 2. BaseSmsProvider

Provides common functionality for all providers:

- Configuration management
- Error handling
- HTTP request utilities
- Logging

### 3. SmsChannel

The main channel class that:

- Initializes and manages providers
- Handles message preparation
- Provides fallback mechanisms
- Manages error reporting

## Implementing a Custom Provider

### 1. Create Provider Class

Create a new class extending `BaseSmsProvider`:

```php
namespace VertoAD\Core\Services\Channels\Sms\Providers;

use VertoAD\Core\Services\Channels\Sms\BaseSmsProvider;

class CustomProvider extends BaseSmsProvider {
    public function getName(): string {
        return 'custom_provider';
    }
    
    public function getRequiredConfig(): array {
        return [
            'api_key',
            'api_secret',
            'endpoint_url'
        ];
    }
    
    public function send(string $phoneNumber, string $message, array $options = []): array {
        // Implement your sending logic here
        // Return format: ['success' => bool, 'message' => string, 'data' => mixed]
    }
}
```

### 2. Configure the Provider

In your notification channel configuration:

```php
$config = [
    'provider_class' => CustomProvider::class,
    'api_key' => 'your_api_key',
    'api_secret' => 'your_api_secret',
    'endpoint_url' => 'https://api.sms-provider.com/send'
];
```

## Using the Provider System

### 1. Basic Usage

```php
use VertoAD\Core\Services\Channels\SmsChannel;

$config = [
    'provider_class' => GenericRestProvider::class,
    'api_url' => 'https://api.example.com/sms',
    'api_key' => 'your_api_key',
    'api_secret' => 'your_api_secret'
];

$smsChannel = new SmsChannel($config);
```

### 2. Sending Messages

```php
$notification = [
    'user_id' => 123,
    'template_id' => 456,
    'title' => 'Alert',
    'content' => 'Your message here'
];

$result = $smsChannel->send($notification);
```

## Error Handling

The system provides comprehensive error handling:

1. **Provider Level**: Each provider handles its specific errors
2. **Channel Level**: The SMS channel handles general errors
3. **Logging**: All errors are logged with context

## Best Practices

1. **Configuration Validation**
   - Always validate required configuration fields
   - Use meaningful error messages
   - Implement proper fallback mechanisms

2. **Error Handling**
   - Catch and log all exceptions
   - Provide detailed error messages
   - Include relevant context in logs

3. **Message Processing**
   - Sanitize message content
   - Handle character encoding
   - Respect message length limits

4. **Security**
   - Implement proper authentication
   - Use HTTPS for API calls
   - Validate phone numbers

## Available Providers

### 1. GenericRestProvider

A general-purpose provider for REST API-based SMS services.

Configuration:
```php
$config = [
    'provider_class' => GenericRestProvider::class,
    'api_url' => 'required',
    'api_key' => 'required',
    'api_secret' => 'required',
    'use_signature' => true, // optional
    'max_length' => 500 // optional
];
```

### 2. Adding New Providers

To add a new provider:

1. Create a new class in `src/Services/Channels/Sms/Providers/`
2. Extend `BaseSmsProvider`
3. Implement required methods
4. Add provider-specific configuration

## Testing

When implementing a new provider:

1. Test configuration validation
2. Test message sending
3. Test error handling
4. Test response parsing
5. Test edge cases (network errors, invalid responses, etc.)

## Troubleshooting

Common issues and solutions:

1. **Provider Not Found**
   - Check provider class name and namespace
   - Verify autoloading configuration

2. **Configuration Errors**
   - Verify all required fields are provided
   - Check field values are correct
   - Ensure proper formatting

3. **Sending Failures**
   - Check API credentials
   - Verify endpoint URLs
   - Check network connectivity
   - Validate message format

## Security Considerations

1. **API Credentials**
   - Store securely
   - Use environment variables
   - Rotate regularly

2. **Request Signing**
   - Implement when required
   - Use strong hashing algorithms
   - Validate timestamps

3. **Data Protection**
   - Sanitize input
   - Encrypt sensitive data
   - Log safely 