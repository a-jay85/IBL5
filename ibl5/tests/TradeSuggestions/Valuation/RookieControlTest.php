<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Valuation\RookieControl;

final class RookieControlTest extends TestCase
{
    public function testRoundOneIsThreeYearsPlusOptionYearFour(): void
    {
        self::assertSame(3, RookieControl::contractYears(1));
        self::assertSame(4, RookieControl::optionSalaryYear(1));
        self::assertSame(4, RookieControl::controlYearsLeft(1, 0));
    }

    public function testRoundTwoIsTwoYearsPlusOptionYearThree(): void
    {
        self::assertSame(2, RookieControl::contractYears(2));
        self::assertSame(3, RookieControl::optionSalaryYear(2));
        self::assertSame(3, RookieControl::controlYearsLeft(2, 0));
        self::assertSame(1, RookieControl::controlYearsLeft(2, 2));
    }

    public function testUndraftedAndRoundThreeHaveNoControl(): void
    {
        foreach ([0, 3, -1] as $round) {
            self::assertFalse(RookieControl::isRookieScale($round, 0));
            self::assertSame(0, RookieControl::contractYears($round));
            self::assertSame(0, RookieControl::controlYearsLeft($round, 0));
            self::assertNull(RookieControl::optionSalaryYear($round));
            self::assertFalse(RookieControl::optionYearIsNext($round, 0));
        }
    }

    public function testExperienceBoundaryAtThreeAndFour(): void
    {
        self::assertTrue(RookieControl::isRookieScale(1, 3));
        self::assertSame(1, RookieControl::controlYearsLeft(1, 3));
        self::assertTrue(RookieControl::optionYearIsNext(1, 3));

        self::assertFalse(RookieControl::isRookieScale(1, 4));
        self::assertSame(0, RookieControl::controlYearsLeft(1, 4));
        self::assertFalse(RookieControl::optionYearIsNext(1, 4));
        self::assertFalse(RookieControl::optionYearIsNext(1, 2));
    }

    public function testRoundTwoAtExperienceThreeHasNoControlLeft(): void
    {
        self::assertTrue(RookieControl::isRookieScale(2, 3));
        self::assertSame(0, RookieControl::controlYearsLeft(2, 3));
        self::assertFalse(RookieControl::optionYearIsNext(2, 3));
    }

    public function testControlYearsNeverNegative(): void
    {
        self::assertSame(0, RookieControl::controlYearsLeft(1, 9));
        self::assertSame(0, RookieControl::controlYearsLeft(2, 9));
    }

    public function testAssetPlacesCountsOnlyCheapControlledSeasons(): void
    {
        self::assertEqualsWithDelta(
            0.75,
            RookieControl::assetPlaces(3, [0 => 50, 1 => -10, 2 => 40, 3 => 90]),
            1e-12,
        );
        self::assertEqualsWithDelta(
            0.5,
            RookieControl::assetPlaces(3, [0 => 50, 1 => 0, 2 => -1, 3 => 90]),
            1e-12,
        );
        self::assertEqualsWithDelta(
            0.25,
            RookieControl::assetPlaces(1, [0 => 1, 1 => 90, 2 => 90, 3 => 90]),
            1e-12,
        );
    }

    public function testAssetPlacesIsZeroWithNoControl(): void
    {
        self::assertSame(0.0, RookieControl::assetPlaces(0, [0 => 50, 1 => 50, 2 => 50, 3 => 50]));
    }

    public function testAssetPlacesIgnoresSeasonsBeyondHorizon(): void
    {
        self::assertEqualsWithDelta(
            2.0,
            RookieControl::assetPlaces(6, [0 => 10, 1 => 10, 2 => 10, 3 => 10]),
            1e-12,
        );
        self::assertEqualsWithDelta(
            1.75,
            RookieControl::assetPlaces(4, [0 => 10, 1 => 10, 2 => 10, 3 => 10]),
            1e-12,
        );
    }
}
