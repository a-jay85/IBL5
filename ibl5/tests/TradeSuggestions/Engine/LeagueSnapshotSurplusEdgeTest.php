<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\SlotNetTallies;
use TradeSuggestions\Engine\SlotRankState;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\Valuation\ContractValue;
use TradeSuggestions\Valuation\FairSalaryTable;

/**
 * `LeagueSnapshot::surplusBySeason` on hand-built pool ranks and fair-salary
 * tables, so the best-slot choice and every skip condition is pinned.
 */
final class LeagueSnapshotSurplusEdgeTest extends TestCase
{
    private const int PID = 101;

    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league(4);
    }

    /**
     * @param array<string, array<int, int>> $pool slot => pid => net
     */
    private static function state(array $pool): SlotRankState
    {
        return SlotRankState::fromTallies(
            SlotNetTallies::fromArray(['offset' => 0, 'pool' => $pool, 'starters' => [], 'bench' => []]),
            [],
        );
    }

    /**
     * Tier-1 medians: PG 1000, SG 2000, SF 3000, PF 4000, C 5000.
     */
    private static function fairTable(): FairSalaryTable
    {
        $ranks = [];
        $salaries = [];
        foreach (SlotMapper::SLOTS as $i => $slot) {
            $pid = 800 + $i;
            $ranks[$slot] = [$pid => 1];
            $salaries[$pid] = 1000 * ($i + 1);
        }

        return FairSalaryTable::build(0, $ranks, $salaries);
    }

    /**
     * @param array<string, array<int, int>> $pool
     * @param array<int, FairSalaryTable> $fair
     * @param array<int, array<int, ProjectedSeason>>|null $projections
     */
    private static function snapshot(array $pool, array $fair, ?array $projections = null): LeagueSnapshot
    {
        $s = self::league();

        return new LeagueSnapshot(...[
            ...get_object_vars($s),
            'slotRanks' => [0 => self::state($pool)],
            'fairSalary' => $fair,
            'projections' => $projections ?? $s->projections,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private static function surplusAtFair(int $fair): array
    {
        return ContractValue::surplusBySeason([0 => self::league()->projections[self::PID][0]->salary], [0 => $fair]);
    }

    public function testBestSlotIsTheFirstSlotHoldingTheBestPoolRank(): void
    {
        $pid = self::PID;
        $s = self::snapshot(
            [
                'PG' => [900 => 9, $pid => 5],
                'SG' => [$pid => 9, 900 => 5],
                'SF' => [$pid => 9, 900 => 5],
                'PF' => [900 => 9, 901 => 8, $pid => 5],
            ],
            [0 => self::fairTable()],
        );

        $expected = self::surplusAtFair(2000);
        self::assertNotSame($expected, self::surplusAtFair(3000), 'guard: SG and SF fair salaries price differently');
        self::assertNotSame($expected, self::surplusAtFair(4000), 'guard: SG and PF fair salaries price differently');
        self::assertSame($expected, $s->surplusBySeason($pid));
    }

    public function testSeasonMissingAtAnOffsetLeavesItOut(): void
    {
        $projections = self::league()->projections;
        unset($projections[self::PID][0]);
        $s = self::snapshot(['PG' => [self::PID => 1]], [0 => self::fairTable()], $projections);

        self::assertSame(ContractValue::surplusBySeason([], []), self::withoutWarnings(static fn (): array => $s->surplusBySeason(self::PID)));
    }

    public function testMissingFairSalaryTableLeavesTheOffsetOut(): void
    {
        $s = self::snapshot(['PG' => [self::PID => 1]], []);

        self::assertSame(ContractValue::surplusBySeason([], []), self::withoutWarnings(static fn (): array => $s->surplusBySeason(self::PID)));
    }

    /**
     * @param callable(): array<int, int> $run
     * @return array<int, int>
     */
    private static function withoutWarnings(callable $run): array
    {
        set_error_handler(static function (int $no, string $message): never {
            throw new \ErrorException($message, 0, $no);
        });
        try {
            return $run();
        } finally {
            restore_error_handler();
        }
    }
}
