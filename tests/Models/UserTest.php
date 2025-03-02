<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Models\User;
use Exception;

class UserTest extends TestCase
{
    private $user;
    
    protected function setUp(): void
    {
        $this->user = new User();
        // Clean up any existing test data
        $this->user->db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
    }
    
    protected function tearDown(): void
    {
        // Clean up test data
        $this->user->db->query('DELETE FROM users WHERE email LIKE ?', ['%@test.com']);
        
        // 关闭数据库连接
        $this->user->db->close();
    }
    
    public function testCreate()
    {
        // 使用唯一ID
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);
        
        $user = $this->user->getById($userId);
        $this->assertEquals('testuser' . $uniqueId, $user['username']);
        $this->assertEquals('test' . $uniqueId . '@test.com', $user['email']);
        $this->assertEquals('advertiser', $user['role']);
    }
    
    public function testCreateDuplicateUsername()
    {
        // 使用唯一ID
        $uniqueId = uniqid();
        $username = 'testuser' . $uniqueId;
        
        $this->user->create(
            'advertiser',
            $username,
            'test1' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Username already exists');
        
        $this->user->create(
            'advertiser',
            $username,
            'test2' . $uniqueId . '@test.com',
            'password123'
        );
    }
    
    public function testCreateDuplicateEmail()
    {
        $this->user->create(
            'advertiser',
            'testuser1',
            'test@test.com',
            'password123'
        );
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Email already exists');
        
        $this->user->create(
            'advertiser',
            'testuser2',
            'test@test.com',
            'password123'
        );
    }
    
    public function testAuthenticate()
    {
        $uniqueId = uniqid();
        $password = 'password123';
        
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            $password
        );
        
        $user = $this->user->authenticate('test' . $uniqueId . '@test.com', $password);
        $this->assertIsArray($user);
        $this->assertEquals($userId, $user['id']);
        
        // Test with wrong password
        $user = $this->user->authenticate('test' . $uniqueId . '@test.com', 'wrongpassword');
        $this->assertFalse($user);
        
        // Test with non-existent email
        $user = $this->user->authenticate('nonexistent@test.com', $password);
        $this->assertFalse($user);
    }
    
    public function testUpdate()
    {
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->user->update($userId, [
            'username' => 'updateduser' . $uniqueId,
            'email' => 'updated' . $uniqueId . '@test.com'
        ]);
        
        $user = $this->user->getById($userId);
        $this->assertEquals('updateduser' . $uniqueId, $user['username']);
        $this->assertEquals('updated' . $uniqueId . '@test.com', $user['email']);
    }
    
    public function testDelete()
    {
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->user->delete($userId);
        
        $user = $this->user->getById($userId);
        $this->assertFalse($user);
    }
    
    public function testFindByUsername()
    {
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $user = $this->user->findByUsername('testuser' . $uniqueId);
        $this->assertIsArray($user);
        $this->assertEquals($userId, $user['id']);
        
        $user = $this->user->findByUsername('nonexistent');
        $this->assertFalse($user);
    }
    
    public function testFindByEmail()
    {
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $user = $this->user->findByEmail('test' . $uniqueId . '@test.com');
        $this->assertIsArray($user);
        $this->assertEquals($userId, $user['id']);
        
        $user = $this->user->findByEmail('nonexistent@test.com');
        $this->assertFalse($user);
    }
    
    public function testAddBalance()
    {
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->user->addBalance($userId, 100.00);
        
        $user = $this->user->getById($userId);
        $this->assertEquals(100.00, $user['balance']);
        
        $this->user->addBalance($userId, 50.00);
        $user = $this->user->getById($userId);
        $this->assertEquals(150.00, $user['balance']);
    }
    
    public function testDeductBalance()
    {
        $uniqueId = uniqid();
        $userId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->user->addBalance($userId, 100.00);
        
        $this->user->deductBalance($userId, 30.00);
        $user = $this->user->getById($userId);
        $this->assertEquals(70.00, $user['balance']);
        
        // Test insufficient balance
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Insufficient balance');
        $this->user->deductBalance($userId, 100.00);
    }
    
    public function testGetByRole()
    {
        $uniqueId = uniqid();
        $this->user->create(
            'advertiser',
            'testuser1' . $uniqueId,
            'test1' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->user->create(
            'publisher',
            'testuser2' . $uniqueId,
            'test2' . $uniqueId . '@test.com',
            'password123'
        );
        
        $advertisers = $this->user->getByRole('advertiser');
        $this->assertIsArray($advertisers);
        $this->assertGreaterThanOrEqual(1, count($advertisers));
        $this->assertEquals('advertiser', $advertisers[0]['role']);
        
        $publishers = $this->user->getByRole('publisher');
        $this->assertIsArray($publishers);
        $this->assertGreaterThanOrEqual(1, count($publishers));
        $this->assertEquals('publisher', $publishers[0]['role']);
    }
    
    public function testGetAll()
    {
        $uniqueId = uniqid();
        $this->user->create(
            'advertiser',
            'testuser1' . $uniqueId,
            'test1' . $uniqueId . '@test.com',
            'password123'
        );
        
        $this->user->create(
            'publisher',
            'testuser2' . $uniqueId,
            'test2' . $uniqueId . '@test.com',
            'password123'
        );
        
        $users = $this->user->getAll();
        $this->assertIsArray($users);
        $this->assertGreaterThanOrEqual(2, count($users));
    }
} 