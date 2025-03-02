<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Response;

class ResponseTest extends TestCase
{
    private $response;
    
    protected function setUp(): void
    {
        $this->response = new Response();
    }
    
    public function testRenderView()
    {
        // 创建一个临时视图文件
        $tempViewDir = ROOT_PATH . '/app/Views/test';
        if (!is_dir($tempViewDir)) {
            mkdir($tempViewDir, 0777, true);
        }
        $tempViewFile = $tempViewDir . '/temp.php';
        file_put_contents($tempViewFile, '<?php echo $testVar; ?>');
        
        // 捕获输出
        ob_start();
        $this->response->renderView('test/temp', ['testVar' => 'Hello World']);
        $output = ob_get_clean();
        
        // 验证结果
        $this->assertEquals('Hello World', $output);
        
        // 清理
        unlink($tempViewFile);
        rmdir($tempViewDir);
    }
    
    public function testRenderNonExistentView()
    {
        // 捕获输出
        ob_start();
        $this->response->renderView('nonexistent/view');
        $output = ob_get_clean();
        
        // 验证结果
        $this->assertStringContainsString('Error: View not found', $output);
    }
    
    public function testRenderWithData()
    {
        // 创建一个临时视图文件，包含多个变量
        $tempViewDir = ROOT_PATH . '/app/Views/test';
        if (!is_dir($tempViewDir)) {
            mkdir($tempViewDir, 0777, true);
        }
        $tempViewFile = $tempViewDir . '/data.php';
        $viewContent = <<<'EOT'
<?php 
echo "Name: " . $name; 
echo ", Age: " . $age; 
?>
EOT;
        file_put_contents($tempViewFile, $viewContent);
        
        // 捕获输出
        ob_start();
        $this->response->renderView('test/data', [
            'name' => 'John Doe',
            'age' => 30
        ]);
        $output = ob_get_clean();
        
        // 验证结果
        $this->assertEquals('Name: John Doe, Age: 30', $output);
        
        // 清理
        unlink($tempViewFile);
        rmdir($tempViewDir);
    }
    
    public function testRenderWithNestedArrayData()
    {
        // 创建一个临时视图文件，使用嵌套数组
        $tempViewDir = ROOT_PATH . '/app/Views/test';
        if (!is_dir($tempViewDir)) {
            mkdir($tempViewDir, 0777, true);
        }
        $tempViewFile = $tempViewDir . '/nested.php';
        $viewContent = <<<'EOT'
<?php 
echo "User: " . $user['name']; 
echo ", Email: " . $user['email']; 
?>
EOT;
        file_put_contents($tempViewFile, $viewContent);
        
        // 捕获输出
        ob_start();
        $this->response->renderView('test/nested', [
            'user' => [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com'
            ]
        ]);
        $output = ob_get_clean();
        
        // 验证结果
        $this->assertEquals('User: Jane Smith, Email: jane@example.com', $output);
        
        // 清理
        unlink($tempViewFile);
        rmdir($tempViewDir);
    }
} 