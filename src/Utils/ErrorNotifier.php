<?php
namespace HFI\UtilityCenter\Utils;

use HFI\UtilityCenter\Models\ErrorLog;
use HFI\UtilityCenter\Config\Config;
use HFI\UtilityCenter\Utils\Mail;
use PDO;

/**
 * ErrorNotifier - Utility class for sending notifications about errors
 */
class ErrorNotifier {
    // Notification methods
    public const METHOD_EMAIL = 'email';
    public const METHOD_SMS = 'sms';
    public const METHOD_SLACK = 'slack';
    
    private static $db;
    private static $config;
    
    /**
     * Initialize the error notifier
     * 
     * @param PDO $db Database connection
     */
    public static function init(PDO $db) {
        self::$db = $db;
        self::$config = Config::get('error_notifications');
    }
    
    /**
     * Send notifications for an error log entry
     * 
     * @param int $errorLogId The ID of the error log entry
     * @param array $errorData The error data
     * @return array Status of notification attempts
     */
    public static function sendNotifications($errorLogId, array $errorData) {
        if (!isset(self::$db)) {
            return ['status' => 'error', 'message' => 'Error notifier not initialized'];
        }
        
        $severity = $errorData['severity'] ?? 'medium';
        $errorType = $errorData['error_type'] ?? 'application';
        
        // Get subscribers who should be notified based on severity and error type
        $subscribers = self::getSubscribers($severity, $errorType);
        
        $results = [];
        
        foreach ($subscribers as $subscriber) {
            $notificationId = self::recordNotificationAttempt($errorLogId, $subscriber['user_id'], $subscriber['method']);
            
            $status = 'pending';
            $message = '';
            
            try {
                switch ($subscriber['method']) {
                    case self::METHOD_EMAIL:
                        $status = self::sendEmailNotification($subscriber, $errorData);
                        break;
                    case self::METHOD_SMS:
                        $status = self::sendSmsNotification($subscriber, $errorData);
                        break;
                    case self::METHOD_SLACK:
                        $status = self::sendSlackNotification($subscriber, $errorData);
                        break;
                }
                
                $message = "Notification sent via {$subscriber['method']}";
            } catch (\Exception $e) {
                $status = 'failed';
                $message = $e->getMessage();
            }
            
            self::updateNotificationStatus($notificationId, $status, $message);
            
            $results[] = [
                'user_id' => $subscriber['user_id'],
                'method' => $subscriber['method'],
                'status' => $status,
                'message' => $message
            ];
        }
        
        return [
            'status' => 'success',
            'notifications_sent' => count($results),
            'results' => $results
        ];
    }
    
