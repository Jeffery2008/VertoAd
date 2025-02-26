<?php
namespace VertoAD\Core\Services\Channels;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailChannel extends BaseNotificationChannel {
    private $mailer;
    
    /**
     * Get channel type
     * @return string
     */
    public function getType(): string {
        return 'email';
    }
    
    /**
     * Check if channel is available
     * @return bool
     */
    public function isAvailable(): bool {
        $required = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'from_email', 'from_name'];
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
            $mailer = $this->getMailer();
            
            // Get recipient email from user ID
            $recipientEmail = $this->getRecipientEmail($notification['user_id']);
            if (!$recipientEmail) {
                $this->logError("Recipient email not found", ['user_id' => $notification['user_id']]);
                return false;
            }
            
            $mailer->addAddress($recipientEmail);
            $mailer->Subject = $notification['title'];
            
            // Set content type based on options
            if (!empty($notification['options']['html_content'])) {
                $mailer->isHTML(true);
                $mailer->Body = $notification['content'];
                $mailer->AltBody = strip_tags($notification['content']);
            } else {
                $mailer->isHTML(false);
                $mailer->Body = $notification['content'];
            }
            
            // Add attachments if any
            if (!empty($notification['options']['attachments'])) {
                foreach ($notification['options']['attachments'] as $attachment) {
                    if (isset($attachment['path']) && isset($attachment['name'])) {
                        $mailer->addAttachment($attachment['path'], $attachment['name']);
                    }
                }
            }
            
            $result = $mailer->send();
            $mailer->clearAddresses();
            $mailer->clearAttachments();
            
            return $result;
            
        } catch (Exception $e) {
            $this->logError("Failed to send email: " . $e->getMessage(), [
                'user_id' => $notification['user_id'],
                'template_id' => $notification['template_id']
            ]);
            return false;
        }
    }
    
    /**
     * Get PHPMailer instance
     * @return PHPMailer
     * @throws Exception
     */
    private function getMailer(): PHPMailer {
        if (!$this->mailer) {
            $this->mailer = new PHPMailer(true);
            
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->getConfig('smtp_host');
            $this->mailer->Port = $this->getConfig('smtp_port');
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->getConfig('smtp_user');
            $this->mailer->Password = $this->getConfig('smtp_pass');
            
            // Use TLS if port is 587
            if ($this->getConfig('smtp_port') == 587) {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Sender settings
            $this->mailer->setFrom(
                $this->getConfig('from_email'),
                $this->getConfig('from_name')
            );
            
            // Character encoding
            $this->mailer->CharSet = 'UTF-8';
        }
        
        return $this->mailer;
    }
    
    /**
     * Get recipient email by user ID
     * @param int $userId
     * @return string|null
     */
    private function getRecipientEmail(int $userId): ?string {
        // TODO: Implement user email lookup from database
        // This should be implemented based on your user system
        return null;
    }
} 