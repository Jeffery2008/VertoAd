<?php

namespace Tests\Models;

use PHPUnit\Framework\TestCase;
use App\Models\AdView;
use App\Models\Ad;
use App\Models\User;

class AdViewTest extends TestCase {
    private $adView;
    private $ad;
    private $user;
    private $testAdId;
    private $testPublisherId;
    
    protected function setUp(): void {
        $this->adView = new AdView();
        $this->ad = new Ad();
        $this->user = new User();
        
        // 生成唯一ID
        $uniqueId = uniqid();
        
        // Create test advertiser
        $advertiserId = $this->user->create(
            'advertiser',
            'testadvertiser' . $uniqueId,
            'advertiser' . $uniqueId . '@example.com',
            'password123'
        );
        
        // Create test publisher
        $this->testPublisherId = $this->user->create(
            'publisher',
            'testpublisher' . $uniqueId,
            'publisher' . $uniqueId . '@example.com',
            'password123'
        );
        
        // Create test ad
        $this->testAdId = $this->ad->create(
            $advertiserId,
            'Test Ad',
            '<div>Test content</div>',
            100.00,
            0.01
        );
        
        // Approve the ad
        $this->ad->approve($this->testAdId);
    }
    
    protected function tearDown(): void {
        // Clean up test data
        $this->adView->db->query('DELETE FROM ad_views');
        $this->ad->db->query('DELETE FROM ads');
        $this->user->db->query('DELETE FROM users');
        
        // 关闭数据库连接
        $this->adView->db->close();
        $this->ad->db->close();
        $this->user->db->close();
    }
    
    public function testRecordView() {
        $viewId = $this->adView->record(
            $this->testAdId,
            $this->testPublisherId,
            '127.0.0.1'
        );
        
        $this->assertIsInt($viewId);
        
        $view = $this->adView->getById($viewId);
        $this->assertEquals($this->testAdId, $view['ad_id']);
        $this->assertEquals($this->testPublisherId, $view['publisher_id']);
        $this->assertEquals('127.0.0.1', $view['viewer_ip']);
        $this->assertNotNull($view['viewed_at']);
    }
    
    public function testGetViewsByAd() {
        // Record views with different IPs
        for ($i = 0; $i < 3; $i++) {
            $this->adView->record(
                $this->testAdId,
                $this->testPublisherId,
                "127.0.0.$i"
            );
        }
        
        $views = $this->adView->getViewsByAd($this->testAdId);
        $this->assertCount(3, $views);
        
        foreach ($views as $view) {
            $this->assertEquals($this->testAdId, $view['ad_id']);
            $this->assertEquals($this->testPublisherId, $view['publisher_id']);
        }
    }
    
    public function testGetViewsByPublisher() {
        // Record views with different IPs
        for ($i = 0; $i < 3; $i++) {
            $this->adView->record(
                $this->testAdId,
                $this->testPublisherId,
                "127.0.0.$i"
            );
        }
        
        $views = $this->adView->getViewsByPublisher($this->testPublisherId);
        $this->assertCount(3, $views);
        
        foreach ($views as $view) {
            $this->assertEquals($this->testPublisherId, $view['publisher_id']);
        }
    }
    
    public function testGetViewStatistics() {
        // Record views with different IPs and timestamps
        $this->adView->db->query("
            INSERT INTO ad_views (ad_id, publisher_id, viewer_ip, cost, viewed_at)
            VALUES 
            (?, ?, '127.0.0.1', 0.01, DATE_SUB(NOW(), INTERVAL 2 DAY)),
            (?, ?, '127.0.0.2', 0.01, DATE_SUB(NOW(), INTERVAL 1 DAY)),
            (?, ?, '127.0.0.3', 0.01, NOW())",
            [
                $this->testAdId, $this->testPublisherId,
                $this->testAdId, $this->testPublisherId,
                $this->testAdId, $this->testPublisherId
            ]
        );
        
        // Test last 24 hours
        $stats = $this->adView->getViewStatistics($this->testAdId, 1);
        $this->assertEquals(1, $stats['total_views']);
        
        // Test last 48 hours
        $stats = $this->adView->getViewStatistics($this->testAdId, 2);
        $this->assertEquals(2, $stats['total_views']);
        
        // Test last 72 hours
        $stats = $this->adView->getViewStatistics($this->testAdId, 3);
        $this->assertEquals(3, $stats['total_views']);
    }
    
    public function testDuplicateViewPrevention() {
        // Record first view
        $this->adView->record(
            $this->testAdId,
            $this->testPublisherId,
            '127.0.0.1'
        );
        
        // Attempt to record duplicate view within 24 hours
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Duplicate view detected');
        
        $this->adView->record(
            $this->testAdId,
            $this->testPublisherId,
            '127.0.0.1'
        );
    }
    
    public function testInvalidAdView() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid ad or publisher');
        
        $this->adView->record(
            99999, // Non-existent ad ID
            $this->testPublisherId,
            '127.0.0.1'
        );
    }
} 