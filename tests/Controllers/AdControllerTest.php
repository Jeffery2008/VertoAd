<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\AdController;
use App\Core\Response;
use App\Models\Ad;
use App\Models\User;

class AdControllerTest extends TestCase
{
    private $adController;
    private $adModel;
    private $userModel;
    private $response;
    private $testUsers = [];
    private $testAds = [];
    
    protected function setUp(): void
    {
        // 创建模拟的Response对象
        $this->response = $this->createMock(Response::class);
        
        // 创建真实的模型实例
        $this->adModel = new Ad();
        $this->userModel = new User();
        
        // 清理测试数据
        $this->cleanupTestData();
        
        // 创建测试用户
        $uniqueSuffix = uniqid();
        $advertiserId = $this->userModel->create('advertiser', 'testadv' . $uniqueSuffix, 'adv' . $uniqueSuffix . '@test.com', 'password123');
        $adminId = $this->userModel->create('admin', 'testadmin' . $uniqueSuffix, 'admin' . $uniqueSuffix . '@test.com', 'password123');
        
        $this->testUsers['advertiser'] = $advertiserId;
        $this->testUsers['admin'] = $adminId;
        
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
        
        $this->testAds['draft'] = $adId;
        
        // 创建AdController实例
        $this->adController = new AdController();
        
        // 使用反射将依赖注入到控制器
        $reflection = new \ReflectionClass($this->adController);
        
        $responseProperty = $reflection->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->adController, $this->response);
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
        
        // 关闭数据库连接
        $this->adModel->db->close();
        $this->userModel->db->close();
        
        // 清理会话
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    /**
     * 模拟广告主登录
     */
    private function mockAdvertiserLogin()
    {
        // 在测试环境中，不需要启动会话，直接设置$_SESSION变量
        $_SESSION['user_id'] = $this->testUsers['advertiser'];
        $_SESSION['username'] = 'testadv';
        $_SESSION['role'] = 'advertiser';
    }
    
    /**
     * 模拟管理员登录
     */
    private function mockAdminLogin()
    {
        // 在测试环境中，不需要启动会话，直接设置$_SESSION变量
        $_SESSION['user_id'] = $this->testUsers['admin'];
        $_SESSION['username'] = 'testadmin';
        $_SESSION['role'] = 'admin';
    }
    
    /**
     * 测试广告列表
     */
    public function testList()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['ads']);
            }));
        
        // 执行方法
        $this->adController->list();
    }
    
    /**
     * 测试未登录时访问广告列表
     */
    public function testListUnauthorized()
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
        $this->adController->list();
    }
    
    /**
     * 测试创建广告
     */
    public function testCreate()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 模拟请求数据
        $requestData = [
            'title' => 'Test Ad Create',
            'content' => 'Test content for create',
            'budget' => 200.00,
            'cost_per_view' => 0.02
        ];
        
        // 模拟 php://input
        $GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($requestData);
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['ad_id']);
            }));
        
        // 执行方法
        $this->adController->create();
        
        // 清理
        unset($GLOBALS['HTTP_RAW_POST_DATA']);
    }
    
    /**
     * 测试获取单个广告
     */
    public function testGet()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['ad']);
            }));
        
        // 执行方法
        $this->adController->get($this->testAds['draft']);
    }
    
    /**
     * 测试更新广告
     */
    public function testUpdate()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 模拟请求数据
        $requestData = [
            'title' => 'Updated Test Ad',
            'content' => 'Updated test content',
            'budget' => 150.00,
            'cost_per_view' => 0.015
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
        $this->adController->update($this->testAds['draft']);
        
        // 清理
        unset($GLOBALS['HTTP_RAW_POST_DATA']);
    }
    
    /**
     * 测试删除广告
     */
    public function testDelete()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['success']) && $data['success'] === true;
            }));
        
        // 执行方法
        $this->adController->delete($this->testAds['draft']);
    }
    
    /**
     * 测试提交广告审核
     */
    public function testSubmit()
    {
        // 模拟已登录的广告商
        $this->mockAdvertiserLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['success']) && $data['success'] === true;
            }));
        
        // 执行方法
        $this->adController->submit($this->testAds['draft']);
    }
    
    /**
     * 测试审批广告
     */
    public function testApprove()
    {
        // 模拟已登录的管理员
        $this->mockAdminLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['success']) && $data['success'] === true;
            }));
        
        // 执行方法
        $this->adController->approve($this->testAds['draft']);
    }
    
    /**
     * 测试拒绝广告
     */
    public function testReject()
    {
        // 模拟已登录的管理员
        $this->mockAdminLogin();
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['success']) && $data['success'] === true;
            }));
        
        // 执行方法
        $this->adController->reject($this->testAds['draft']);
    }
    
    /**
     * 测试提供广告
     */
    public function testServe()
    {
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['ad']) || isset($data['error']);
            }));
        
        // 执行方法
        $this->adController->serve();
    }
    
    /**
     * 测试跟踪广告浏览
     */
    public function testTrack()
    {
        // 创建模拟请求数据
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestData = [
            'ad_id' => $this->testAds['draft'],
            'publisher_id' => $this->testUsers['advertiser'] // 为了测试简单，使用advertiser作为publisher
        ];
        
        // 模拟 php://input
        $GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($requestData);
        
        // 设置期望的JSON响应
        $this->response->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['success']);
            }));
        
        // 执行方法
        $this->adController->track();
        
        // 清理
        unset($GLOBALS['HTTP_RAW_POST_DATA']);
        unset($_SERVER['REQUEST_METHOD']);
    }
} 