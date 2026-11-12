<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\Profile\ProfileDimension;

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

    private static function state(int $payroll, int $payrollRank): TeamState
    {
        $base = TeamStateRebuilder::before(SnapshotFactory::league(), 1, 0);

        return new TeamState(
            $base->tid,
            $base->offset,
            $base->lineup,
            $base->starterRank,
            $base->bestBenchRank,
            $base->profile,
            $base->positionStrength,
            $payroll,
            $payrollRank,
            $base->rosterCount,
        );
    }
}
