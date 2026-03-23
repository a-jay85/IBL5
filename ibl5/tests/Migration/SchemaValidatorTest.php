<?php

declare(strict_types=1);

namespace Tests\Migration;

use Migration\SchemaAssertion;
use Migration\SchemaValidationResult;
use Migration\SchemaValidator;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

final class SchemaValidatorTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testEmptyAssertionsAlwaysPasses(): void
    {
        $validator = new SchemaValidator($this->mockDb);
        $result = $validator->validate([]);

        $this->assertTrue($result->passed);
        $this->assertSame([], $result->missing);
    }

    public function testAllColumnsFoundPasses(): void
    {
        $this->mockDb->onQuery('INFORMATION_SCHEMA', [
            ['TABLE_NAME' => 'ibl_plr', 'COLUMN_NAME' => 'dc_canPlayInGame'],
            ['TABLE_NAME' => 'ibl_plr', 'COLUMN_NAME' => 'pid'],
        ]);

        $validator = new SchemaValidator($this->mockDb);
        $result = $validator->validate([
            new SchemaAssertion('ibl_plr', 'dc_canPlayInGame'),
            new SchemaAssertion('ibl_plr', 'pid'),
        ]);

        $this->assertTrue($result->passed);
        $this->assertSame([], $result->missing);
    }

    public function testMissingColumnFails(): void
    {
        $this->mockDb->onQuery('INFORMATION_SCHEMA', [
            ['TABLE_NAME' => 'ibl_plr', 'COLUMN_NAME' => 'pid'],
        ]);

        $validator = new SchemaValidator($this->mockDb);
        $result = $validator->validate([
            new SchemaAssertion('ibl_plr', 'dc_canPlayInGame'),
            new SchemaAssertion('ibl_plr', 'pid'),
        ]);

        $this->assertFalse($result->passed);
        $this->assertSame(['ibl_plr.dc_canPlayInGame'], $result->missing);
    }

    public function testMultipleMissingColumns(): void
    {
        $this->mockDb->onQuery('INFORMATION_SCHEMA', []);

        $validator = new SchemaValidator($this->mockDb);
        $result = $validator->validate([
            new SchemaAssertion('ibl_plr', 'dc_canPlayInGame'),
            new SchemaAssertion('ibl_trade_info', 'trade_from'),
        ]);

        $this->assertFalse($result->passed);
        $this->assertCount(2, $result->missing);
        $this->assertContains('ibl_plr.dc_canPlayInGame', $result->missing);
        $this->assertContains('ibl_trade_info.trade_from', $result->missing);
    }

    public function testPartialMatchReportsOnlyMissing(): void
    {
        $this->mockDb->onQuery('INFORMATION_SCHEMA', [
            ['TABLE_NAME' => 'ibl_plr', 'COLUMN_NAME' => 'pid'],
            ['TABLE_NAME' => 'ibl_team_info', 'COLUMN_NAME' => 'teamid'],
        ]);

        $validator = new SchemaValidator($this->mockDb);
        $result = $validator->validate([
            new SchemaAssertion('ibl_plr', 'pid'),
            new SchemaAssertion('ibl_plr', 'dc_canPlayInGame'),
            new SchemaAssertion('ibl_team_info', 'teamid'),
            new SchemaAssertion('ibl_trade_info', 'trade_from'),
        ]);

        $this->assertFalse($result->passed);
        $this->assertSame([
            'ibl_plr.dc_canPlayInGame',
            'ibl_trade_info.trade_from',
        ], $result->missing);
    }

    public function testSchemaAssertionToKey(): void
    {
        $assertion = new SchemaAssertion('ibl_plr', 'dc_canPlayInGame');
        $this->assertSame('ibl_plr.dc_canPlayInGame', $assertion->toKey());
    }

    public function testValidationResultProperties(): void
    {
        $passed = new SchemaValidationResult(passed: true, missing: []);
        $this->assertTrue($passed->passed);
        $this->assertSame([], $passed->missing);

        $failed = new SchemaValidationResult(passed: false, missing: ['ibl_plr.xyz']);
        $this->assertFalse($failed->passed);
        $this->assertSame(['ibl_plr.xyz'], $failed->missing);
    }
}
