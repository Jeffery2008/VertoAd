<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Request;

class RequestTest extends TestCase
{
    private $originalServerVars;
    private $originalGet;
    private $originalPost;
    
    protected function setUp(): void
    {
        // 保存原始的全局变量
        $this->originalServerVars = $_SERVER;
        $this->originalGet = $_GET;
        $this->originalPost = $_POST;
    }
    
    protected function tearDown(): void
    {
        // 恢复原始的全局变量
        $_SERVER = $this->originalServerVars;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
        
        // 清理可能的输入流模拟
        if (isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
            unset($GLOBALS['HTTP_RAW_POST_DATA']);
        }
    }
    
    /**
     * 模拟输入流内容（php://input）
     */
    private function mockPhpInput($data)
    {
        $GLOBALS['HTTP_RAW_POST_DATA'] = $data;
    }
    
    public function testGetMethod()
    {
        // 模拟GET请求
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $request = new Request();
        $this->assertEquals('GET', $request->getMethod());
        
        // 模拟POST请求
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request();
        $this->assertEquals('POST', $request->getMethod());
    }
    
    public function testGetUri()
    {
        $_SERVER['REQUEST_URI'] = '/test/path?query=value';
        $request = new Request();
        $this->assertEquals('/test/path', $request->getUri());
    }
    
    public function testGetQuery()
    {
        $_GET = [
            'param1' => 'value1',
            'param2' => 'value2'
        ];
        
        $request = new Request();
        
        // 测试获取整个查询数组
        $this->assertEquals($_GET, $request->getQuery());
        
        // 测试获取单个查询参数
        $this->assertEquals('value1', $request->getQuery('param1'));
        $this->assertEquals('value2', $request->getQuery('param2'));
        
        // 测试获取不存在的参数
        $this->assertNull($request->getQuery('nonexistent'));
    }
    
    public function testGetBodyFromPost()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'field1' => 'value1',
            'field2' => 'value2'
        ];
        
        $request = new Request();
        
        // 测试获取整个请求体
        $this->assertEquals($_POST, $request->getBody());
        
        // 测试获取单个字段
        $this->assertEquals('value1', $request->getBody('field1'));
        $this->assertEquals('value2', $request->getBody('field2'));
        
        // 测试获取不存在的字段
        $this->assertNull($request->getBody('nonexistent'));
    }
    
    public function testGetBodyFromJson()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = []; // POST数组为空
        
        // 模拟JSON数据
        $jsonData = ['field1' => 'value1', 'field2' => 'value2'];
        $this->mockPhpInput(json_encode($jsonData));
        
        $request = new Request();
        
        // 测试获取整个请求体
        $this->assertEquals($jsonData, $request->getBody());
        
        // 测试获取单个字段
        $this->assertEquals('value1', $request->getBody('field1'));
        $this->assertEquals('value2', $request->getBody('field2'));
    }
    
    public function testParamsHandling()
    {
        $request = new Request();
        
        // 设置参数
        $params = ['id' => 123, 'slug' => 'test-slug'];
        $request->setParams($params);
        
        // 获取参数
        $this->assertEquals(123, $request->getParam('id'));
        $this->assertEquals('test-slug', $request->getParam('slug'));
        $this->assertNull($request->getParam('nonexistent'));
    }
    
    public function testIsAjax()
    {
        // 非AJAX请求
        $_SERVER['HTTP_X_REQUESTED_WITH'] = '';
        $request = new Request();
        $this->assertFalse($request->isAjax());
        
        // AJAX请求
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $request = new Request();
        $this->assertTrue($request->isAjax());
    }
    
    public function testGetIp()
    {
        // 测试REMOTE_ADDR
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        unset($_SERVER['HTTP_CLIENT_IP']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        $request = new Request();
        $this->assertEquals('192.168.1.1', $request->getIp());
        
        // 测试HTTP_X_FORWARDED_FOR
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
        $request = new Request();
        $this->assertEquals('10.0.0.1', $request->getIp());
        
        // 测试HTTP_CLIENT_IP
        $_SERVER['HTTP_CLIENT_IP'] = '172.16.0.1';
        $request = new Request();
        $this->assertEquals('172.16.0.1', $request->getIp());
    }
    
    public function testGetUserAgent()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Test User Agent)';
        $request = new Request();
        $this->assertEquals('Mozilla/5.0 (Test User Agent)', $request->getUserAgent());
        
        // 测试User-Agent不存在的情况
        unset($_SERVER['HTTP_USER_AGENT']);
        $request = new Request();
        $this->assertEquals('', $request->getUserAgent());
    }
    
    public function testIsSecure()
    {
        // 非HTTPS
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = '80';
        $request = new Request();
        $this->assertFalse($request->isSecure());
        
        // HTTPS通过标志
        $_SERVER['HTTPS'] = 'on';
        $request = new Request();
        $this->assertTrue($request->isSecure());
        
        // HTTPS通过端口
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['SERVER_PORT'] = '443';
        $request = new Request();
        $this->assertTrue($request->isSecure());
    }
} 