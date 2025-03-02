<?php

namespace Tests\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\AuthController;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

class AuthControllerTest extends TestCase
{
    private $authController;
    private $request;
    private $response;
    private $user;
    
    protected function setUp(): void
    {
        $this->request = $this->createMock(Request::class);
        $this->response = $this->createMock(Response::class);
        
        // 创建真实的 User 模型实例
        $this->user = new User();
        
        // 使用反射替换 AuthController 中的 Response 依赖
        $this->authController = new AuthController();
        $reflectionClass = new \ReflectionClass(AuthController::class);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->authController, $this->response);
        
        // 清理测试数据
        $this->user->db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
    }
    
    protected function tearDown(): void
    {
        // 清理测试数据
        $this->user->db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
        
        // 关闭数据库连接
        $this->user->db->close();
        
        // 清理会话变量
        $_SESSION = [];
    }
    
    /**
     * 测试登录 GET 请求
     */
    public function testLoginGetRequest()
    {
        // 配置模拟对象
        $this->request->method('getMethod')
            ->willReturn('GET');
        
        $this->request->method('getUri')
            ->willReturn('/admin/login');
        
        $this->request->method('isAjax')
            ->willReturn(false);
        
        // 期望视图被渲染
        $this->response->expects($this->once())
            ->method('renderView')
            ->with('admin/login')
            ->willReturn('Login View');
        
        // 执行测试
        $result = $this->authController->login($this->request);
        
        // 验证结果
        $this->assertEquals('Login View', $result);
    }
    
    /**
     * 测试使用无效凭据登录
     */
    public function testLoginWithInvalidCredentials()
    {
        // 配置模拟对象
        $this->request->method('getMethod')
            ->willReturn('POST');
        
        $this->request->method('isAjax')
            ->willReturn(false);
        
        // 模拟请求数据
        $requestData = [
            'username' => 'invaliduser',
            'password' => 'invalidpassword'
        ];
        
        $this->request->method('getBody')
            ->willReturn($requestData);
        
        // 期望渲染带有错误消息的视图
        $this->response->expects($this->once())
            ->method('renderView')
            ->with('admin/login', ['error' => 'Invalid username or password'])
            ->willReturn('Login View with Error');
        
        // 执行测试
        $result = $this->authController->login($this->request);
        
        // 验证结果
        $this->assertEquals('Login View with Error', $result);
    }
    
    /**
     * 测试使用有效凭据登录
     */
    public function testLoginWithValidCredentials()
    {
        // 创建测试用户
        $username = 'testuser';
        $password = 'testpassword';
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $userId = $this->user->create('admin', $username, 'test@test.com', $password);
        
        // 配置模拟对象
        $this->request->method('getMethod')
            ->willReturn('POST');
        
        $this->request->method('isAjax')
            ->willReturn(false);
        
        // 模拟请求数据
        $requestData = [
            'username' => $username,
            'password' => $password
        ];
        
        $this->request->method('getBody')
            ->willReturn($requestData);
        
        // 注意：由于此测试方法会调用 header() 和 exit，
        // 这里我们不能完全测试成功的登录流程 
        // 这里我们只验证不会抛出异常
        
        try {
            ob_start(); // 捕获输出
            $this->authController->login($this->request);
            ob_end_clean(); // 清除输出
            $this->assertTrue(true); // 如果没有异常，测试通过
        } catch (\Exception $e) {
            $this->fail('登录过程中出现异常: ' . $e->getMessage());
        }
    }
    
    /**
     * 测试注销功能
     */
    public function testLogout()
    {
        // 注意：由于 logout() 方法使用 header() 和 exit，
        // 我们不能完全测试它。这里我们只验证不会抛出异常
        
        try {
            ob_start(); // 捕获输出
            $this->authController->logout();
            ob_end_clean(); // 清除输出
            $this->assertTrue(true); // 如果没有异常，测试通过
        } catch (\Exception $e) {
            $this->fail('注销过程中出现异常: ' . $e->getMessage());
        }
    }
} 