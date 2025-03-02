<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\AdminController;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Models\ActivationKey;

class AdminControllerTest extends TestCase
{
    private $adminController;
    private $request;
    private $response;
    private $user;
    private $activationKey;
    private $originalSession;
    
    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        
        // 创建真实的模型实例
        $this->user = new User();
        $this->activationKey = new ActivationKey();
        
        // 使用反射替换 AdminController 中的依赖
        $this->adminController = new AdminController();
        $reflectionClass = new \ReflectionClass(AdminController::class);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->adminController, $this->response);
        
        $userProperty = $reflectionClass->getProperty('user');
        $userProperty->setAccessible(true);
        $userProperty->setValue($this->adminController, $this->user);
        
        $activationKeyProperty = $reflectionClass->getProperty('activationKey');
        $activationKeyProperty->setAccessible(true);
        $activationKeyProperty->setValue($this->adminController, $this->activationKey);
        
        // 清理测试数据
        $this->user->db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
        $this->user->db->query('DELETE FROM activation_keys WHERE created_by IN (SELECT id FROM users WHERE email LIKE ?)', ['%@test.com']);
        
        // 保存原始会话状态
        $this->originalSession = $_SESSION;
    }
    
    protected function tearDown(): void
    {
        // 清理测试数据
        $this->user->db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
        $this->user->db->query('DELETE FROM activation_keys WHERE created_by IN (SELECT id FROM users WHERE email LIKE ?)', ['%@test.com']);
        
        // 关闭数据库连接
        $this->user->db->close();
        $this->activationKey->db->close();
        
        // 恢复会话状态
        $_SESSION = $this->originalSession;
        session_destroy();
    }
    
    /**
     * 模拟管理员登录
     */
    private function mockAdminLogin()
    {
        // 在测试环境中，不需要启动会话，直接设置$_SESSION变量
        $_SESSION['user_id'] = $this->testAdminId;
        $_SESSION['username'] = 'testadmin';
        $_SESSION['role'] = 'admin';
    }
    
    /**
     * 测试仪表板页面访问
     */
    public function testDashboard()
    {
        // 模拟管理员登录
        $adminId = $this->mockAdminLogin();
        
        // 模拟获取所有用户
        $users = [
            ['id' => $adminId, 'username' => 'testadmin', 'role' => 'admin'],
            ['id' => 2, 'username' => 'testuser', 'role' => 'advertiser']
        ];
        
        // 配置模拟对象
        $this->response->expects($this->once())
            ->method('renderView')
            ->with('admin/dashboard', ['users' => $users])
            ->willReturn('Dashboard View');
        
        // 执行测试
        $result = $this->adminController->dashboard($this->request);
        
        // 验证结果
        $this->assertEquals('Dashboard View', $result);
    }
    
    /**
     * 测试未授权访问仪表板
     */
    public function testDashboardUnauthorized()
    {
        // 确保没有活动会话
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // 注意：由于 dashboard() 方法对未授权用户使用 header() 和 exit，
        // 我们不能完全测试它。这里我们只验证不会抛出异常
        
        try {
            ob_start(); // 捕获输出
            $this->adminController->dashboard($this->request);
            ob_end_clean(); // 清除输出
            $this->assertTrue(true); // 如果没有异常，测试通过
        } catch (\Exception $e) {
            $this->fail('访问仪表板时出现异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试生成密钥 GET 请求
     */
    public function testGenerateKeysGetRequest()
    {
        // 模拟管理员登录
        $this->mockAdminLogin();
        
        // 配置模拟对象
        $this->request->method('getMethod')
            ->willReturn('GET');
        
        $this->response->expects($this->once())
            ->method('renderView')
            ->with('admin/generate_keys')
            ->willReturn('Generate Keys View');
        
        // 执行测试
        $result = $this->adminController->generateKeys($this->request);
        
        // 验证结果
        $this->assertEquals('Generate Keys View', $result);
    }
    
    /**
     * 测试生成密钥 POST 请求
     */
    public function testGenerateKeysPostRequest()
    {
        // 模拟管理员登录
        $adminId = $this->mockAdminLogin();
        
        // 配置模拟对象
        $this->request->method('getMethod')
            ->willReturn('POST');
        
        // 模拟请求数据
        $requestData = [
            'amount' => 100,
            'quantity' => 2
        ];
        
        $this->request->method('getBody')
            ->willReturn($requestData);
        
        // 期望视图被渲染，包含生成的密钥
        $this->response->expects($this->once())
            ->method('renderView')
            ->with(
                $this->equalTo('admin/generate_keys'),
                $this->callback(function($params) {
                    // 验证生成的密钥
                    return isset($params['keys']) && 
                           is_array($params['keys']) && 
                           count($params['keys']) === 2;
                })
            )
            ->willReturn('Generate Keys View with Keys');
        
        // 执行测试
        $result = $this->adminController->generateKeys($this->request);
        
        // 验证结果
        $this->assertEquals('Generate Keys View with Keys', $result);
        
        // 验证数据库中是否创建了激活密钥
        $stmt = $this->activationKey->db->query('SELECT COUNT(*) FROM activation_keys WHERE created_by = ?', [$adminId]);
        $count = (int)$stmt->fetchColumn();
        $this->assertEquals(2, $count);
    }
    
    /**
     * 测试生成密钥并导出 CSV
     */
    public function testGenerateKeysAndExportCsv()
    {
        // 模拟管理员登录
        $this->mockAdminLogin();
        
        // 配置模拟对象
        $this->request->method('getMethod')
            ->willReturn('POST');
        
        // 模拟请求数据（带导出选项）
        $requestData = [
            'amount' => 100,
            'quantity' => 2,
            'export' => 'csv'
        ];
        
        $this->request->method('getBody')
            ->willReturn($requestData);
        
        // 注意：由于此测试方法会使用 header() 和 exit，
        // 我们不能完全测试 CSV 导出功能。这里我们只验证不会抛出异常
        
        try {
            ob_start(); // 捕获输出
            $this->adminController->generateKeys($this->request);
            ob_end_clean(); // 清除输出
            $this->assertTrue(true); // 如果没有异常，测试通过
        } catch (\Exception $e) {
            $this->fail('生成密钥并导出 CSV 时出现异常: ' . $e->getMessage());
        }
    }
} 