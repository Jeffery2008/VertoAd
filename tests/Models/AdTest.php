<?php

use PHPUnit\Framework\TestCase;
use App\Models\Ad;
use App\Models\User;

class AdTest extends TestCase {
    private $ad;
    private $user;
    private $testUserId;
    
    protected function setUp(): void {
        $this->ad = new Ad();
        $this->user = new User();
        
        // 生成唯一ID
        $uniqueId = uniqid();
        
        // Create a test user
        $this->testUserId = $this->user->create(
            'advertiser',
            'testuser' . $uniqueId,
            'test' . $uniqueId . '@example.com',
            'password123'
        );
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->ad->db->query('DELETE FROM ad_views');
        $this->ad->db->query('DELETE FROM ads');
        $this->ad->db->query('DELETE FROM users');
        
        // 关闭数据库连接
        $this->ad->db->close();
    }
    
    public function testCreateAd() {
        $adId = $this->ad->create(
            $this->testUserId,
            'Test Ad',
            '<div>Test content</div>',
            100.00,
            0.01
        );
        
        $this->assertIsInt($adId);
        
        $createdAd = $this->ad->findById($adId);
        $this->assertEquals('Test Ad', $createdAd['title']);
        $this->assertEquals('<div>Test content</div>', $createdAd['content']);
        $this->assertEquals(100.00, $createdAd['budget']);
        $this->assertEquals(100.00, $createdAd['remaining_budget']);
        $this->assertEquals(0.01, $createdAd['cost_per_view']);
        $this->assertEquals('draft', $createdAd['status']);
    }
    
    public function testUpdateAd() {
        $adId = $this->ad->create(
            $this->testUserId,
            'Original Title',
            '<div>Original content</div>',
            100.00,
            0.01
        );
        
        $this->ad->update($adId, [
            'title' => 'Updated Title',
            'content' => '<div>Updated content</div>'
        ]);
        
        $updatedAd = $this->ad->findById($adId);
        $this->assertEquals('Updated Title', $updatedAd['title']);
        $this->assertEquals('<div>Updated content</div>', $updatedAd['content']);
    }
    
    public function testDeleteAd() {
        $adId = $this->ad->create(
            $this->testUserId,
            'Test Ad',
            '<div>Test content</div>',
            100.00,
            0.01
        );
        
        $this->ad->delete($adId);
        
        $deletedAd = $this->ad->findById($adId);
        $this->assertFalse($deletedAd);
    }
    
    public function testAdWorkflow() {
        // Create ad
        $adId = $this->ad->create(
            $this->testUserId,
            'Test Ad',
            '<div>Test content</div>',
            100.00,
            0.01
        );
        
        // Submit for review
        $this->ad->submit($adId);
        $pendingAd = $this->ad->findById($adId);
        $this->assertEquals('pending', $pendingAd['status']);
        
        // Approve ad
        $this->ad->approve($adId);
        $approvedAd = $this->ad->findById($adId);
        $this->assertEquals('approved', $approvedAd['status']);
        
        // Reject ad
        $this->ad->reject($adId);
        $rejectedAd = $this->ad->findById($adId);
        $this->assertEquals('rejected', $rejectedAd['status']);
    }
    
    public function testTrackView() {
        // Create publisher
        $uniqueId = uniqid();
        $publisherId = $this->user->create(
            'publisher',
            'publisher' . $uniqueId,
            'publisher' . $uniqueId . '@example.com',
            'password123'
        );
        
        // Create ad
        $adId = $this->ad->create(
            $this->testUserId,
            'Test Ad',
            '<div>Test content</div>',
            100.00,
            0.01
        );
        
        // Approve ad
        $this->ad->approve($adId);
        
        // Track view
        $this->ad->trackView($adId, $publisherId, '127.0.0.1');
        
        // Check remaining budget
        $updatedAd = $this->ad->findById($adId);
        $this->assertEquals(99.99, $updatedAd['remaining_budget']);
        
        // Check view statistics
        $stats = $this->ad->getStats($adId);
        $this->assertEquals(1, $stats['total_views']);
        $this->assertEquals(0.01, $stats['total_cost']);
    }
    
    public function testGetActiveAds() {
        // Create multiple ads with different statuses
        $adId1 = $this->ad->create($this->testUserId, 'Ad 1', '<div>Content 1</div>', 100.00, 0.01);
        $adId2 = $this->ad->create($this->testUserId, 'Ad 2', '<div>Content 2</div>', 100.00, 0.01);
        $adId3 = $this->ad->create($this->testUserId, 'Ad 3', '<div>Content 3</div>', 100.00, 0.01);
        
        // Set different statuses
        $this->ad->approve($adId1);
        $this->ad->reject($adId2);
        $this->ad->approve($adId3);
        
        // Get active ads
        $activeAds = $this->ad->getActiveAds();
        
        // Should only get approved ads
        $this->assertCount(2, $activeAds);
        $this->assertEquals('approved', $activeAds[0]['status']);
        $this->assertEquals('approved', $activeAds[1]['status']);
    }
    
    public function testInsufficientBudget() {
        // Create ad with exactly enough budget for one view
        $adId = $this->ad->create(
            $this->testUserId,
            'Test Ad',
            '<div>Test content</div>',
            0.01, // Exactly enough for one view
            0.01
        );
        
        $uniqueId = uniqid();
        $publisherId = $this->user->create(
            'publisher',
            'publisher' . $uniqueId,
            'publisher' . $uniqueId . '@example.com',
            'password123'
        );
        
        $this->ad->approve($adId);
        
        // First view should succeed
        $this->ad->trackView($adId, $publisherId, '127.0.0.1');
        
        // Second view should fail due to insufficient budget
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient budget or invalid ad');
        
        $this->ad->trackView($adId, $publisherId, '127.0.0.2');
    }
} 