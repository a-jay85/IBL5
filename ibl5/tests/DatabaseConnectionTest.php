<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function testDatabaseConnectionSucceeds(): void
    {
        $isConnected = DatabaseConnection::testConnection();
        $this->assertTrue($isConnected, 'Failed to connect to local MAMP database');
    }

    public function testCanFetchDatabaseStatus(): void
    {
        $status = DatabaseConnection::getStatus();
        
        $this->assertTrue($status['connected']);
        $this->assertEquals('localhost', $status['host']);
        $this->assertEquals('iblhoops_ibl5', $status['database']);
        $this->assertNotEmpty($status['version']);
    }

    public function testCanQueryPlayers(): void
    {
        $query = "SELECT pid, name FROM ibl_plr LIMIT 1";
        $player = DatabaseConnection::fetchRow($query);
        
        $this->assertIsArray($player);
        $this->assertArrayHasKey('pid', $player);
        $this->assertArrayHasKey('name', $player);
    }

    public function testCanCountTables(): void
    {
        $count = DatabaseConnection::fetchValue(
            "SELECT COUNT(*) as table_count FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'iblhoops_ibl5'"
        );
        
        $this->assertNotEmpty($count);
        $this->assertGreaterThan(0, (int)$count);
    }

    public function testCanFetchMultipleRows(): void
    {
        $query = "SELECT pid, name FROM ibl_plr LIMIT 5";
        $players = DatabaseConnection::fetchAll($query);
        
        $this->assertIsArray($players);
        $this->assertLessThanOrEqual(5, count($players));
        
        if (count($players) > 0) {
            $this->assertArrayHasKey('pid', $players[0]);
            $this->assertArrayHasKey('name', $players[0]);
        }
    }
}
