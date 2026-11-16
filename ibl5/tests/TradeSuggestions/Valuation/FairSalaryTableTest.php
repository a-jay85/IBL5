<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Valuation\FairSalaryTable;

final class FairSalaryTableTest extends TestCase
{
    public function testTierBoundaryAtRanksFiveAndSix(): void
    {
        self::assertSame(1, FairSalaryTable::tierFor(1));
        self::assertSame(1, FairSalaryTable::tierFor(5));
        self::assertSame(2, FairSalaryTable::tierFor(6));
        self::assertSame(2, FairSalaryTable::tierFor(10));
        self::assertSame(3, FairSalaryTable::tierFor(11));
    }

    public function testTierForRankBelowOneThrows(): void
    {
        self::assertSame(1, FairSalaryTable::tierFor(1));
        $this->expectException(\InvalidArgumentException::class);
        FairSalaryTable::tierFor(0);
    }

    public function testMedianOfOddCount(): void
    {
        $table = self::tableForTier([100, 900, 300]);

        self::assertSame(300, $table->fairSalary('PG', 1));
    }

    public function testMedianOfEvenCountRoundsHalfUp(): void
    {
        self::assertSame(201, self::tableForTier([100, 301])->fairSalary('PG', 1));
        self::assertSame(200, self::tableForTier([100, 300])->fairSalary('PG', 1));
        self::assertSame(250, self::tableForTier([900, 100, 200, 300])->fairSalary('PG', 1));
    }

    public function testZeroSalaryIsNotADataPoint(): void
    {
        self::assertSame(500, self::tableForTier([0, 400, 600])->fairSalary('PG', 1));
        self::assertSame([], FairSalaryTable::build(0, ['PG' => [1 => 1]], [])->tiers('PG'));
    }

    public function testTierWithDataPricesAtItsOwnMedianOverAWorseTier(): void
    {
        $table = FairSalaryTable::build(0, ['PG' => [1 => 1, 2 => 6]], [1 => 900, 2 => 300]);

        self::assertSame(900, $table->fairSalary('PG', 1));
        self::assertSame(300, $table->fairSalary('PG', 2));
    }

    public function testEmptyTierFallsBackToWorseTierFirst(): void
    {
        $topEmpty = FairSalaryTable::build(0, ['PG' => [1 => 6]], [1 => 200]);
        self::assertSame(200, $topEmpty->fairSalary('PG', 1));

        $middleEmpty = FairSalaryTable::build(0, ['PG' => [1 => 6, 2 => 16]], [1 => 200, 2 => 50]);
        self::assertSame(50, $middleEmpty->fairSalary('PG', 3));

        $nearestWorse = FairSalaryTable::build(0, ['PG' => [1 => 11, 2 => 16]], [1 => 70, 2 => 50]);
        self::assertSame(70, $nearestWorse->fairSalary('PG', 1));

        $nearestBetter = FairSalaryTable::build(0, ['PG' => [1 => 1, 2 => 6]], [1 => 900, 2 => 200]);
        self::assertSame(200, $nearestBetter->fairSalary('PG', 4));
    }

    public function testNoDataAnywhereFallsBackToVeteranMinimum(): void
    {
        $table = FairSalaryTable::build(3, [], []);

        self::assertSame(35, $table->fairSalary('C', 1));
        self::assertSame(3, $table->offset());
        self::assertSame([], $table->tiers('C'));
    }

    public function testUnknownSlotThrows(): void
    {
        $table = FairSalaryTable::build(0, ['SF' => [1 => 1]], [1 => 100]);
        self::assertSame([1 => 100], $table->tiers('SF'));

        try {
            $table->fairSalary('XX', 1);
            self::fail('fairSalary accepted an unknown slot');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('XX', $e->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        FairSalaryTable::build(0, ['XX' => [1 => 1]], [1 => 100]);
    }

    /**
     * @param list<int> $salaries every salary lands in tier 1 at slot PG
     */
    private static function tableForTier(array $salaries): FairSalaryTable
    {
        $ranks = [];
        $byPid = [];
        foreach ($salaries as $i => $salary) {
            $ranks[$i + 1] = $i + 1;
            $byPid[$i + 1] = $salary;
        }

        return FairSalaryTable::build(0, ['PG' => $ranks], $byPid);
    }
}
