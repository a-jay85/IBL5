<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\TeamProfile;

final class ProfileDeltaTest extends TestCase
{
    public function testFromStatesRecordsBeforeAndAfterPayrollRankAndPayroll(): void
    {
        $before = self::state(payroll: 1000, payrollRank: 7);
        $after = self::state(payroll: 1500, payrollRank: 12);

        $delta = ProfileDelta::fromStates($before, $after);

        self::assertSame(['before' => 7, 'after' => 12], $delta->payrollRank);
        self::assertSame(['before' => 1000, 'after' => 1500], $delta->payroll);
    }

    public function testMovedDimensionsKeepKeyOrderForEqualMoves(): void
    {
        [$a, $b, $c, $d] = ProfileDimension::KEYS;
        $delta = new ProfileDelta(1, 0, [
            $a => ['before' => 10, 'after' => 12],
            $b => ['before' => 10, 'after' => 8],
            $c => ['before' => 5, 'after' => 5],
            $d => ['before' => 10, 'after' => 12],
        ], [], ['before' => 1, 'after' => 1], ['before' => 0, 'after' => 0]);

        self::assertSame([$a, $b, $d], $delta->movedDimensions());
    }

    public function testMovedDimensionsLargestFirstWithTiesInKeyOrder(): void
    {
        [$a, $b, $c, $d] = ProfileDimension::KEYS;
        $delta = new ProfileDelta(1, 0, [
            $a => ['before' => 10, 'after' => 11],
            $b => ['before' => 10, 'after' => 14],
            $c => ['before' => 10, 'after' => 11],
            $d => ['before' => 14, 'after' => 10],
        ], [], ['before' => 1, 'after' => 1], ['before' => 0, 'after' => 0]);

        self::assertSame([$b, $d, $a, $c], $delta->movedDimensions());
    }

    public function testFromStatesRecordsDimensionAndStarterRanksOfBothStates(): void
    {
        $keys = ProfileDimension::KEYS;
        $beforeRanks = [];
        $afterRanks = [];
        foreach ($keys as $i => $key) {
            $beforeRanks[$key] = $i % 28 + 1;
            $afterRanks[$key] = ($i + 5) % 28 + 1;
        }
        // A dimension ranked on one side only is left out.
        unset($afterRanks[$keys[0]]);
        $before = self::state(payroll: 1000, payrollRank: 7, profileRanks: $beforeRanks, starterRank: ['PG' => 3, 'SG' => 8]);
        $after = self::state(payroll: 1500, payrollRank: 12, profileRanks: $afterRanks, starterRank: ['PG' => 5, 'C' => 9]);

        $delta = ProfileDelta::fromStates($before, $after);

        $expected = [];
        foreach (array_slice($keys, 1) as $key) {
            $expected[$key] = ['before' => $beforeRanks[$key], 'after' => $afterRanks[$key]];
        }
        self::assertSame($expected, $delta->dimensionRanks);
        self::assertSame(
            ['PG' => ['before' => 3, 'after' => 5], 'SG' => ['before' => 8, 'after' => null], 'SF' => ['before' => null, 'after' => null], 'PF' => ['before' => null, 'after' => null], 'C' => ['before' => null, 'after' => 9]],
            $delta->starterRanks,
        );
    }

    /**
     * @param array<string, int>|null $profileRanks
     * @param array<string, int>|null $starterRank
     */
    private static function state(int $payroll, int $payrollRank, ?array $profileRanks = null, ?array $starterRank = null): TeamState
    {
        $base = TeamStateRebuilder::before(SnapshotFactory::league(4, 8), 1, 0);
        $profile = $profileRanks === null ? $base->profile : new TeamProfile($base->tid, $base->offset, [], $profileRanks);

        return new TeamState(
            $base->tid,
            $base->offset,
            $base->lineup,
            $starterRank ?? $base->starterRank,
            $base->bestBenchRank,
            $profile,
            $base->positionStrength,
            $payroll,
            $payrollRank,
            $base->rosterCount,
        );
    }
}
