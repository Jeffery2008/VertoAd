<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\PublisherController;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\Ad;
use App\Models\AdView;

class PublisherControllerTest extends TestCase
{
    private $publisherController;
    private $request;
    private $response;
    private $user;
    private $ad;
    private $adView;
    private $testUsers = [];

    protected function setUp(): void
    {
        // 创建模拟的Request和Response对象
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        
        // 创建真实的模型实例
        $this->user = new User();
        $this->ad = new Ad();
        $this->adView = new AdView();
        
        // 清理测试数据
        $this->cleanupTestData();
        
        // 创建测试用户
        $uniqueSuffix = uniqid();
        $publisherId = $this->user->create('publisher', 'testpub' . $uniqueSuffix, 'pub' . $uniqueSuffix . '@test.com', 'password123');
        $advertiserId = $this->user->create('advertiser', 'testadv' . $uniqueSuffix, 'adv' . $uniqueSuffix . '@test.com', 'password123');
        
        $this->testUsers['publisher'] = $publisherId;
        $this->testUsers['advertiser'] = $advertiserId;
        
        // 创建PublisherController实例
        $this->publisherController = new PublisherController();
        
        // 使用反射将依赖注入到控制器
        $reflection = new \ReflectionClass($this->publisherController);
        
        $requestProperty = $reflection->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->publisherController, $this->request);
        
        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->publisherController, $this->response);
    }
    
    // 清理测试数据的辅助方法
    private function cleanupTestData()
    {
        $db = new \App\Core\Database();
        $db->query('DELETE FROM ad_views WHERE publisher_id IN (SELECT id FROM users WHERE email LIKE ?)', ['%@test.com']);
        $db->query('DELETE FROM ads WHERE user_id IN (SELECT id FROM users WHERE email LIKE ?)', ['%@test.com']);
        $db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
    }
    
    protected function tearDown(): void
    {
        // 清理测试数据
        $this->cleanupTestData();
        
        // 清理会话
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
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
     * 测试发布者仪表盘页面
     */
    public function testIndex()
    {
        // 模拟已登录的发布者
        $this->mockPublisherLogin();
        
        // 设置期望的view方法调用
        $this->response->expects($this->once())
            ->method('view')
            ->with('publisher/dashboard', $this->callback(function($data) {
                return isset($data['stats']) && isset($data['user']);
            }));
        
        // 执行方法
        $this->publisherController->index();
    }
    
    /**
     * 测试未登录时访问发布者仪表盘
     */
    public function testIndexNotLoggedIn()
    {
        // 确保没有活动会话
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // 设置期望的重定向
        $this->response->expects($this->once())
            ->method('redirect')
            ->with('/login');
        
        // 执行方法
        $this->publisherController->index();
    }
    
    /**
     * 测试获取广告接口
     */
    public function testGetAd()
    {
        // 创建测试广告
        $uniqueSuffix = uniqid();
        $adId = $this->ad->create(
            $this->testUsers['advertiser'],
            'Test Ad ' . $uniqueSuffix,
            'Test content for ad ' . $uniqueSuffix,
            'approved',
            100.00,
            100.00,
            0.01
        );
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return $data['success'] === true && isset($data['ad']);
            }));
        
        // 执行方法
        $this->publisherController->getAd();
    }
    
    /**
     * 测试记录广告浏览
     */
    public function testRecordView()
    {
        // 创建测试广告
        $uniqueSuffix = uniqid();
        $adId = $this->ad->create(
            $this->testUsers['advertiser'],
            'Test Ad ' . $uniqueSuffix,
            'Test content for ad ' . $uniqueSuffix,
            'approved',
            100.00,
            100.00,
            0.01
        );
        
        // 模拟POST请求和请求体
        $this->request->method('getMethod')->willReturn('POST');
        $this->request->method('getBody')->willReturn([
            'ad_id' => $adId,
            'publisher_id' => $this->testUsers['publisher']
        ]);
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return $data['success'] === true && isset($data['view_id']);
            }));
        
        // 执行方法
        $this->publisherController->recordView();
    }
    
    /**
     * 测试统计页面
     */
    public function testStats()
    {
        // 模拟已登录的发布者
        $this->mockPublisherLogin();
        
        // 设置期望的view方法调用
        $this->response->expects($this->once())
            ->method('view')
            ->with('publisher/stats', $this->callback(function($data) {
                return isset($data['views']) && isset($data['earnings']) && isset($data['user']);
            }));
        
        // 执行方法
        $this->publisherController->stats();
    }
} 