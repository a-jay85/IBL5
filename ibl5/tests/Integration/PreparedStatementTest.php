<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use mysqli;
use mysqli_stmt;

/**
 * Integration tests for mysqli prepared statements
 * 
 * These tests verify:
 * - Parameter binding for all types (i=integer, s=string, d=double, b=blob)
 * - NULL value handling
 * - LIKE query patterns with wildcards
 * - Date parameter binding
 * - Multiple parameter bindings
 * - Statement reuse and execution
 * 
 * Tests run only in CI environment with real MariaDB database
 */
class PreparedStatementTest extends TestCase
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
        
        $this->db->set_charset('utf8mb4');
        
        // Create test table for prepared statement tests
        $this->db->query('DROP TABLE IF EXISTS test_prepared_statements');
        $this->db->query('
            CREATE TABLE test_prepared_statements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                int_value INT,
                string_value VARCHAR(255),
                double_value DOUBLE,
                blob_value BLOB,
                date_value DATE,
                nullable_value VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ');
    }
    
    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->query('DROP TABLE IF EXISTS test_prepared_statements');
            $this->db->close();
        }
    }
    
    public function testIntegerParameterBinding(): void
    {
        // Insert with integer parameter
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (int_value) VALUES (?)');
        $this->assertInstanceOf(mysqli_stmt::class, $stmt);
        
        $intValue = 42;
        $stmt->bind_param('i', $intValue);
        $this->assertTrue($stmt->execute());
        $this->assertEquals(1, $stmt->affected_rows);
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify with SELECT
        $stmt = $this->db->prepare('SELECT int_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEquals(42, $row['int_value']);
        
        $stmt->close();
    }
    
    public function testStringParameterBinding(): void
    {
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (string_value) VALUES (?)');
        
        $stringValue = "Test String with 'quotes' and \"double quotes\"";
        $stmt->bind_param('s', $stringValue);
        $this->assertTrue($stmt->execute());
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify
        $stmt = $this->db->prepare('SELECT string_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEquals($stringValue, $row['string_value']);
        
        $stmt->close();
    }
    
    public function testDoubleParameterBinding(): void
    {
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (double_value) VALUES (?)');
        
        $doubleValue = 3.14159265359;
        $stmt->bind_param('d', $doubleValue);
        $this->assertTrue($stmt->execute());
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify
        $stmt = $this->db->prepare('SELECT double_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEqualsWithDelta($doubleValue, (float)$row['double_value'], 0.0001);
        
        $stmt->close();
    }
    
    public function testBlobParameterBinding(): void
    {
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (blob_value) VALUES (?)');
        
        // Binary data
        $blobValue = pack('H*', '48656c6c6f20576f726c64'); // "Hello World" in hex
        $stmt->bind_param('b', $blobValue);
        $stmt->send_long_data(0, $blobValue);
        $this->assertTrue($stmt->execute());
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify
        $stmt = $this->db->prepare('SELECT blob_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEquals($blobValue, $row['blob_value']);
        
        $stmt->close();
    }
    
    public function testDateParameterBinding(): void
    {
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (date_value) VALUES (?)');
        
        $dateValue = '2025-12-14';
        $stmt->bind_param('s', $dateValue);
        $this->assertTrue($stmt->execute());
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify
        $stmt = $this->db->prepare('SELECT date_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEquals($dateValue, $row['date_value']);
        
        $stmt->close();
    }
    
    public function testNullParameterHandling(): void
    {
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (nullable_value) VALUES (?)');
        
        $nullValue = null;
        $stmt->bind_param('s', $nullValue);
        $this->assertTrue($stmt->execute());
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify NULL was stored
        $stmt = $this->db->prepare('SELECT nullable_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertNull($row['nullable_value']);
        
        $stmt->close();
    }
    
    public function testMultipleParameterBindings(): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO test_prepared_statements 
            (int_value, string_value, double_value, date_value) 
            VALUES (?, ?, ?, ?)
        ');
        
        $intValue = 100;
        $stringValue = 'Multiple params';
        $doubleValue = 99.99;
        $dateValue = '2025-01-01';
        
        $stmt->bind_param('isds', $intValue, $stringValue, $doubleValue, $dateValue);
        $this->assertTrue($stmt->execute());
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Verify all values
        $stmt = $this->db->prepare('
            SELECT int_value, string_value, double_value, date_value 
            FROM test_prepared_statements 
            WHERE id = ?
        ');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $this->assertEquals($intValue, $row['int_value']);
        $this->assertEquals($stringValue, $row['string_value']);
        $this->assertEqualsWithDelta($doubleValue, (float)$row['double_value'], 0.01);
        $this->assertEquals($dateValue, $row['date_value']);
        
        $stmt->close();
    }
    
    public function testLikeQueryWithWildcards(): void
    {
        // Insert test data
        $testStrings = ['Apple', 'Application', 'Banana', 'Appliance'];
        foreach ($testStrings as $str) {
            $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (string_value) VALUES (?)');
            $stmt->bind_param('s', $str);
            $stmt->execute();
            $stmt->close();
        }
        
        // Test LIKE with wildcard
        $stmt = $this->db->prepare('
            SELECT string_value 
            FROM test_prepared_statements 
            WHERE string_value LIKE ? 
            ORDER BY string_value
        ');
        
        $pattern = 'Appl%';
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $matches = [];
        while ($row = $result->fetch_assoc()) {
            $matches[] = $row['string_value'];
        }
        
        $this->assertCount(3, $matches);
        $this->assertEquals(['Apple', 'Appliance', 'Application'], $matches);
        
        $stmt->close();
    }
    
    public function testStatementReuse(): void
    {
        // Prepare statement once
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (int_value) VALUES (?)');
        
        // Execute multiple times with different values
        for ($i = 1; $i <= 5; $i++) {
            $value = $i * 10;
            $stmt->bind_param('i', $value);
            $this->assertTrue($stmt->execute());
            $this->assertEquals(1, $stmt->affected_rows);
        }
        
        $stmt->close();
        
        // Verify all insertions
        $result = $this->db->query('
            SELECT COUNT(*) as count 
            FROM test_prepared_statements 
            WHERE int_value IN (10, 20, 30, 40, 50)
        ');
        $row = $result->fetch_assoc();
        $this->assertEquals(5, $row['count']);
    }
    
    public function testUpdateWithPreparedStatement(): void
    {
        // Insert initial value
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (string_value) VALUES (?)');
        $initialValue = 'Initial';
        $stmt->bind_param('s', $initialValue);
        $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Update using prepared statement
        $stmt = $this->db->prepare('UPDATE test_prepared_statements SET string_value = ? WHERE id = ?');
        $newValue = 'Updated';
        $stmt->bind_param('si', $newValue, $insertId);
        $this->assertTrue($stmt->execute());
        $this->assertEquals(1, $stmt->affected_rows);
        $stmt->close();
        
        // Verify update
        $stmt = $this->db->prepare('SELECT string_value FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEquals($newValue, $row['string_value']);
        $stmt->close();
    }
    
    public function testDeleteWithPreparedStatement(): void
    {
        // Insert value
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (int_value) VALUES (?)');
        $value = 999;
        $stmt->bind_param('i', $value);
        $stmt->execute();
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        // Delete using prepared statement
        $stmt = $this->db->prepare('DELETE FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $this->assertTrue($stmt->execute());
        $this->assertEquals(1, $stmt->affected_rows);
        $stmt->close();
        
        // Verify deletion
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM test_prepared_statements WHERE id = ?');
        $stmt->bind_param('i', $insertId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $this->assertEquals(0, $row['count']);
        $stmt->close();
    }
    
    public function testSqlInjectionPrevention(): void
    {
        // Insert legitimate data
        $stmt = $this->db->prepare('INSERT INTO test_prepared_statements (string_value) VALUES (?)');
        $legitimateValue = 'Legitimate Data';
        $stmt->bind_param('s', $legitimateValue);
        $stmt->execute();
        $stmt->close();
        
        // Attempt SQL injection (should be treated as literal string)
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM test_prepared_statements WHERE string_value = ?');
        $injectionAttempt = "' OR '1'='1";
        $stmt->bind_param('s', $injectionAttempt);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        // Should return 0 because the injection string doesn't match any real data
        $this->assertEquals(0, $row['count'], 'Prepared statements should prevent SQL injection');
        
        $stmt->close();
    }
}
