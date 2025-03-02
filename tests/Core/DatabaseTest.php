<?php

namespace Tests\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use PDO;

class DatabaseTest extends TestCase
{
    private $db;
    
    protected function setUp(): void
    {
        $this->db = new Database();
    }
    
    public function testConnection()
    {
        // 验证连接是否成功
        $connection = $this->db->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
    }
    
    public function testQuery()
    {
        // 测试简单查询
        $stmt = $this->db->query("SELECT 1 as test");
        $this->assertIsObject($stmt);
        
        $row = $stmt->fetch();
        $this->assertEquals(1, $row['test']);
    }
    
    public function testQueryWithParams()
    {
        // 测试带参数的查询
        $stmt = $this->db->query("SELECT ? as test", [42]);
        $this->assertIsObject($stmt);
        
        $row = $stmt->fetch();
        $this->assertEquals(42, $row['test']);
    }
    
    public function testInsertAndLastInsertId()
    {
        // 创建临时测试表
        $this->db->query('CREATE TEMPORARY TABLE test_insert (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255))');
        
        // 插入数据
        $this->db->query('INSERT INTO test_insert (name) VALUES (?)', ['Test name']);
        
        // 验证last insert id
        $lastId = $this->db->lastInsertId();
        $this->assertEquals(1, $lastId);
        
        // 验证数据已插入
        $stmt = $this->db->query('SELECT * FROM test_insert WHERE id = ?', [$lastId]);
        $row = $stmt->fetch();
        $this->assertEquals('Test name', $row['name']);
    }
    
    public function testTransaction()
    {
        // 创建临时测试表
        $this->db->query('CREATE TEMPORARY TABLE test_transaction (id INT AUTO_INCREMENT PRIMARY KEY, value INT)');
        
        // 开始事务
        $this->db->beginTransaction();
        
        try {
            // 插入数据
            $this->db->query('INSERT INTO test_transaction (value) VALUES (?)', [1]);
            $this->db->query('INSERT INTO test_transaction (value) VALUES (?)', [2]);
            
            // 提交事务
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        
        // 验证数据已插入
        $stmt = $this->db->query('SELECT COUNT(*) as count FROM test_transaction');
        $row = $stmt->fetch();
        $this->assertEquals(2, $row['count']);
    }
    
    public function testTransactionRollback()
    {
        // 创建临时测试表
        $this->db->query('CREATE TEMPORARY TABLE test_rollback (id INT AUTO_INCREMENT PRIMARY KEY, value INT)');
        
        // 开始事务
        $this->db->beginTransaction();
        
        // 插入第一条数据
        $this->db->query('INSERT INTO test_rollback (value) VALUES (?)', [1]);
        
        // 回滚事务
        $this->db->rollback();
        
        // 验证数据未插入
        $stmt = $this->db->query('SELECT COUNT(*) as count FROM test_rollback');
        $row = $stmt->fetch();
        $this->assertEquals(0, $row['count']);
    }
} 