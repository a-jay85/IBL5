<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\ProductionLine;
use TradeSuggestions\ProductionSource;

class ProductionLineTest extends TestCase
{
    public function testCurrentSeasonPointsCountThreesOnce(): void
    {
        $line = ProductionLine::fromCurrentRow(['stats_fgm' => 10, 'stats_3gm' => 4, 'stats_ftm' => 5, 'stats_orb' => 3, 'stats_drb' => 6]);

        self::assertSame(29, $line->points);
        self::assertSame(9, $line->rebounds);
        self::assertSame(ProductionSource::CurrentSeason, $line->source);
    }

    public function testChoosePrefersCurrentAtMinimumSample(): void
    {
        $chosen = ProductionLine::choose(self::current(400), self::hist(2000));

        self::assertNotNull($chosen);
        self::assertSame(ProductionSource::CurrentSeason, $chosen->source);
    }

    public function testChooseFallsBackToLastSeasonBelowSample(): void
    {
        $chosen = ProductionLine::choose(self::current(399), self::hist(2000));

        self::assertNotNull($chosen);
        self::assertSame(ProductionSource::LastSeason, $chosen->source);
        self::assertSame(2000, $chosen->minutes);
    }

    public function testChooseReturnsNullForRookieWithoutHistory(): void
    {
        self::assertNull(ProductionLine::choose(self::current(194), null));
        self::assertNull(ProductionLine::choose(self::current(194), self::hist(399)));
    }

    public function testPer36IsZeroWhenMinutesZero(): void
    {
        $line = ProductionLine::fromCurrentRow(['stats_fgm' => 3, 'stats_min' => 0, 'stats_gm' => 0]);

        self::assertSame(0.0, $line->pointsPer36());
        self::assertSame(0.0, $line->pointsPerGame());

        $hist = ProductionLine::fromHistRow(['minutes' => 720, 'games' => 20, 'pts' => 400, 'reb' => 100, 'ast' => 60]);
        self::assertSame(20.0, $hist->pointsPer36());
        self::assertSame(5.0, $hist->reboundsPer36());
        self::assertSame(3.0, $hist->assistsPer36());
        self::assertSame(20.0, $hist->pointsPerGame());
        self::assertSame(5.0, $hist->reboundsPerGame());
        self::assertSame(3.0, $hist->assistsPerGame());
    }

    private static function current(int $minutes): ProductionLine
    {
        return ProductionLine::fromCurrentRow(['stats_min' => $minutes, 'stats_gm' => 10]);
    }

    private static function hist(int $minutes): ProductionLine
    {
        return ProductionLine::fromHistRow(['minutes' => $minutes, 'games' => 60, 'pts' => 900]);
    }
}
