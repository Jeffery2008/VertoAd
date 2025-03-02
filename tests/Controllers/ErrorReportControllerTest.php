<?php

use PHPUnit\Framework\TestCase;
use App\Controllers\ErrorReportController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

class ErrorReportControllerTest extends TestCase
{
    private $controller;
    private $mockDb;
    private $mockRequest;
    private $mockResponse;
    
    protected function setUp(): void
    {
        // Create mock objects
        $this->mockDb = $this->createMock(Database::class);
        $this->mockRequest = $this->createMock(Request::class);
        $this->mockResponse = $this->createMock(Response::class);
        
        // Create the controller
        $this->controller = new ErrorReportController();
        
        // Use reflection to set the mock objects
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($this->controller, $this->mockDb);
        
        $requestProperty = $reflectionClass->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($this->controller, $this->mockRequest);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($this->controller, $this->mockResponse);
        
        // Mock parent::__construct to bypass session startup
        $parentConstructorMethod = $reflectionClass->getMethod('__construct');
        $parentConstructorMethod->setAccessible(true);
        
        // Define constants needed for tests
        if (!defined('PHPUNIT_RUNNING')) {
            define('PHPUNIT_RUNNING', true);
        }
    }
    
    public function testDashboard(): void
    {
        // Create a mock of PDOStatement for database results
        $mockStatement = $this->createMock(PDOStatement::class);
        
        // Set up mock responses for all queries used in the dashboard method
        
        // Total errors
        $mockStatement->expects($this->at(0))
            ->method('fetch')
            ->willReturn(['count' => 100]);
            
        // Unresolved errors
        $mockStatement->expects($this->at(1))
            ->method('fetch')
            ->willReturn(['count' => 25]);
            
        // Errors by type
        $mockStatement->expects($this->at(2))
            ->method('fetchAll')
            ->willReturn([
                ['type' => 'Error', 'count' => 30],
                ['type' => 'Warning', 'count' => 50],
                ['type' => 'Notice', 'count' => 20]
            ]);
            
        // Last 24 hours errors
        $mockStatement->expects($this->at(3))
            ->method('fetch')
            ->willReturn(['count' => 15]);
            
        // Daily statistics
        $mockStatement->expects($this->at(4))
            ->method('fetchAll')
            ->willReturn([
                ['date' => '2023-01-01', 'count' => 10],
                ['date' => '2023-01-02', 'count' => 15],
                ['date' => '2023-01-03', 'count' => 20]
            ]);
            
        // Common messages
        $mockStatement->expects($this->at(5))
            ->method('fetchAll')
            ->willReturn([
                ['message' => 'Division by zero', 'count' => 15],
                ['message' => 'Undefined variable', 'count' => 10]
            ]);
            
        // Recent errors
        $mockStatement->expects($this->at(6))
            ->method('fetchAll')
            ->willReturn([
                ['id' => 1, 'type' => 'Error', 'message' => 'Test error', 'file' => 'test.php', 'line' => 10, 'created_at' => '2023-01-03 12:00:00', 'status' => 'new'],
                ['id' => 2, 'type' => 'Warning', 'message' => 'Test warning', 'file' => 'test.php', 'line' => 20, 'created_at' => '2023-01-03 11:00:00', 'status' => 'resolved']
            ]);
        
        // Set up the database mock to return our statement mock
        $this->mockDb->expects($this->any())
            ->method('query')
            ->willReturn($mockStatement);
        
        // Expect the requireRole method to be called with 'admin'
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        $requireRoleMethod = $reflectionClass->getMethod('requireRole');
        $requireRoleMethod->setAccessible(true);
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
        
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect the view method to be called with the correct parameters
        $this->mockResponse->expects($this->once())
            ->method('renderView')
            ->with(
                $this->equalTo('admin/error_dashboard'),
                $this->callback(function($params) {
                    // Just check that the dashboard key exists
                    return isset($params['dashboard']);
                })
            );
            
        // Call the dashboard method
        $mockController->dashboard();
    }
    
