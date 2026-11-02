<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Projection;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Projection\InjuryStatus;

final class InjuryStatusTest extends TestCase
{
    public function testZeroDaysIsHealthy(): void
    {
        self::assertSame(InjuryStatus::Healthy, InjuryStatus::fromDays(0, 40, true));
    }

    public function testOutsideRegularSeasonIsHealthyEvenWhenInjured(): void
    {
        self::assertSame(InjuryStatus::Healthy, InjuryStatus::fromDays(30, 82, false));
        self::assertSame(InjuryStatus::BackForPlayoffs, InjuryStatus::fromDays(30, 82, true));
    }

    public function testTenDaysIsBackSoonAndElevenIsNotWhenShareIsSmall(): void
    {
        self::assertSame(InjuryStatus::BackSoon, InjuryStatus::fromDays(10, 40, true));
        self::assertSame(InjuryStatus::BackForPlayoffs, InjuryStatus::fromDays(11, 40, true));
    }

    public function testFifteenPercentShareBoundary(): void
    {
        self::assertSame(InjuryStatus::BackSoon, InjuryStatus::fromDays(12, 80, true));
        self::assertSame(InjuryStatus::BackForPlayoffs, InjuryStatus::fromDays(13, 80, true));
    }

    public function testInjuredLongerThanGamesLeftIsOutForYear(): void
    {
        self::assertSame(InjuryStatus::OutForYear, InjuryStatus::fromDays(20, 20, true));
        self::assertSame(InjuryStatus::BackForPlayoffs, InjuryStatus::fromDays(20, 21, true));
    }

    public function testInjuredWithZeroGamesLeftIsOutForYear(): void
    {
        self::assertSame(InjuryStatus::OutForYear, InjuryStatus::fromDays(3, 0, true));
    }

    public function testOutForYearChecksBeforeBackSoon(): void
    {
        self::assertSame(InjuryStatus::OutForYear, InjuryStatus::fromDays(5, 3, true));
    }

    public function testAvailabilityWeightsMatchFrozenTable(): void
    {
        self::assertSame(1.0, InjuryStatus::Healthy->availabilityWeight());
        self::assertSame(0.9, InjuryStatus::BackSoon->availabilityWeight());
        self::assertSame(0.6, InjuryStatus::BackForPlayoffs->availabilityWeight());
        self::assertSame(0.0, InjuryStatus::OutForYear->availabilityWeight());
    }

    public function testPlayoffWeightRestoresBackForPlayoffsToFull(): void
    {
        self::assertSame(1.0, InjuryStatus::BackForPlayoffs->playoffWeight());
        self::assertSame(0.9, InjuryStatus::BackSoon->playoffWeight());
        self::assertSame(0.0, InjuryStatus::OutForYear->playoffWeight());
        self::assertSame(1.0, InjuryStatus::Healthy->playoffWeight());
    }
}
