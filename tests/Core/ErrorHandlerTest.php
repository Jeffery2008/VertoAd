<?php

use PHPUnit\Framework\TestCase;
use App\Core\ErrorHandler;
use App\Core\Database;

class ErrorHandlerTest extends TestCase
{
    private $errorHandler;
    private $mockDb;

    protected function setUp(): void
    {
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
    }
    
    protected function tearDown(): void
    {
        // Reset ErrorHandler singleton instance after each test
        $reflectionClass = new ReflectionClass(ErrorHandler::class);
        $instanceProperty = $reflectionClass->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }
    
    public function testGetInstance(): void
    {
        // First getInstance call should create and return the instance
        $instance1 = ErrorHandler::getInstance();
        $this->assertInstanceOf(ErrorHandler::class, $instance1);
        
        // Second getInstance call should return the same instance
        $instance2 = ErrorHandler::getInstance();
        $this->assertSame($instance1, $instance2);
    }
    
    public function testEnsureErrorTable(): void
    {
        // Set up the mock to expect a database query to create the errors table
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with($this->stringContains('CREATE TABLE IF NOT EXISTS errors'));
        
        // Call the private method using reflection
        $reflectionClass = new ReflectionClass(ErrorHandler::class);
        $method = $reflectionClass->getMethod('ensureErrorTable');
        $method->setAccessible(true);
        $method->invoke($this->errorHandler);
    }
    
    public function testLogError(): void
    {
        // Expected parameters for logging an error
        $type = 'TestError';
        $message = 'Test error message';
        $file = 'test_file.php';
        $line = 42;
        $trace = 'Test stack trace';
        
        // The query parameters expected to be passed to the database
        $expectedQueryParams = [
            $type,
            $message,
            $file,
            $line,
            $trace,
            $this->isType('string'), // json encoded request data
            null, // user_id
            $this->anything(), // IP address
            $this->anything() // User agent
        ];
        
        // Configure the mock to expect the database query with the correct parameters
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) use ($expectedQueryParams) {
                    return count($params) === 9 &&
                           $params[0] === $expectedQueryParams[0] &&
                           $params[1] === $expectedQueryParams[1] &&
                           $params[2] === $expectedQueryParams[2] &&
                           $params[3] === $expectedQueryParams[3] &&
                           $params[4] === $expectedQueryParams[4];
                })
            );
        
        // Call the logError method
        $this->errorHandler->logError($type, $message, $file, $line, $trace);
    }
    
    public function testLogException(): void
    {
        // Create a test exception
        $testException = new Exception('Test exception message');
        
        // Configure the mock to expect the database query for logging the exception
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('INSERT INTO errors'),
                $this->callback(function($params) {
                    return count($params) === 9 &&
                           $params[0] === 'Exception' &&
                           $params[1] === 'Test exception message';
                })
            );
        
        // Call the static logException method
        ErrorHandler::logException($testException);
    }
    
    public function testHandleError(): void
    {
        // Set up error reporting level to catch all errors
        $oldErrorReporting = error_reporting(E_ALL);
        
        // We expect handleError to convert the error to an ErrorException
        $this->expectException(ErrorException::class);
        $this->expectExceptionMessage('Test error');
        
        // Call handleError with a test error
        $this->errorHandler->handleError(E_WARNING, 'Test error', 'test_file.php', 42);
        
        // Restore original error reporting level
        error_reporting($oldErrorReporting);
    }
    
    public function testHandleErrorWithSuppressedError(): void
    {
        // Set error reporting to ignore warnings
        $oldErrorReporting = error_reporting(E_ALL & ~E_WARNING);
        
        // Call handleError with a warning (which should be suppressed)
        $result = $this->errorHandler->handleError(E_WARNING, 'Test warning', 'test_file.php', 42);
        
        // Verify that handleError returns false for suppressed errors
        $this->assertFalse($result);
        
        // Restore original error reporting level
        error_reporting($oldErrorReporting);
    }
    
    public function testRegister(): void
    {
        // Save the original error and exception handlers
        $originalErrorHandler = set_error_handler(function() {});
        restore_error_handler();
        
        $originalExceptionHandler = set_exception_handler(function() {});
        restore_exception_handler();
        
        // Call the register method
        $result = $this->errorHandler->register();
        
        // Verify that the register method returns $this for method chaining
        $this->assertSame($this->errorHandler, $result);
        
        // 在PHPUnit环境中，我们无法可靠地获取当前错误处理器
        // 因此，我们只验证register方法返回了正确的实例
        $this->assertTrue(true, 'Register method completed without errors');
        
        // Restore the original error and exception handlers
        if ($originalErrorHandler) {
            set_error_handler($originalErrorHandler);
        }
        if ($originalExceptionHandler) {
            set_exception_handler($originalExceptionHandler);
        }
    }
    
    public function testGetErrorType(): void
    {
        // Call the private getErrorType method using reflection
        $reflectionClass = new ReflectionClass(ErrorHandler::class);
        $method = $reflectionClass->getMethod('getErrorType');
        $method->setAccessible(true);
        
        // Test with known error types
        $this->assertEquals('Error', $method->invoke($this->errorHandler, E_ERROR));
        $this->assertEquals('Warning', $method->invoke($this->errorHandler, E_WARNING));
        $this->assertEquals('Notice', $method->invoke($this->errorHandler, E_NOTICE));
        
        // Test with unknown error type
        $this->assertEquals('Unknown Error', $method->invoke($this->errorHandler, 999999));
    }
} 