    public function testList(): void
    {
        // Mock GET parameters
        $_GET = [
            'page' => '2',
            'status' => 'new',
            'type' => 'Error',
            'search' => 'test'
        ];
        
        // Create a mock of PDOStatement for database results
        $mockStatement = $this->createMock(PDOStatement::class);
        
        // Configure mockStatement for the count query
        $mockStatement->expects($this->at(0))
            ->method('fetch')
            ->willReturn(['count' => 50]);
            
        // Configure mockStatement for the error list query
        $mockStatement->expects($this->at(1))
            ->method('fetchAll')
            ->willReturn([
                ['id' => 1, 'type' => 'Error', 'message' => 'Test error', 'file' => 'test.php', 'line' => 10, 'created_at' => '2023-01-03 12:00:00', 'status' => 'new'],
                ['id' => 2, 'type' => 'Error', 'message' => 'Another test error', 'file' => 'test.php', 'line' => 20, 'created_at' => '2023-01-03 11:00:00', 'status' => 'new']
            ]);
            
        // Configure mockStatement for the types query
        $mockStatement->expects($this->at(2))
            ->method('fetchAll')
            ->willReturn([
                ['type' => 'Error'],
                ['type' => 'Warning'],
                ['type' => 'Notice']
            ]);
        
        // Set up the database mock to return our statement mock
        $this->mockDb->expects($this->any())
            ->method('query')
            ->willReturn($mockStatement);
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect the view method to be called with the correct parameters
        $this->mockResponse->expects($this->once())
            ->method('renderView')
            ->with(
                $this->equalTo('admin/error_list'),
                $this->callback(function($params) {
                    return isset($params['errors']) &&
                           isset($params['types']) &&
                           isset($params['status']) &&
                           isset($params['type']) &&
                           isset($params['search']) &&
                           isset($params['page']) &&
                           isset($params['perPage']) &&
                           isset($params['total']) &&
                           isset($params['totalPages']);
                })
            );
            
        // Create a mock Request object
        $mockRequest = $this->createMock(Request::class);
            
        // Call the list method with the mock request
        $mockController->list($mockRequest);
    }
    
