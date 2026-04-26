<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\IblSeasonDateHelper;

final class IblSeasonDateHelperTest extends TestCase
{
    public function testSeptemberDateReturnsNextYear(): void
    {
        $this->assertSame(2026, IblSeasonDateHelper::dateToSeasonEndingYear('2025-09-10'));
    }

    public function testOctoberDateReturnsNextYear(): void
    {
        $this->assertSame(2026, IblSeasonDateHelper::dateToSeasonEndingYear('2025-10-15'));
    }

    public function testNovemberDateReturnsNextYear(): void
    {
        $this->assertSame(2026, IblSeasonDateHelper::dateToSeasonEndingYear('2025-11-01'));
    }

    public function testDecemberDateReturnsNextYear(): void
    {
        $this->assertSame(2026, IblSeasonDateHelper::dateToSeasonEndingYear('2025-12-20'));
    }

    public function testJanuaryDateReturnsSameYear(): void
    {
        $this->assertSame(2026, IblSeasonDateHelper::dateToSeasonEndingYear('2026-01-16'));
    }

    public function testJuneDateReturnsSameYear(): void
    {
        $this->assertSame(2026, IblSeasonDateHelper::dateToSeasonEndingYear('2026-06-10'));
    }

    public function testInvalidDateReturnsZero(): void
    {
        $this->assertSame(0, IblSeasonDateHelper::dateToSeasonEndingYear('not-a-date'));
    }

    public function testEmptyDateReturnsZero(): void
    {
        $this->assertSame(0, IblSeasonDateHelper::dateToSeasonEndingYear(''));
    }

    public function testSeptemberDateReturnsPreseason(): void
    {
        $this->assertSame('preseason', IblSeasonDateHelper::getGameTypeFromDate('2025-09-10'));
    }

    public function testOctoberDateReturnsHeat(): void
    {
        $this->assertSame('heat', IblSeasonDateHelper::getGameTypeFromDate('2025-10-15'));
    }

    public function testJuneDateReturnsPlayoffs(): void
    {
        $this->assertSame('playoffs', IblSeasonDateHelper::getGameTypeFromDate('2026-06-10'));
    }

    public function testJanuaryDateReturnsRegularSeason(): void
    {
        $this->assertSame('regularSeason', IblSeasonDateHelper::getGameTypeFromDate('2026-01-16'));
    }

    public function testMarchDateReturnsRegularSeason(): void
    {
        $this->assertSame('regularSeason', IblSeasonDateHelper::getGameTypeFromDate('2026-03-05'));
    }

    public function testInvalidDateReturnsRegularSeason(): void
    {
        $this->assertSame('regularSeason', IblSeasonDateHelper::getGameTypeFromDate('not-a-date'));
    }

    public function testEmptyDateReturnsRegularSeason(): void
    {
        $this->assertSame('regularSeason', IblSeasonDateHelper::getGameTypeFromDate(''));
    }
}
