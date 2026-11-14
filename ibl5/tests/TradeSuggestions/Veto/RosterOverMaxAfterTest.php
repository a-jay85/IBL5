<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\RosterOverMaxAfter;
use TradeSuggestions\Veto\VetoSet;

final class RosterOverMaxAfterTest extends TestCase
{
    public function testRosterSixteenFires(): void
    {
        $deal = VetoDealFactory::rosterOverMax()['deal'];

        self::assertSame(16, $deal->partner->after[0]->rosterCount);
        self::assertTrue((new RosterOverMaxAfter())->fires($deal));
    }

    public function testRosterFifteenDoesNotFire(): void
    {
        $deal = self::roster(userRoster: 14, partnerRoster: 14);

        self::assertSame(15, $deal->partner->after[0]->rosterCount);
        self::assertFalse((new RosterOverMaxAfter())->fires($deal));
    }

    public function testUserSideOverMaxFires(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [VetoDealFactory::uid(10)],
            [],
            [VetoDealFactory::pid(2, 11), VetoDealFactory::pid(2, 12)],
            [],
            userRoster: 15,
        )['deal'];

        self::assertSame(16, $deal->user->after[0]->rosterCount);
        self::assertTrue((new RosterOverMaxAfter())->fires($deal));
    }

    public function testSmallRosterDoesNotFire(): void
    {
        $deal = self::roster(userRoster: 13, partnerRoster: 13);

        self::assertSame(12, $deal->user->after[0]->rosterCount);
        self::assertFalse((new RosterOverMaxAfter())->fires($deal));
    }

    public function testOnePlayableAtSlotDoesNotFire(): void
    {
        $deal = VetoDealFactory::thinSlot()['deal'];

        self::assertSame(1, $deal->user->after[0]->positionStrength['SG']->playableDepth);
        self::assertFalse((new RosterOverMaxAfter())->fires($deal));
    }

    public function testOtherVetoesQuietOnFixture(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::rosterOverMax()['deal']);

        self::assertSame(['ROSTER_OVER_MAX_AFTER'], $report->firedIds());
    }

    /**
     * The user sends its two no-minute extras (G, F) for the partner's one
     * (GF): the user ends at its size minus one, the partner at its size plus one.
     */
    private static function roster(int $userRoster, int $partnerRoster): DealView
    {
        return VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [],
            [],
            [VetoDealFactory::uid(10), VetoDealFactory::uid(11)],
            [],
            [VetoDealFactory::pid(2, 12)],
            [],
            $userRoster,
            $partnerRoster,
        )['deal'];
    }
}