    public function testView(): void
    {
        // Mock error ID
        $errorId = 1;
        
        // Create a mock of PDOStatement for database results
        $mockStatement = $this->createMock(PDOStatement::class);
        
        // Configure mockStatement to return a sample error
        $mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'id' => 1,
                'type' => 'Error',
                'message' => 'Test error',
                'file' => 'test.php',
                'line' => 10,
                'created_at' => '2023-01-03 12:00:00',
                'status' => 'new',
                'trace' => 'Test stack trace',
                'request_data' => json_encode(['GET' => [], 'POST' => ['test' => 'value']]),
                'user_id' => 5,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit Test',
                'notes' => 'Test notes'
            ]);
        
        // Set up the database mock to return our statement mock
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('SELECT * FROM errors WHERE id = ?'),
                $this->equalTo([$errorId])
            )
            ->willReturn($mockStatement);
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect the view method to be called with the correct parameters
        $this->mockResponse->expects($this->once())
            ->method('renderView')
            ->with(
                $this->equalTo('admin/error_detail'),
                $this->callback(function($params) {
                    return isset($params['error']) &&
                           $params['error']['id'] === 1 &&
                           $params['error']['type'] === 'Error';
                })
            );
            
        // Call the viewError method
        $mockController->viewError($errorId);
    }
    
    public function testViewNonExistentError(): void
    {
        // Mock error ID
        $errorId = 999;
        
        // Create a mock of PDOStatement for database results
        $mockStatement = $this->createMock(PDOStatement::class);
        
        // Configure mockStatement to return null (no error found)
        $mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn(false);
        
        // Set up the database mock to return our statement mock
        $this->mockDb->expects($this->once())
            ->method('query')
            ->willReturn($mockStatement);
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect redirect to be called
        $this->mockResponse->expects($this->once())
            ->method('redirect')
            ->with('/admin/errors');
            
        // Call the viewError method
        $mockController->viewError($errorId);
    }
    
    public function testUpdateStatus(): void
    {
        // Mock error ID
        $errorId = 1;
        
        // Mock POST data
        $postData = [
            'status' => 'resolved',
            'notes' => 'Issue fixed in version 1.2.3'
        ];
        
        // Mock request method and body
        $this->mockRequest->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
            
        $this->mockRequest->expects($this->once())
            ->method('getBody')
            ->willReturn($postData);
        
        // Set up the database mock to expect the update query
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('UPDATE errors SET status = ?'),
                $this->equalTo(['resolved', 'Issue fixed in version 1.2.3', $errorId])
            );
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $requestProperty = $reflectionClass->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($mockController, $this->mockRequest);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect redirect to be called
        $this->mockResponse->expects($this->once())
            ->method('redirect')
            ->with('/admin/errors/view/' . $errorId);
            
        // Call the updateStatus method
        $mockController->updateStatus($errorId);
    }
    
    public function testGetStats(): void
    {
        // Create a mock of PDOStatement for database results
        $mockStatement = $this->createMock(PDOStatement::class);
        
        // Configure mockStatement for hourly data
        $mockStatement->expects($this->at(0))
            ->method('fetchAll')
            ->willReturn([
                ['hour' => '2023-01-03 10:00:00', 'count' => 5],
                ['hour' => '2023-01-03 11:00:00', 'count' => 8],
                ['hour' => '2023-01-03 12:00:00', 'count' => 3]
            ]);
            
        // Configure mockStatement for status data
        $mockStatement->expects($this->at(1))
            ->method('fetchAll')
            ->willReturn([
                ['status' => 'new', 'count' => 25],
                ['status' => 'in_progress', 'count' => 10],
                ['status' => 'resolved', 'count' => 50],
                ['status' => 'ignored', 'count' => 15]
            ]);
        
        // Set up the database mock to return our statement mock
        $this->mockDb->expects($this->any())
            ->method('query')
            ->willReturn($mockStatement);
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect the json method to be called with the correct data
        $this->mockResponse->expects($this->once())
            ->method('json')
            ->with($this->callback(function($data) {
                return isset($data['hourly']) &&
                       isset($data['byStatus']) &&
                       count($data['hourly']) === 3 &&
                       count($data['byStatus']) === 4;
            }));
            
        // Call the getStats method
        $mockController->getStats();
    }
    
    public function testBulkUpdate(): void
    {
        // Mock POST data
        $postData = [
            'ids' => [1, 2, 3],
            'status' => 'resolved'
        ];
        
        // Mock request method and body
        $this->mockRequest->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');
            
        $this->mockRequest->expects($this->once())
            ->method('getBody')
            ->willReturn($postData);
        
        // Set up the database mock to expect the update query
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('UPDATE errors SET status = ? WHERE id IN (?,?,?)'),
                $this->equalTo(['resolved', 1, 2, 3])
            );
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $requestProperty = $reflectionClass->getProperty('request');
        $requestProperty->setAccessible(true);
        $requestProperty->setValue($mockController, $this->mockRequest);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect redirect to be called
        $this->mockResponse->expects($this->once())
            ->method('redirect')
            ->with('/admin/errors');
            
        // Call the bulkUpdate method
        $mockController->bulkUpdate();
    }
    
    public function testViewError(): void
    {
        // Mock error ID
        $errorId = 1;
        
        // Create a mock of PDOStatement for database results
        $mockStatement = $this->createMock(PDOStatement::class);
        
        // Configure mockStatement to return a sample error
        $mockStatement->expects($this->once())
            ->method('fetch')
            ->willReturn([
                'id' => 1,
                'type' => 'Error',
                'message' => 'Test error',
                'file' => 'test.php',
                'line' => 10,
                'created_at' => '2023-01-03 12:00:00',
                'status' => 'new',
                'trace' => 'Test stack trace',
                'request_data' => json_encode(['GET' => [], 'POST' => ['test' => 'value']]),
                'user_id' => 5,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'PHPUnit Test',
                'notes' => 'Test notes'
            ]);
        
        // Set up the database mock to return our statement mock
        $this->mockDb->expects($this->once())
            ->method('query')
            ->with(
                $this->stringContains('SELECT * FROM errors WHERE id = ?'),
                $this->equalTo([$errorId])
            )
            ->willReturn($mockStatement);
        
        // Mock the requireRole method
        $mockController = $this->getMockBuilder(ErrorReportController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['requireRole'])
            ->getMock();
            
        $mockController->expects($this->once())
            ->method('requireRole')
            ->with('admin');
            
        // Set mocks on the mock controller
        $reflectionClass = new ReflectionClass(ErrorReportController::class);
        
        $dbProperty = $reflectionClass->getProperty('db');
        $dbProperty->setAccessible(true);
        $dbProperty->setValue($mockController, $this->mockDb);
        
        $responseProperty = $reflectionClass->getProperty('response');
        $responseProperty->setAccessible(true);
        $responseProperty->setValue($mockController, $this->mockResponse);
        
        // Expect the view method to be called with the correct parameters
        $this->mockResponse->expects($this->once())
            ->method('renderView')
            ->with(
                $this->equalTo('admin/error_detail'),
                $this->callback(function($params) {
                    return isset($params['error']) &&
                           $params['error']['id'] === 1 &&
                           $params['error']['type'] === 'Error';
                })
            );
            
        // Call the viewError method
        $mockController->viewError($errorId);
    }
} 