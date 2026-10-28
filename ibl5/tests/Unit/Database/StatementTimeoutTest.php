<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Database\StatementTimeout;
use PHPUnit\Framework\TestCase;

class StatementTimeoutTest extends TestCase
{
    public function testResolveSecondsDefaultsToTenWhenUnset(): void
    {
        $this->assertSame(10, StatementTimeout::resolveSeconds(null));
    }

    public function testResolveSecondsDefaultsToTenWhenEmptyString(): void
    {
        $this->assertSame(10, StatementTimeout::resolveSeconds(''));
    }

    public function testResolveSecondsAcceptsZeroAsDisabled(): void
    {
        $this->assertSame(0, StatementTimeout::resolveSeconds(0));
    }

    public function testResolveSecondsAcceptsInRangeIntIncludingMax(): void
    {
        $this->assertSame(30, StatementTimeout::resolveSeconds(30));
        $this->assertSame(3600, StatementTimeout::resolveSeconds(3600));
    }

    public function testResolveSecondsRejectsNegativeAndOverMax(): void
    {
        $this->assertSame(10, StatementTimeout::resolveSeconds(-1));
        $this->assertSame(10, StatementTimeout::resolveSeconds(3601));
    }

    public function testResolveSecondsRejectsNumericString(): void
    {
        $this->assertSame(10, StatementTimeout::resolveSeconds('5'));
        $this->assertSame(10, StatementTimeout::resolveSeconds(5.0));
    }

    public function testApplyIssuesSetOnMysqliForWebSapi(): void
    {
        $mysqli = self::createMock(\mysqli::class);
        $mysqli->expects($this->once())
            ->method('query')
            ->with('SET SESSION max_statement_time = 10')
            ->willReturn(true);

        $this->assertTrue(StatementTimeout::apply($mysqli, null, 'litespeed'));
    }

    public function testApplyIssuesSetOnPdoForWebSapi(): void
    {
        $pdo = self::createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with('SET SESSION max_statement_time = 10')
            ->willReturn(0);

        $this->assertTrue(StatementTimeout::apply($pdo, null, 'apache2handler'));
    }

    public function testApplyTreatsCliServerAsWeb(): void
    {
        $mysqli = self::createMock(\mysqli::class);
        $mysqli->expects($this->once())
            ->method('query')
            ->willReturn(true);

        $this->assertTrue(StatementTimeout::apply($mysqli, null, 'cli-server'));
    }

    public function testApplySkipsCliSapi(): void
    {
        $mysqli = self::createMock(\mysqli::class);
        $mysqli->expects($this->never())->method('query');

        $this->assertFalse(StatementTimeout::apply($mysqli, null, 'cli'));
    }

    public function testApplySkipsWhenConfiguredZero(): void
    {
        $pdo = self::createMock(\PDO::class);
        $pdo->expects($this->never())->method('exec');

        $this->assertFalse(StatementTimeout::apply($pdo, 0, 'litespeed'));
    }

    public function testApplyFailsOpenWhenSetThrows(): void
    {
        $mysqli = self::createStub(\mysqli::class);
        $mysqli->method('query')
            ->willThrowException(new \mysqli_sql_exception('Unknown system variable', 1193));

        $this->assertFalse(StatementTimeout::apply($mysqli, null, 'litespeed'));
    }

    public function testDisableIssuesZeroSet(): void
    {
        $pdo = self::createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with('SET SESSION max_statement_time = 0')
            ->willReturn(0);

        $this->assertTrue(StatementTimeout::disable($pdo));
    }
}
