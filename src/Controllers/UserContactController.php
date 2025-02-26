<?php
namespace VertoAD\Core\Controllers;

use VertoAD\Core\Models\UserContact;

class UserContactController extends BaseController {
    private $userContact;
    
    public function __construct() {
        parent::__construct();
        $this->userContact = new UserContact();
    }
    
    /**
     * 显示联系方式管理页面
     */
    public function index() {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->redirect('/login');
            return;
        }
        
        $contacts = $this->userContact->getUserContacts($userId);
        
        $this->render('user/contacts', [
            'contacts' => $contacts,
            'title' => '联系方式管理'
        ]);
    }
    
    /**
     * 更新邮箱地址
     */
    public function updateEmail() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $email = $_POST['email'] ?? '';
        if (empty($email)) {
            $this->jsonResponse(['success' => false, 'message' => '邮箱地址不能为空']);
            return;
        }
        
        try {
            $success = $this->userContact->updateEmail($userId, $email);
            if ($success) {
                // 发送验证码
                $this->userContact->sendVerificationCode($userId, 'email');
            }
            
            $this->jsonResponse([
                'success' => $success,
                'message' => $success ? '邮箱更新成功，请查收验证码' : '邮箱更新失败'
            ]);
            
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => '系统错误']);
        }
    }
    
    /**
     * 更新手机号
     */
    public function updatePhone() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $phone = $_POST['phone'] ?? '';
        if (empty($phone)) {
            $this->jsonResponse(['success' => false, 'message' => '手机号不能为空']);
            return;
        }
        
        try {
            $success = $this->userContact->updatePhone($userId, $phone);
            if ($success) {
                // 发送验证码
                $this->userContact->sendVerificationCode($userId, 'phone');
            }
            
            $this->jsonResponse([
                'success' => $success,
                'message' => $success ? '手机号更新成功，请查收验证码' : '手机号更新失败'
            ]);
            
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => '系统错误']);
        }
    }
    
    /**
     * 验证邮箱
     */
    public function verifyEmail() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $code = $_POST['code'] ?? '';
        if (empty($code)) {
            $this->jsonResponse(['success' => false, 'message' => '验证码不能为空']);
            return;
        }
        
        $success = $this->userContact->verifyEmail($userId, $code);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '邮箱验证成功' : '验证码无效或已过期'
        ]);
    }
    
    /**
     * 验证手机号
     */
    public function verifyPhone() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $code = $_POST['code'] ?? '';
        if (empty($code)) {
            $this->jsonResponse(['success' => false, 'message' => '验证码不能为空']);
            return;
        }
        
        $success = $this->userContact->verifyPhone($userId, $code);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '手机号验证成功' : '验证码无效或已过期'
        ]);
    }
    
    /**
     * 重新发送验证码
     */
    public function resendVerificationCode() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => '无效的请求方法']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'message' => '请先登录']);
            return;
        }
        
        $type = $_POST['type'] ?? '';
        if (!in_array($type, ['email', 'phone'])) {
            $this->jsonResponse(['success' => false, 'message' => '无效的验证类型']);
            return;
        }
        
        $success = $this->userContact->sendVerificationCode($userId, $type);
        
        $this->jsonResponse([
            'success' => $success,
            'message' => $success ? '验证码已发送' : '验证码发送失败'
        ]);
    }
} 