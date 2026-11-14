<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\RatingSet;
use TradeSuggestions\Valuation\ContractValue;

final class ContractValueTest extends TestCase
{
    public function testSurplusIsFairMinusSalary(): void
    {
        self::assertSame(200, ContractValue::surplusAt(500, 300));
        self::assertSame(-200, ContractValue::surplusAt(300, 500));
        self::assertSame(
            [0 => 200, 1 => -50, 2 => 0, 3 => 0],
            ContractValue::surplusBySeason([0 => 300, 1 => 150, 3 => 0], [0 => 500, 1 => 100, 2 => 400]),
        );
    }

    public function testSurplusBySeasonTreatsMissingOffsetsAsEmpty(): void
    {
        self::assertSame(
            [0 => -500, 1 => 0, 2 => 0, 3 => 0],
            ContractValue::surplusBySeason([0 => 500], [1 => 300]),
        );
    }

    public function testRaiseAtCurrentSeasonIgnoresThePriorContractYear(): void
    {
        self::assertSame(0, ContractValue::raiseAt(self::player([1 => 100, 2 => 200], 2), 0));
    }

    public function testSurplusIsZeroWhenOffTheBooks(): void
    {
        self::assertSame(0, ContractValue::surplusAt(500, 0));
        self::assertSame(0, ContractValue::surplusAt(500, -1));
        self::assertSame(499, ContractValue::surplusAt(500, 1));
    }

    public function testRaisesReadFromSalaryYearSteps(): void
    {
        $player = self::player([2 => 100, 3 => 120, 4 => 150], 2);

        self::assertSame([1 => 20, 2 => 30, 3 => 0], ContractValue::upcomingRaises($player));
        self::assertSame(0, ContractValue::raiseAt($player, 0));
        self::assertSame(0, ContractValue::raiseAt($player, -1));
    }

    public function testRaiseIsZeroPastContractEndAndBeyondSalaryYr6(): void
    {
        self::assertSame(
            [1 => 20, 2 => 0, 3 => 0],
            ContractValue::upcomingRaises(self::player([5 => 200, 6 => 220], 5)),
        );
        self::assertSame(
            [1 => 0, 2 => 0, 3 => 0],
            ContractValue::upcomingRaises(self::player([6 => 220], 6)),
        );
        self::assertSame(
            0,
            ContractValue::raiseAt(self::player([1 => 0, 2 => 300], 1), 1),
        );
    }

    public function testNegativeSurplusIgnoresCurrentSeasonAndPositiveSeasons(): void
    {
        self::assertSame(
            250,
            ContractValue::negativeSurplusBeyondCurrent([0 => -900, 1 => -100, 2 => 250, 3 => -150]),
        );
    }

    public function testTrulyBadRequiresStrictlyMoreThanFloor(): void
    {
        self::assertFalse(ContractValue::isTrulyBadLongMoney([0 => -999, 1 => -300]));
        self::assertTrue(ContractValue::isTrulyBadLongMoney([0 => 0, 1 => -301]));
    }

    public function testAverageRemainingSalaryOverOnBooksYears(): void
    {
        $player = self::player([1 => 9000, 2 => 9000, 3 => 100, 4 => 200, 5 => 300, 6 => 0], 3);

        self::assertEqualsWithDelta(200.0, ContractValue::averageRemainingSalary($player), 1e-9);
        self::assertEqualsWithDelta(
            600.0,
            ContractValue::averageRemainingSalary(self::player([6 => 600, 7 => 9000], 6)),
            1e-9,
        );
    }

    public function testAverageRemainingSalaryIsZeroWithNoSalary(): void
    {
        self::assertSame(0.0, ContractValue::averageRemainingSalary(self::player([], 1)));
    }

    /**
     * @param array<int, int> $salaryByYear
     */
    private static function player(array $salaryByYear, int $effectiveCy): ProjectionPlayer
    {
        return new ProjectionPlayer(
            pid: 1,
            name: 'Test Player',
            pos: 'PG',
            tid: 1,
            age: 25,
            injuredDays: 0,
            exp: 3,
            talent: 50,
            bird: 0,
            draftRound: 1,
            draftYear: 2020,
            draftPickNo: 10,
            prefs: [],
            ratings: RatingSet::fromRow([]),
            depth: [],
            dcDepth: [],
            dcMinutes: 0,
            effectiveCy: $effectiveCy,
            salaryByYear: $salaryByYear,
            production: null,
            tradeable: true,
        );
    }
}
