<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use mysqli;

/**
 * Integration tests for MariaDB/MySQL database connectivity
 * 
 * These tests verify:
 * - Connection establishment with mysqli
 * - Character encoding configuration (utf8mb4)
 * - Basic query execution
 * 
 * Tests run only in CI environment with real MariaDB database
 */
class DatabaseConnectionTest extends TestCase
{
    private ?mysqli $db = null;
    
    protected function setUp(): void
    {
        // Skip tests if not in CI environment with database
        if (!getenv('DB_HOST')) {
            $this->markTestSkipped('Integration tests only run in CI with database');
        }
        
        $this->db = new mysqli(
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: 'root',
            getenv('DB_NAME') ?: 'ibl5_test',
            (int)(getenv('DB_PORT') ?: 3306)
        );
        
        if ($this->db->connect_error) {
            $this->fail('Database connection failed: ' . $this->db->connect_error);
        }
    }
    
    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->close();
        }
    }
    
    public function testMysqliConnectionEstablishment(): void
    {
        $this->assertInstanceOf(mysqli::class, $this->db);
        $this->assertNull($this->db->connect_error, 'Connection error should be null');
        $this->assertGreaterThan(0, $this->db->thread_id, 'Connection should have a thread ID');
    }
    
    public function testCharacterEncodingUtf8mb4(): void
    {
        // Set character encoding
        $result = $this->db->set_charset('utf8mb4');
        $this->assertTrue($result, 'Failed to set utf8mb4 charset');
        
        // Verify character set was applied
        $charset = $this->db->character_set_name();
        $this->assertEquals('utf8mb4', $charset);
    }
    
    public function testDatabaseVersion(): void
    {
        $version = $this->db->get_server_info();
        $this->assertNotEmpty($version);
        $this->assertStringContainsString('MariaDB', $version);
    }
    
    public function testBasicQueryExecution(): void
    {
        // Test simple SELECT query
        $result = $this->db->query('SELECT 1 + 1 as result');
        $this->assertNotFalse($result, 'Query should succeed');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        
        $row = $result->fetch_assoc();
        $this->assertEquals(2, $row['result']);
        
        $result->free();
    }
    
    public function testDatabaseTableExists(): void
    {
        // Verify schema was loaded by checking for key table
        $result = $this->db->query("SHOW TABLES LIKE 'ibl_plr'");
        $this->assertNotFalse($result);
        $this->assertEquals(1, $result->num_rows, 'ibl_plr table should exist');
        
        $result->free();
    }
    
    public function testTableCount(): void
    {
        // Verify substantial number of tables were created
        $result = $this->db->query(
            "SELECT COUNT(*) as table_count 
             FROM information_schema.TABLES 
             WHERE TABLE_SCHEMA = DATABASE()"
        );
        
        $this->assertNotFalse($result);
        $row = $result->fetch_assoc();
        $this->assertGreaterThan(100, (int)$row['table_count'], 'Should have 100+ tables from schema.sql');
        
        $result->free();
    }
    
    public function testTransactionSupport(): void
    {
        // Test transaction capabilities
        $this->assertTrue($this->db->autocommit(false), 'Should disable autocommit');
        $this->assertTrue($this->db->begin_transaction(), 'Should start transaction');
        $this->assertTrue($this->db->rollback(), 'Should rollback transaction');
        $this->assertTrue($this->db->autocommit(true), 'Should re-enable autocommit');
    }
    
    public function testConnectionPersistence(): void
    {
        $threadId1 = $this->db->thread_id;
        
        // Execute a query
        $result = $this->db->query('SELECT 1');
        $this->assertNotFalse($result);
        $result->free();
        
        // Thread ID should remain the same (connection persists)
        $threadId2 = $this->db->thread_id;
        $this->assertEquals($threadId1, $threadId2, 'Connection thread ID should not change');
    }
}
