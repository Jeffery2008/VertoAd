<?php

use PHPUnit\Framework\TestCase;
use App\Core\ErrorHandler;
use App\Core\Database;

class ErrorHandlingTest extends TestCase
{
    private $errorHandler;
    private $mockDb;
    
    protected function setUp(): void
    {
        // Backup existing error and exception handlers
        $this->originalErrorHandler = set_error_handler(function() {});
        restore_error_handler();
        
        $this->originalExceptionHandler = set_exception_handler(function() {});
        restore_exception_handler();
        
        // Create a mock Database object
        $this->mockDb = $this->createMock(Database::class);
        
        // Create a reflection of the ErrorHandler class
        $reflectionClass = new ReflectionClass(ErrorHandler::class);
        
        // Create an instance of ErrorHandler using reflection to bypass the private constructor
        $this->errorHandler = $reflectionClass->newInstanceWithoutConstructor();
        
        // Set the mockDb to the ErrorHandler instance using reflection
        $reflectionProperty = $reflectionClass->getProperty('db');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($this->errorHandler, $this->mockDb);
        
        // Reset the instance property to allow getInstance to return our test instance
        $instanceProperty = $reflectionClass->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, $this->errorHandler);
        
        // Register the error handler for testing
        $this->errorHandler->register();
    }
    
    protected function tearDown(): void
    {
        // Reset ErrorHandler singleton instance
        $reflectionClass = new ReflectionClass(ErrorHandler::class);
        $instanceProperty = $reflectionClass->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
        
        // Restore original error and exception handlers
        if ($this->originalErrorHandler) {
            set_error_handler($this->originalErrorHandler);
        } else {
            restore_error_handler();
        }
        
        if ($this->originalExceptionHandler) {
            set_exception_handler($this->originalExceptionHandler);
        } else {
            restore_exception_handler();
        }
    }
    
    public function testLogErrorOnWarningTrigger(): void
    {
        // Configure the mock to expect the database query with a Warning type
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) {
                    return $params[0] === 'Warning' && 
                           strpos($params[1], 'Test warning') !== false;
                })
            );
        
        // Trigger a warning (which should be converted to an exception by our error handler)
        try {
            trigger_error('Test warning', E_USER_WARNING);
            $this->fail('Warning should have been converted to exception');
        } catch (\Throwable $e) {
            // Expected behavior - error is converted to exception
            $this->assertInstanceOf(ErrorException::class, $e);
            $this->assertStringContainsString('Test warning', $e->getMessage());
        }
    }
    
    public function testLogErrorOnNoticeTrigger(): void
    {
        // Configure the mock to expect the database query with a Notice type
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) {
                    return $params[0] === 'User Notice' && 
                           strpos($params[1], 'Test notice') !== false;
                })
            );
        
        // Trigger a notice (which should be converted to an exception by our error handler)
        try {
            trigger_error('Test notice', E_USER_NOTICE);
            $this->fail('Notice should have been converted to exception');
        } catch (\Throwable $e) {
            // Expected behavior - error is converted to exception
            $this->assertInstanceOf(ErrorException::class, $e);
            $this->assertStringContainsString('Test notice', $e->getMessage());
        }
    }
    
    public function testLogExceptionOnDirectThrow(): void
    {
        // Configure the mock to expect the database query for the exception
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) {
                    return $params[0] === 'Exception' && 
                           $params[1] === 'Test exception';
                })
            );
        
        // Let the exception handler catch the exception
        $previous = set_exception_handler(function() {});
        restore_exception_handler();
        
        try {
            // Throw an exception that should be caught by the exception handler
            $exception = new Exception('Test exception');
            $this->errorHandler->handleException($exception);
        } catch (\Throwable $e) {
            // This should not happen, as the exception should be handled
            $this->fail('Exception should have been handled: ' . $e->getMessage());
        }
    }
    
    public function testManualExceptionLogging(): void
    {
        // Configure the mock to expect the database query for logging the exception
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) {
                    return $params[0] === 'RuntimeException' && 
                           $params[1] === 'Test manual exception logging';
                })
            );
        
        // Manually log an exception
        $exception = new RuntimeException('Test manual exception logging');
        ErrorHandler::logException($exception, ['extra' => 'data']);
    }
    
    public function testExceptionWithAdditionalData(): void
    {
        // Additional data to include with the exception
        $additionalData = ['user_action' => 'login', 'attempt' => 3];
        
        // Configure the mock to expect the database query with extra data in the trace
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) use ($additionalData) {
                    return $params[0] === 'InvalidArgumentException' && 
                           $params[1] === 'Test with additional data' &&
                           strpos($params[4], json_encode($additionalData)) !== false;
                })
            );
        
        // Log an exception with additional data
        $exception = new InvalidArgumentException('Test with additional data');
        ErrorHandler::logException($exception, $additionalData);
    }
    
    public function testRequestDataIsCollected(): void
    {
        // Set up some test request data
        $_GET = ['page' => '1', 'sort' => 'date'];
        $_POST = ['username' => 'test_user', 'action' => 'submit'];
        $_COOKIE = ['session_id' => 'abc123'];
        $_SERVER['REQUEST_URI'] = '/test/page';
        $_SERVER['HTTP_REFERER'] = 'http://example.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';
        
        // Configure the mock to expect the database query with request data
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) {
                    $requestData = json_decode($params[5], true);
                    return isset($requestData['GET']['page']) && 
                           $requestData['GET']['page'] === '1' &&
                           isset($requestData['POST']['username']) && 
                           $requestData['POST']['username'] === 'test_user' &&
                           isset($requestData['COOKIE']['session_id']) && 
                           $requestData['COOKIE']['session_id'] === 'abc123' &&
                           isset($requestData['SERVER']['REQUEST_URI']) && 
                           $requestData['SERVER']['REQUEST_URI'] === '/test/page';
                })
            );
        
        // Create a test exception
        $exception = new Exception('Test exception with request data');
        
        // Handle the exception
        $this->errorHandler->handleException($exception);
    }
} 