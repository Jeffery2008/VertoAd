<?php
namespace VertoAD\Core\Models;

use VertoAD\Core\Utils\Database;
use VertoAD\Core\Utils\Cache;
use VertoAD\Core\Utils\Validator;

class UserContact {
    private $db;
    private $cache;
    private $validator;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->cache = Cache::getInstance();
        $this->validator = new Validator();
    }
    
    /**
     * 获取用户联系方式
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserContacts(int $userId): array {
        $cacheKey = "user_contacts:{$userId}";
        $contacts = $this->cache->get($cacheKey);
        
        if ($contacts === false) {
            $sql = "SELECT * FROM user_contacts WHERE user_id = :user_id";
            $params = [':user_id' => $userId];
            $contacts = $this->db->query($sql, $params)->fetch();
            
            if ($contacts) {
                $this->cache->set($cacheKey, $contacts, 3600); // 缓存1小时
            }
        }
        
        return $contacts ?: [];
    }
    
    /**
     * 更新用户邮箱
     * @param int $userId 用户ID
     * @param string $email 邮箱地址
     * @param bool $verified 是否已验证
     * @return bool
     */
    public function updateEmail(int $userId, string $email, bool $verified = false): bool {
        if (!$this->validator->isValidEmail($email)) {
            throw new \InvalidArgumentException('无效的邮箱地址');
        }
        
        try {
            $sql = "INSERT INTO user_contacts (user_id, email, email_verified) 
                   VALUES (:user_id, :email, :verified)
                   ON DUPLICATE KEY UPDATE 
                   email = :email, 
                   email_verified = :verified,
                   updated_at = CURRENT_TIMESTAMP";
            
            $params = [
                ':user_id' => $userId,
                ':email' => $email,
                ':verified' => $verified
            ];
            
            $success = $this->db->execute($sql, $params);
            
            if ($success) {
                $this->clearUserCache($userId);
                $this->logContactChange($userId, 'email', $email);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            // 记录错误日志
            error_log("Failed to update user email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 更新用户手机号
     * @param int $userId 用户ID
     * @param string $phone 手机号
     * @param bool $verified 是否已验证
     * @return bool
     */
    public function updatePhone(int $userId, string $phone, bool $verified = false): bool {
        if (!$this->validator->isValidPhone($phone)) {
            throw new \InvalidArgumentException('无效的手机号');
        }
        
        try {
            $sql = "INSERT INTO user_contacts (user_id, phone, phone_verified) 
                   VALUES (:user_id, :phone, :verified)
                   ON DUPLICATE KEY UPDATE 
                   phone = :phone, 
                   phone_verified = :verified,
                   updated_at = CURRENT_TIMESTAMP";
            
            $params = [
                ':user_id' => $userId,
                ':phone' => $phone,
                ':verified' => $verified
            ];
            
            $success = $this->db->execute($sql, $params);
            
            if ($success) {
                $this->clearUserCache($userId);
                $this->logContactChange($userId, 'phone', $phone);
            }
            
            return $success;
            
        } catch (\Exception $e) {
            // 记录错误日志
            error_log("Failed to update user phone: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 验证用户邮箱
     * @param int $userId 用户ID
     * @param string $verificationCode 验证码
     * @return bool
     */
    public function verifyEmail(int $userId, string $verificationCode): bool {
        // 验证码检查逻辑
        if (!$this->checkVerificationCode($userId, 'email', $verificationCode)) {
            return false;
        }
        
        $sql = "UPDATE user_contacts 
               SET email_verified = 1, 
                   email_verified_at = CURRENT_TIMESTAMP 
               WHERE user_id = :user_id";
        
        $success = $this->db->execute($sql, [':user_id' => $userId]);
        
        if ($success) {
            $this->clearUserCache($userId);
            $this->clearVerificationCode($userId, 'email');
        }
        
        return $success;
    }
    
    /**
     * 验证用户手机号
     * @param int $userId 用户ID
     * @param string $verificationCode 验证码
     * @return bool
     */
    public function verifyPhone(int $userId, string $verificationCode): bool {
        // 验证码检查逻辑
        if (!$this->checkVerificationCode($userId, 'phone', $verificationCode)) {
            return false;
        }
        
        $sql = "UPDATE user_contacts 
               SET phone_verified = 1, 
                   phone_verified_at = CURRENT_TIMESTAMP 
               WHERE user_id = :user_id";
        
        $success = $this->db->execute($sql, [':user_id' => $userId]);
        
        if ($success) {
            $this->clearUserCache($userId);
            $this->clearVerificationCode($userId, 'phone');
        }
        
        return $success;
    }
    
    /**
     * 发送验证码
     * @param int $userId 用户ID
     * @param string $type 类型（email/phone）
     * @return bool
     */
    public function sendVerificationCode(int $userId, string $type): bool {
        $contacts = $this->getUserContacts($userId);
        if (!$contacts) {
            return false;
        }
        
        $code = $this->generateVerificationCode();
        $recipient = $type === 'email' ? $contacts['email'] : $contacts['phone'];
        
        if (empty($recipient)) {
            return false;
        }
        
        // 保存验证码
        $this->saveVerificationCode($userId, $type, $code);
        
        // 发送验证码
        if ($type === 'email') {
            return $this->sendEmailVerificationCode($recipient, $code);
        } else {
            return $this->sendSmsVerificationCode($recipient, $code);
        }
    }
    
    /**
     * 记录联系方式变更
     * @param int $userId 用户ID
     * @param string $type 类型
     * @param string $newValue 新值
     */
    private function logContactChange(int $userId, string $type, string $newValue): void {
        $sql = "INSERT INTO user_contact_changes 
               (user_id, change_type, new_value) 
               VALUES (:user_id, :type, :value)";
        
        $params = [
            ':user_id' => $userId,
            ':type' => $type,
            ':value' => $newValue
        ];
        
        try {
            $this->db->execute($sql, $params);
        } catch (\Exception $e) {
            error_log("Failed to log contact change: " . $e->getMessage());
        }
    }
    
    /**
     * 清除用户缓存
     * @param int $userId 用户ID
     */
    private function clearUserCache(int $userId): void {
        $this->cache->delete("user_contacts:{$userId}");
    }
    
    /**
     * 生成验证码
     * @return string
     */
    private function generateVerificationCode(): string {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * 保存验证码
     * @param int $userId 用户ID
     * @param string $type 类型
     * @param string $code 验证码
     */
    private function saveVerificationCode(int $userId, string $type, string $code): void {
        $key = "verification_code:{$userId}:{$type}";
        $this->cache->set($key, $code, 600); // 10分钟有效期
    }
    
    /**
     * 检查验证码
     * @param int $userId 用户ID
     * @param string $type 类型
     * @param string $code 验证码
     * @return bool
     */
    private function checkVerificationCode(int $userId, string $type, string $code): bool {
        $key = "verification_code:{$userId}:{$type}";
        $savedCode = $this->cache->get($key);
        return $savedCode && $savedCode === $code;
    }
    
    /**
     * 清除验证码
     * @param int $userId 用户ID
     * @param string $type 类型
     */
    private function clearVerificationCode(int $userId, string $type): void {
        $key = "verification_code:{$userId}:{$type}";
        $this->cache->delete($key);
    }
    
    /**
     * 发送邮箱验证码
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @return bool
     */
    private function sendEmailVerificationCode(string $email, string $code): bool {
        // 使用EmailChannel发送验证码
        $emailChannel = new EmailChannel();
        return $emailChannel->send([
            'to' => $email,
            'subject' => '验证码',
            'content' => "您的验证码是：{$code}，10分钟内有效。"
        ]);
    }
    
    /**
     * 发送短信验证码
     * @param string $phone 手机号
     * @param string $code 验证码
     * @return bool
     */
    private function sendSmsVerificationCode(string $phone, string $code): bool {
        // 使用SmsChannel发送验证码
        $smsChannel = new SmsChannel();
        return $smsChannel->send([
            'to' => $phone,
            'content' => "您的验证码是：{$code}，10分钟内有效。"
        ]);
    }
} 