<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\BillingController;
use App\Core\Response;
use App\Models\User;
use App\Models\Ad;
use App\Models\AdView;

class BillingControllerTest extends TestCase
{
    private $billingController;
    private $userModel;
    private $adModel;
    private $adViewModel;
    private $response;
    private $testUsers = [];
    private $testAds = [];
    
    protected function setUp(): void
    {
        // 创建模拟的Response对象
        $this->response = $this->createMock(Response::class);
        
        // 创建真实的模型实例
        $this->userModel = new User();
        $this->adModel = new Ad();
        $this->adViewModel = new AdView();
        
        // 清理测试数据
        $this->cleanupTestData();
        
        // 创建测试用户
        $uniqueSuffix = uniqid();
        $advertiserId = $this->userModel->create('advertiser', 'testadv' . $uniqueSuffix, 'adv' . $uniqueSuffix . '@test.com', 'password123');
        $publisherId = $this->userModel->create('publisher', 'testpub' . $uniqueSuffix, 'pub' . $uniqueSuffix . '@test.com', 'password123');
        
        $this->testUsers['advertiser'] = $advertiserId;
        $this->testUsers['publisher'] = $publisherId;
        
        // 创建测试广告
        $adTitle = 'Test Ad ' . $uniqueSuffix;
        $adContent = 'Test content for ad ' . $uniqueSuffix;
        $adId = $this->adModel->create(
            $this->testUsers['advertiser'],
            $adTitle,
            $adContent,
            100.00,
            0.01
        );
        
        $this->testAds['approved'] = $adId;
        
        // 将广告状态更新为已批准
        $this->adModel->approve($this->testAds['approved']);
        
        // 创建一些测试广告浏览记录
        $this->adViewModel->record($adId, $this->testUsers['publisher'], '127.0.0.1');
        $this->adViewModel->record($adId, $this->testUsers['publisher'], '127.0.0.2');
        
        // 创建BillingController实例
        $this->billingController = new BillingController();
        
        // 使用反射将依赖注入到控制器
        $reflection = new \ReflectionClass($this->billingController);
        
        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->billingController, $this->response);
    }
    
    // 清理测试数据的辅助方法
    private function cleanupTestData()
    {
        $db = new \App\Core\Database();
        $db->query('DELETE FROM ad_views WHERE ad_id IN (SELECT id FROM ads WHERE user_id IN (SELECT id FROM users WHERE email LIKE ?))', ['%@test.com']);
        $db->query('DELETE FROM ads WHERE user_id IN (SELECT id FROM users WHERE email LIKE ?)', ['%@test.com']);
        $db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
    }
    
    protected function tearDown(): void
    {
        // 清理测试数据
        $this->cleanupTestData();
        
        // 清理会话变量
        $_SESSION = [];
    }
    
    /**
     * 模拟广告商登录
     */
    private function mockAdvertiserLogin()
    {
        // 在测试环境中，不需要启动会话，直接设置$_SESSION变量
        $_SESSION['user_id'] = $this->testUsers['advertiser'];
        $_SESSION['username'] = 'testadv';
        $_SESSION['role'] = 'advertiser';
    }
    
    /**
     * 模拟发布者登录
     */
    private function mockPublisherLogin()
    {
        // 在测试环境中，不需要启动会话，直接设置$_SESSION变量
        $_SESSION['user_id'] = $this->testUsers['publisher'];
        $_SESSION['username'] = 'testpub';
        $_SESSION['role'] = 'publisher';
    }
    
    /**
     * 测试获取用户余额
     */
    public function testGetCredits()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['credits']);
            }));
        
        // 执行方法
        $this->billingController->getCredits();
    }
    
    /**
     * 测试未登录时获取余额
     */
    public function testGetCreditsUnauthorized()
    {
        // 确保没有活动会话
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with(
                $this->callback(function($data) {
                    return isset($data['error']) && $data['error'] === 'Unauthorized';
                }),
                401
            );
        
        // 执行方法
        $this->billingController->getCredits();
    }
    
    /**
     * 测试添加余额
     */
    public function testAddCredits()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 模拟请求数据
        $requestData = [
            'amount' => 100.00
        ];
        
        // 模拟 php://input
        $GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($requestData);
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['success']) && $data['success'] === true;
            }));
        
        // 执行方法
        $this->billingController->addCredits();
        
        // 清理
        unset($GLOBALS['HTTP_RAW_POST_DATA']);
    }
    
    /**
     * 测试添加无效余额
     */
    public function testAddCreditsInvalidAmount()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 模拟请求数据 - 无效金额
        $requestData = [
            'amount' => -50.00
        ];
        
        // 模拟 php://input
        $GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($requestData);
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with(
                $this->callback(function($data) {
                    return isset($data['error']) && $data['error'] === 'Invalid amount';
                }),
                400
            );
        
        // 执行方法
        $this->billingController->addCredits();
        
        // 清理
        unset($GLOBALS['HTTP_RAW_POST_DATA']);
    }
    
    /**
     * 测试获取广告统计
     */
    public function testGetAdStats()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['stats']) && isset($data['hourly_stats']);
            }));
        
        // 执行方法
        $this->billingController->getAdStats($this->testAds['approved']);
    }
    
    /**
     * 测试获取非本人广告的统计
     */
    public function testGetAdStatsUnauthorized()
    {
        // 模拟已登录的发布者 (不是广告主)
        $this->mockPublisherLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with(
                $this->callback(function($data) {
                    return isset($data['error']) && $data['error'] === 'Unauthorized';
                }),
                401
            );
        
        // 执行方法
        $this->billingController->getAdStats($this->testAds['approved']);
    }
    
    /**
     * 测试获取发布者统计
     */
    public function testGetPublisherStats()
    {
        // 模拟已登录的发布者
        $this->mockPublisherLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['earnings']) && isset($data['views']);
            }));
        
        // 执行方法
        $this->billingController->getPublisherStats();
    }
} 