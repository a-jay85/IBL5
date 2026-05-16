<?php

declare(strict_types=1);

namespace Tests\Standings;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Standings\AggregateTiebreaker;

#[CoversClass(AggregateTiebreaker::class)]
final class AggregateTiebreakerTest extends TestCase
{
    public function testTwoTeamsClearWinner(): void
    {
        $h2h = [
            1 => [2 => ['wins' => 3, 'losses' => 1]],
            2 => [1 => ['wins' => 1, 'losses' => 3]],
        ];

        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [1, 2],
            static fn (int $tid, int $opp): array => $h2h[$tid][$opp] ?? ['wins' => 0, 'losses' => 0],
        );

        self::assertGreaterThan($pcts[2], $pcts[1]);
        self::assertEqualsWithDelta(0.75, $pcts[1], 0.001);
        self::assertEqualsWithDelta(0.25, $pcts[2], 0.001);
    }

    public function testTwoTeamsTied(): void
    {
        $h2h = [
            1 => [2 => ['wins' => 2, 'losses' => 2]],
            2 => [1 => ['wins' => 2, 'losses' => 2]],
        ];

        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [1, 2],
            static fn (int $tid, int $opp): array => $h2h[$tid][$opp] ?? ['wins' => 0, 'losses' => 0],
        );

        self::assertEqualsWithDelta(0.5, $pcts[1], 0.001);
        self::assertEqualsWithDelta(0.5, $pcts[2], 0.001);
    }

    public function testThreeTeamsCircularH2H(): void
    {
        $h2h = [
            1 => [2 => ['wins' => 3, 'losses' => 1], 3 => ['wins' => 1, 'losses' => 3]],
            2 => [1 => ['wins' => 1, 'losses' => 3], 3 => ['wins' => 3, 'losses' => 1]],
            3 => [1 => ['wins' => 3, 'losses' => 1], 2 => ['wins' => 1, 'losses' => 3]],
        ];

        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [1, 2, 3],
            static fn (int $tid, int $opp): array => $h2h[$tid][$opp] ?? ['wins' => 0, 'losses' => 0],
        );

        self::assertEqualsWithDelta(0.5, $pcts[1], 0.001);
        self::assertEqualsWithDelta(0.5, $pcts[2], 0.001);
        self::assertEqualsWithDelta(0.5, $pcts[3], 0.001);
    }

    public function testEmptyInput(): void
    {
        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [],
            static fn (int $tid, int $opp): array => ['wins' => 0, 'losses' => 0],
        );

        self::assertSame([], $pcts);
    }

    public function testSingleTeam(): void
    {
        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [5],
            static fn (int $tid, int $opp): array => ['wins' => 0, 'losses' => 0],
        );

        self::assertEqualsWithDelta(0.0, $pcts[5], 0.001);
    }

    public function testNoGamesPlayed(): void
    {
        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [1, 2],
            static fn (int $tid, int $opp): array => ['wins' => 0, 'losses' => 0],
        );

        self::assertEqualsWithDelta(0.0, $pcts[1], 0.001);
        self::assertEqualsWithDelta(0.0, $pcts[2], 0.001);
    }

    public function testSafeWinPctZeroDivision(): void
    {
        self::assertEqualsWithDelta(0.0, AggregateTiebreaker::safeWinPct(0, 0), 0.001);
    }

    public function testSafeWinPctNormal(): void
    {
        self::assertEqualsWithDelta(0.75, AggregateTiebreaker::safeWinPct(3, 1), 0.001);
    }

    public function testFlatWinsMatrixAdapter(): void
    {
        $winsMatrix = [
            1 => [2 => 3],
            2 => [1 => 1],
        ];

        $pcts = AggregateTiebreaker::computeAggregateH2HPcts(
            [1, 2],
            static fn (int $tid, int $opp): array => [
                'wins' => $winsMatrix[$tid][$opp] ?? 0,
                'losses' => $winsMatrix[$opp][$tid] ?? 0,
            ],
        );

        self::assertEqualsWithDelta(0.75, $pcts[1], 0.001);
        self::assertEqualsWithDelta(0.25, $pcts[2], 0.001);
    }
}
