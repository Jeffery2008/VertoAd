<?php
namespace VertoAD\Core\Utils;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use VertoAD\Core\Utils\ErrorLogger;
use VertoAD\Core\Config\Config;

/**
 * Mailer - Utility class for sending emails
 */
class Mailer {
    /**
     * Send an email
     * 
     * @param string|array $to Recipient email address(es)
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string $altBody Plain text alternative body
     * @param array $options Additional options
     * @return bool True if email was sent successfully
     */
    public static function send($to, $subject, $body, $altBody = '', array $options = []) {
        // Load email configuration
        $config = Config::get('error_logging.email');
        
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            if ($config['smtp']['auth']) {
                $mail->isSMTP();
                $mail->Host = $config['smtp']['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $config['smtp']['username'];
                $mail->Password = $config['smtp']['password'];
                $mail->SMTPSecure = $config['smtp']['encryption'];
                $mail->Port = $config['smtp']['port'];
            }
            
            // Set UTF-8 encoding
            $mail->CharSet = 'UTF-8';
            
            // Recipients
            $mail->setFrom(
                $options['from_address'] ?? $config['from_address'],
                $options['from_name'] ?? $config['from_name']
            );
            
            if (is_array($to)) {
                foreach ($to as $recipient) {
                    if (is_array($recipient) && isset($recipient['email'])) {
                        $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
                    } else {
                        $mail->addAddress($recipient);
                    }
                }
            } else {
                $mail->addAddress($to);
            }
            
            // Add CC recipients if specified
            if (isset($options['cc'])) {
                if (is_array($options['cc'])) {
                    foreach ($options['cc'] as $cc) {
                        $mail->addCC($cc);
                    }
                } else {
                    $mail->addCC($options['cc']);
                }
            }
            
            // Add BCC recipients if specified
            if (isset($options['bcc'])) {
                if (is_array($options['bcc'])) {
                    foreach ($options['bcc'] as $bcc) {
                        $mail->addBCC($bcc);
                    }
                } else {
                    $mail->addBCC($options['bcc']);
                }
            }
            
            // Add reply-to if specified
            if (isset($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to']);
            }
            
            // Add attachments if specified
            if (isset($options['attachments'])) {
                if (is_array($options['attachments'])) {
                    foreach ($options['attachments'] as $attachment) {
                        if (is_array($attachment)) {
                            $mail->addAttachment(
                                $attachment['path'],
                                $attachment['name'] ?? basename($attachment['path']),
                                $attachment['encoding'] ?? 'base64',
                                $attachment['type'] ?? ''
                            );
                        } else {
                            $mail->addAttachment($attachment);
                        }
                    }
                } else {
                    $mail->addAttachment($options['attachments']);
                }
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $altBody ?: strip_tags($body);
            
            // Send the email
            $mail->send();
            
            return true;
        } catch (Exception $e) {
            // Log the error
            ErrorLogger::logAppError(
                'Failed to send email: ' . $mail->ErrorInfo,
                ErrorLogger::SEVERITY_HIGH,
                [
                    'to' => $to,
                    'subject' => $subject,
                    'options' => $options
                ]
            );
            
            return false;
        }
    }
    
    /**
     * Send an error notification email
     * 
     * @param array $recipients Array of recipient email addresses
     * @param array $errorData Error data
     * @return bool True if email was sent successfully
     */
    public static function sendErrorNotification(array $recipients, array $errorData) {
        // Load email configuration
        $config = Config::get('error_logging.email');
        
        if (!$config['enabled']) {
            return false;
        }
        
        // Format subject with severity
        $severity = $errorData['severity'] ?? 'unknown';
        $subject = $config['subject_prefix'] . '[' . strtoupper($severity) . '] ' . substr($errorData['message'], 0, 100);
        
        // Build email body
        $body = self::buildErrorEmailBody($errorData);
        
        // Send the email
        return self::send($recipients, $subject, $body, '', [
            'from_address' => $config['from_address'],
            'from_name' => $config['from_name']
        ]);
    }
    
    /**
     * Build HTML email body for error notifications
     * 
     * @param array $errorData Error data
     * @return string HTML email body
     */
    private static function buildErrorEmailBody(array $errorData) {
        // Get app name and environment
        $appName = getenv('APP_NAME') ?: 'VertoAD';
        $environment = getenv('APP_ENV') ?: 'production';
        
        // Format the timestamp
        $timestamp = isset($errorData['created_at']) 
            ? date('Y-m-d H:i:s', strtotime($errorData['created_at']))
            : date('Y-m-d H:i:s');
        
        // Build the HTML
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title>Error Notification</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.5; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-bottom: 1px solid #dee2e6; }
                .header h2 { margin: 0; color: #343a40; }
                .content { padding: 20px; background-color: #fff; border: 1px solid #dee2e6; border-radius: 4px; }
                .footer { margin-top: 20px; padding: 15px; font-size: 12px; text-align: center; color: #6c757d; }
                .severity { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; text-transform: uppercase; font-size: 12px; }
                .severity-low { background-color: #d4edda; color: #155724; }
                .severity-medium { background-color: #d1ecf1; color: #0c5460; }
                .severity-high { background-color: #fff3cd; color: #856404; }
                .severity-critical { background-color: #f8d7da; color: #721c24; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                table th { text-align: left; padding: 8px; border-bottom: 1px solid #dee2e6; background-color: #f8f9fa; }
                table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
                .stack-trace { background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; overflow-x: auto; margin-top: 15px; }
                .btn { display: inline-block; padding: 8px 12px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . $appName . ' Error Notification</h2>
                </div>
                <div class="content">
                    <p>An error has occurred in the <strong>' . $environment . '</strong> environment:</p>
                    
                    <h3>' . htmlspecialchars($errorData['message']) . '</h3>
                    
                    <p>
                        <span class="severity severity-' . $errorData['severity'] . '">' . strtoupper($errorData['severity']) . '</span>
                        &nbsp;|&nbsp;
                        <strong>Type:</strong> ' . htmlspecialchars($errorData['error_type']) . '
                        &nbsp;|&nbsp;
                        <strong>Time:</strong> ' . $timestamp . '
                    </p>
                    
                    <table>
                        <tr>
                            <th colspan="2">Error Details</th>
                        </tr>';
                    
        // Add file and line information if available
        if (!empty($errorData['file'])) {
            $html .= '
                        <tr>
                            <td><strong>File:</strong></td>
                            <td>' . htmlspecialchars($errorData['file']) . (isset($errorData['line']) ? ' (line ' . $errorData['line'] . ')' : '') . '</td>
                        </tr>';
        }
                    
        // Add URL information if available
        if (!empty($errorData['url']) || !empty($errorData['request_uri'])) {
            $html .= '
                        <tr>
                            <td><strong>URL:</strong></td>
                            <td>' . htmlspecialchars($errorData['url'] ?? $errorData['request_uri']) . '</td>
                        </tr>';
        }
        
        // Add user information if available
        if (!empty($errorData['user_id'])) {
            $html .= '
                        <tr>
                            <td><strong>User:</strong></td>
                            <td>ID: ' . htmlspecialchars($errorData['user_id']) . 
                                (!empty($errorData['user_type']) ? ' (Type: ' . htmlspecialchars($errorData['user_type']) . ')' : '') . '</td>
                        </tr>';
        }
        
        // Add IP address if available
        if (!empty($errorData['ip_address'])) {
            $html .= '
                        <tr>
                            <td><strong>IP Address:</strong></td>
                            <td>' . htmlspecialchars($errorData['ip_address']) . '</td>
                        </tr>';
        }
                    
        $html .= '
                    </table>';
                    
        // Add stack trace if available and enabled
        if (!empty($errorData['stack_trace']) && Config::get('error_logging.email.include_stacktrace')) {
            $stackTrace = is_string($errorData['stack_trace']) 
                ? $errorData['stack_trace'] 
                : (is_array($errorData['stack_trace']) ? json_encode($errorData['stack_trace'], JSON_PRETTY_PRINT) : '');
                
            $html .= '
                    <h4>Stack Trace</h4>
                    <div class="stack-trace">' . nl2br(htmlspecialchars($stackTrace)) . '</div>';
        }
                    
        // Add link to error log if we have an ID
        if (!empty($errorData['id'])) {
            $errorUrl = getenv('APP_URL') . '/admin/errors/view/' . $errorData['id'];
            $html .= '
                    <p style="margin-top: 20px; text-align: center;">
                        <a href="' . $errorUrl . '" class="btn">View Error Details</a>
                    </p>';
        }
                    
        $html .= '
                </div>
                <div class="footer">
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>' . $appName . ' Error Monitoring System</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
} 