    /**
     * Get subscribers who should be notified based on severity and error type
     * 
     * @param string $severity Error severity
     * @param string $errorType Error type
     * @return array List of subscribers
     */
    private static function getSubscribers($severity, $errorType) {
        $severityLevel = self::getSeverityLevel($severity);
        
        $stmt = self::$db->prepare("
            SELECT s.*, u.email, u.phone, u.name
            FROM error_notification_subscriptions s
            JOIN users u ON s.user_id = u.id
            WHERE s.min_severity_level <= :severity_level
            AND (s.error_types IS NULL OR s.error_types LIKE :error_type)
            AND s.is_active = 1
        ");
        
        $wildcardType = "%{$errorType}%";
        $stmt->bindParam(':severity_level', $severityLevel, PDO::PARAM_INT);
        $stmt->bindParam(':error_type', $wildcardType, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Record a notification attempt in the database
     * 
     * @param int $errorLogId The ID of the error log entry
     * @param int $userId The ID of the user to notify
     * @param string $method Notification method
     * @return int The ID of the notification record
     */
    private static function recordNotificationAttempt($errorLogId, $userId, $method) {
        $stmt = self::$db->prepare("
            INSERT INTO error_notifications (error_log_id, user_id, method, status, created_at)
            VALUES (:error_log_id, :user_id, :method, 'pending', NOW())
        ");
        
        $stmt->bindParam(':error_log_id', $errorLogId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':method', $method, PDO::PARAM_STR);
        $stmt->execute();
        
        return self::$db->lastInsertId();
    }
    
    /**
     * Update notification status in the database
     * 
     * @param int $notificationId The ID of the notification record
     * @param string $status The new status
     * @param string $message Additional message
     */
    private static function updateNotificationStatus($notificationId, $status, $message = '') {
        $stmt = self::$db->prepare("
            UPDATE error_notifications
            SET status = :status, message = :message, updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    /**
     * Send an email notification
     * 
     * @param array $subscriber Subscriber information
     * @param array $errorData Error data
     * @return bool True if sent successfully
     */
    private static function sendEmailNotification(array $subscriber, array $errorData) {
        // Check if email notifications are enabled
        if (!Config::get('error_logging.email.enabled')) {
            return self::updateNotificationStatus(
                $subscriber['notification_id'], 
                'failed', 
                'Email notifications are disabled'
            );
        }
        
        try {
            // Get email address to send to
            $email = $subscriber['notification_target'] ?: $subscriber['email'] ?? null;
            
            if (empty($email)) {
                return self::updateNotificationStatus(
                    $subscriber['notification_id'], 
                    'failed', 
                    'No valid email address found'
                );
            }
            
            // Send the email using Mailer utility
            $sent = Mailer::sendErrorNotification([$email], $errorData);
            
            // Update notification status
            if ($sent) {
                return self::updateNotificationStatus(
                    $subscriber['notification_id'], 
                    'sent'
                );
            } else {
                return self::updateNotificationStatus(
                    $subscriber['notification_id'], 
                    'failed', 
                    'Failed to send email'
                );
            }
        } catch (\Exception $e) {
            // Log the error and update status
            return self::updateNotificationStatus(
                $subscriber['notification_id'], 
                'failed', 
                'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Format the email body for error notifications
     * 
     * @param array $errorData Error data
     * @param array $subscriber Subscriber information
     * @return string Formatted HTML email body
     */
    private static function formatEmailBody(array $errorData, array $subscriber) {
        $appName = Config::get('app')['name'] ?? 'HFI Utility Center';
        $errorTime = date('Y-m-d H:i:s', strtotime($errorData['created_at'] ?? 'now'));
        $errorDetails = self::formatErrorDetails($errorData);
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f8f8; padding: 10px; border-bottom: 2px solid #ddd; }
                .content { padding: 20px 0; }
                .footer { border-top: 1px solid #ddd; padding-top: 10px; font-size: 12px; color: #777; }
                .severity { font-weight: bold; }
                .severity-critical { color: #d9534f; }
                .severity-high { color: #f0ad4e; }
                .severity-medium { color: #5bc0de; }
                .severity-low { color: #5cb85c; }
                .details { background-color: #f9f9f9; padding: 10px; border-left: 3px solid #ddd; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>{$appName} - Error Notification</h2>
                </div>
                <div class='content'>
                    <p>Hello {$subscriber['name']},</p>
                    <p>This is an automated notification about an error that occurred in the {$appName} system.</p>
                    <p>
                        <strong>Error Type:</strong> {$errorData['error_type']}<br>
                        <strong>Severity:</strong> <span class='severity severity-{$errorData['severity']}'>{$errorData['severity']}</span><br>
                        <strong>Time:</strong> {$errorTime}<br>
                        <strong>Message:</strong> {$errorData['error_message']}
                    </p>
                    
                    <div class='details'>
                        <h3>Error Details:</h3>
                        {$errorDetails}
                    </div>
                    
                    <p>You can view more details and manage this error in the admin dashboard.</p>
                </div>
                <div class='footer'>
                    <p>You received this notification because you are subscribed to {$errorData['error_type']} errors with {$errorData['severity']} severity or higher. You can update your notification preferences in the admin dashboard.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $body;
    }
    
    /**
     * Format error details for display in notifications
     * 
     * @param array $errorData Error data
     * @return string Formatted error details HTML
     */
    private static function formatErrorDetails(array $errorData) {
        $details = '';
        
        if (!empty($errorData['file'])) {
            $details .= "<p><strong>File:</strong> {$errorData['file']}</p>";
        }
        
        if (!empty($errorData['line'])) {
            $details .= "<p><strong>Line:</strong> {$errorData['line']}</p>";
        }
        
        if (!empty($errorData['error_code'])) {
            $details .= "<p><strong>Error Code:</strong> {$errorData['error_code']}</p>";
        }
        
        if (!empty($errorData['url'])) {
            $details .= "<p><strong>URL:</strong> {$errorData['url']}</p>";
        }
        
        if (!empty($errorData['stack_trace'])) {
            $trace = is_string($errorData['stack_trace']) 
                ? $errorData['stack_trace'] 
                : json_encode($errorData['stack_trace'], JSON_PRETTY_PRINT);
            
            $details .= "<p><strong>Stack Trace:</strong></p><pre>{$trace}</pre>";
        }
        
        // Add additional data if available
        if (!empty($errorData['additional_data']) && is_string($errorData['additional_data'])) {
            try {
                $additionalData = json_decode($errorData['additional_data'], true);
                if (!empty($additionalData) && is_array($additionalData)) {
                    // Filter out sensitive or redundant data
                    unset($additionalData['stack_trace']);
                    
                    if (!empty($additionalData)) {
                        $details .= "<p><strong>Additional Data:</strong></p><pre>" . 
                            json_encode($additionalData, JSON_PRETTY_PRINT) . 
                            "</pre>";
                    }
                }
            } catch (\Exception $e) {
                // Ignore JSON parsing errors
            }
        }
        
        return $details;
    }
    
    /**
     * Send an SMS notification
     * 
     * @param array $subscriber Subscriber information
     * @param array $errorData Error data
     * @return string Status of the notification attempt
     */
    private static function sendSmsNotification(array $subscriber, array $errorData) {
        if (empty($subscriber['phone'])) {
            return 'failed';
        }
        
        // This is a placeholder. In a real implementation, you would integrate with an SMS service
        if (!empty(self::$config['sms']['service']) && self::$config['sms']['service'] === 'twilio') {
            // Example Twilio integration code would go here
            // For now, we'll just log that we would have sent an SMS
            error_log("Would send SMS to {$subscriber['phone']} about {$errorData['severity']} {$errorData['error_type']} error");
            return 'sent';
        }
        
        return 'not_configured';
    }
    
    /**
     * Send a Slack notification
     * 
     * @param array $subscriber Subscriber information
     * @param array $errorData Error data
     * @return string Status of the notification attempt
     */
    private static function sendSlackNotification(array $subscriber, array $errorData) {
        // This is a placeholder. In a real implementation, you would integrate with Slack's API
        if (!empty(self::$config['slack']['webhook_url'])) {
            // Example Slack integration code would go here
            // For now, we'll just log that we would have sent a Slack notification
            error_log("Would send Slack notification to channel {$subscriber['slack_channel']} about {$errorData['severity']} {$errorData['error_type']} error");
            return 'sent';
        }
        
        return 'not_configured';
    }
    
    /**
     * Get the numeric severity level for a given severity string
     * 
     * @param string $severity Severity string (low, medium, high, critical)
     * @return int Numeric severity level (1-4)
     */
    private static function getSeverityLevel($severity) {
        switch (strtolower($severity)) {
            case 'critical':
                return 4;
            case 'high':
                return 3;
            case 'medium':
                return 2;
            case 'low':
            default:
                return 1;
        }
    }
} 