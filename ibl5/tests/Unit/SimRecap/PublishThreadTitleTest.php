<?php

declare(strict_types=1);

namespace Tests\Unit\SimRecap;

use PHPUnit\Framework\TestCase;
use SimRecap\PublishThreadTitle;

final class PublishThreadTitleTest extends TestCase
{
    public function testBuildsSeasonSimTitle(): void
    {
        self::assertSame('2008-09, SIM #10', PublishThreadTitle::build(2009, 10, null));
    }

    public function testCenturyRolloverPadsEndYear(): void
    {
        self::assertSame('1999-00, SIM #3', PublishThreadTitle::build(2000, 3, null));
    }

    public function testOverrideWins(): void
    {
        self::assertSame('BRIDE WARS', PublishThreadTitle::build(2009, 10, '  BRIDE WARS '));
    }

    public function testBlankOverrideFallsBack(): void
    {
        self::assertSame('2008-09, SIM #10', PublishThreadTitle::build(2009, 10, '   '));
    }

    public function testZeroSimNumberThrowsWithoutOverride(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PublishThreadTitle::build(2009, 0, null);
    }

    public function testSeasonEndingYearForDate(): void
    {
        self::assertSame(2009, PublishThreadTitle::seasonEndingYearForDate('2008-09-01'));
        self::assertSame(2009, PublishThreadTitle::seasonEndingYearForDate('2009-08-31'));
        self::assertSame(2009, PublishThreadTitle::seasonEndingYearForDate('2009-06-30'));
        self::assertSame(2009, PublishThreadTitle::seasonEndingYearForDate('2008-10-15'));
        self::assertSame(2009, PublishThreadTitle::seasonEndingYearForDate('2008-12-31'));
    }

    public function testFirstSimOfSeasonBuildsTitle(): void
    {
        self::assertSame('2008-09, SIM #1', PublishThreadTitle::build(2009, 1, null));
    }
}
