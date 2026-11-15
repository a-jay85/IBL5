<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\SideView;
use TradeSuggestions\Veto\VetoSet;
use TradeSuggestions\Veto\WinNowWorseThisSeason;

final class WinNowWorseThisSeasonTest extends TestCase
{
    public function testFiresWhenStarterRankWorsens(): void
    {
        self::assertTrue((new WinNowWorseThisSeason())->fires(VetoDealFactory::winNowWorse()['deal']));
    }

    public function testFiresWhenBestBenchWorsens(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::WinNow, TeamMode::Middle],
            [],
            [7 => ['age' => 25, 'base' => 40.0]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        )['deal'];

        self::assertSame($deal->user->before[0]->starterRank, $deal->user->after[0]->starterRank);
        self::assertGreaterThan($deal->user->before[0]->bestBenchRank['SF'], $deal->user->after[0]->bestBenchRank['SF']);
        self::assertTrue((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testFiresWhenInjuryAdjustedRotationDrops(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::WinNow, TeamMode::Middle],
            [],
            [7 => ['age' => 25, 'base' => 50.5, 'injured' => 200]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        )['deal'];

        $user = $deal->user;
        self::assertSame(0.0, $user->availabilityByPid[VetoDealFactory::pid(2, 7)]);
        foreach ($user->before[0]->starterRank as $slot => $rank) {
            self::assertLessThanOrEqual($rank ?? PHP_INT_MAX, $user->after[0]->starterRank[$slot] ?? PHP_INT_MAX);
        }
        foreach ($user->before[0]->bestBenchRank as $slot => $rank) {
            self::assertLessThanOrEqual($rank ?? PHP_INT_MAX, $user->after[0]->bestBenchRank[$slot] ?? PHP_INT_MAX);
        }
        self::assertTrue((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testRebuildSideNeverFires(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Rebuild, TeamMode::Middle],
            [2 => ['age' => 31, 'base' => 85.0]],
            [2 => ['age' => 25, 'base' => 40.0]],
            [VetoDealFactory::uid(2)],
            [],
            [VetoDealFactory::pid(2, 2)],
            [],
        )['deal'];

        self::assertFalse((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testMiddleSideNeverFires(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [2 => ['age' => 31, 'base' => 85.0]],
            [2 => ['age' => 25, 'base' => 40.0]],
            [VetoDealFactory::uid(2)],
            [],
            [VetoDealFactory::pid(2, 2)],
            [],
        )['deal'];

        self::assertFalse((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testUnchangedRanksDoNotFire(): void
    {
        $deal = VetoDealFactory::quiet(TeamMode::Middle, TeamMode::WinNow)['deal'];

        self::assertFalse((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testOtherVetoesQuietOnFixture(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::winNowWorse()['deal']);

        self::assertSame(['WIN_NOW_WORSE_THIS_SEASON'], $report->firedIds());
    }

    public function testRotationPlayerMissingFromAvailabilityCountsAsFullyAvailable(): void
    {
        // Before: 1 (1.0) + 2 (absent, counts 1.0) = 2.0. After: 1 (1.0) + 3 (0.0) = 1.0.
        $deal = self::rotationDeal(['PG' => [1, 2]], ['PG' => [1, 3]], [1 => 1.0, 3 => 0.0]);

        self::assertTrue((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testEqualRotationCountDoesNotFire(): void
    {
        $deal = self::rotationDeal(['PG' => [1, 2]], ['PG' => [3, 4]], [1 => 1.0, 2 => 0.5, 3 => 0.5, 4 => 1.0]);

        self::assertFalse((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testRotationDropJustPastEpsilonFires(): void
    {
        // The drop is a hair over ROTATION_EPSILON; a sum that starts above zero rounds it away.
        $deal = self::rotationDeal(['PG' => [1]], ['PG' => [2]], [1 => 0.5, 2 => 0.4999999989999999]);

        self::assertTrue((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testRotationDropOfExactlyEpsilonDoesNotFire(): void
    {
        $deal = self::rotationDeal(['PG' => [1]], ['PG' => [2]], [1 => 1.0, 2 => 1.0 - WinNowWorseThisSeason::ROTATION_EPSILON / 2]);

        self::assertFalse((new WinNowWorseThisSeason())->fires($deal));
    }

    public function testPidInTwoSlotsCountsOnce(): void
    {
        // Before: pids 1 and 2 (2.0), though 2 sits in two slots. After: 3 and 4 (2.0).
        $deal = self::rotationDeal(['PG' => [1, 2], 'SG' => [2]], ['PG' => [3], 'SG' => [4]], []);

        self::assertFalse((new WinNowWorseThisSeason())->fires($deal));
    }

    /**
     * The quiet deal (ranks unchanged) with the win-now partner's offset-0 rotations scripted.
     *
     * @param array<string, list<int>> $beforeRotation
     * @param array<string, list<int>> $afterRotation
     * @param array<int, float> $availability
     */
    private static function rotationDeal(array $beforeRotation, array $afterRotation, array $availability): DealView
    {
        $deal = VetoDealFactory::quiet(TeamMode::Middle, TeamMode::WinNow)['deal'];
        $user = $deal->partner;
        $with = static function (TeamState $state, array $rotation): TeamState {
            $l = $state->lineup;

            return new TeamState(
                $state->tid,
                $state->offset,
                new SeasonLineup($l->tid, $l->offset, $l->depth, $rotation, $l->players, $l->chartReliable, $l->minutesWeight),
                $state->starterRank,
                $state->bestBenchRank,
                $state->profile,
                $state->positionStrength,
                $state->payroll,
                $state->payrollRank,
                $state->rosterCount,
            );
        };
        $before = $user->before;
        $after = $user->after;
        $before[0] = $with($before[0], $beforeRotation);
        $after[0] = $with($after[0], $afterRotation);

        return new DealView(
            $deal->user,
            new SideView(
                $user->tid,
                $user->mode,
                $user->sent,
                $user->received,
                $user->sentPicks,
                $user->receivedPicks,
                $before,
                $after,
                $user->gain,
                $user->situation,
                $user->surplusByPid,
                $user->delta,
                $user->pickWorthById,
                $availability,
                $user->injuryByPid,
            ),
            $deal->candidate,
            $deal->shapeId,
        );
    }
}
