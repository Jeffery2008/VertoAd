<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Controller;

class TestController extends Controller
{
    // 暴露受保护的方法以进行测试
    public function callView($name, $data = [])
    {
        return $this->view($name, $data);
    }
    
    public function callIsLoggedIn()
    {
        return $this->isLoggedIn();
    }
    
    public function callGetCurrentUser()
    {
        return $this->getCurrentUser();
    }
    
    public function callRequireLogin()
    {
        return $this->requireLogin();
    }
    
    public function callRequireRole($role)
    {
        return $this->requireRole($role);
    }
    
    public function callJson($data)
    {
        return $this->json($data);
    }
    
    public function callRedirect($url)
    {
        return $this->redirect($url);
    }
}

class ControllerTest extends TestCase
{
    private $controller;
    private $originalSession;
    
    protected function setUp(): void
    {
        // 保存原始的会话状态
        $this->originalSession = $_SESSION ?? [];
        
        // 创建测试控制器
        $this->controller = new TestController();
    }
    
    protected function tearDown(): void
    {
        // 恢复会话状态
        $_SESSION = $this->originalSession;
        
        // 清理任何可能的输出缓冲
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    public function testView()
    {
        // 创建临时视图文件
        $tempViewDir = ROOT_PATH . '/app/Views/test';
        if (!is_dir($tempViewDir)) {
            mkdir($tempViewDir, 0777, true);
        }
        $tempViewFile = $tempViewDir . '/view.php';
        file_put_contents($tempViewFile, '<?php echo $testVar; ?>');
        
        // 捕获输出
        ob_start();
        $this->controller->callView('test/view', ['testVar' => 'Test Output']);
        $output = ob_get_clean();
        
        // 验证输出
        $this->assertEquals('Test Output', $output);
        
        // 清理
        unlink($tempViewFile);
        rmdir($tempViewDir);
    }
    
    public function testViewNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('View file not found');
        
        $this->controller->callView('nonexistent/view');
    }
    
    public function testIsLoggedIn()
    {
        // 未登录状态
        $_SESSION = [];
        $this->assertFalse($this->controller->callIsLoggedIn());
        
        // 已登录状态
        $_SESSION['user_id'] = 123;
        $this->assertTrue($this->controller->callIsLoggedIn());
    }
    
    public function testGetCurrentUser()
    {
        // 未登录状态
        $_SESSION = [];
        $this->assertNull($this->controller->callGetCurrentUser());
        
        // 这里无法完全测试getCurrentUser方法，因为它依赖于User模型
        // 要进行完整测试，我们需要模拟User模型或配置测试数据库
        // 在实际测试中，应考虑使用模拟对象
    }
    
    public function testRequireLogin()
    {
        // 测试未登录时重定向
        $_SESSION = [];
        
        // 捕获输出
        ob_start();
        $this->expectOutputString('');
        
        // 我们无法测试exit()，但可以捕获header()的调用
        $this->expectOutputRegex('/.*Location: \/login.*/');
        
        try {
            $this->controller->callRequireLogin();
        } catch (\Exception $e) {
            // 忽略可能由exit()导致的异常
        }
        
        ob_end_clean();
    }
    
    public function testJson()
    {
        // 测试JSON输出
        $data = ['test' => 'value', 'number' => 123];
        
        // 捕获输出
        ob_start();
        
        try {
            $this->controller->callJson($data);
        } catch (\Exception $e) {
            // 忽略可能由exit()导致的异常
        }
        
        $output = ob_get_clean();
        
        // 验证输出的JSON
        $this->assertJsonStringEqualsJsonString(json_encode($data), $output);
    }
    
    public function testRedirect()
    {
        // 测试重定向
        $url = '/test/redirect';
        
        // 捕获输出
        ob_start();
        $this->expectOutputString('');
        
        // 我们无法测试exit()，但可以捕获header()的调用
        $this->expectOutputRegex('/.*Location: \/test\/redirect.*/');
        
        try {
            $this->controller->callRedirect($url);
        } catch (\Exception $e) {
            // 忽略可能由exit()导致的异常
        }
        
        ob_end_clean();
    }
} 