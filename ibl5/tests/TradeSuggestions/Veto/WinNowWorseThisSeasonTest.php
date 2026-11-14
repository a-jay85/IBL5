<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\TeamMode;
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
}
