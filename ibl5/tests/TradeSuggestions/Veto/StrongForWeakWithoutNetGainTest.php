<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\StrongForWeakWithoutNetGain;
use TradeSuggestions\Veto\VetoSet;

final class StrongForWeakWithoutNetGainTest extends TestCase
{
    public function testFiresWhenStrongSpotDropsThreeWhilePatchingWeak(): void
    {
        $deal = self::tuned(VetoDealFactory::strongForWeak()['deal'], 4, 27);

        self::assertTrue((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testFixtureFiresOnItsOwn(): void
    {
        self::assertTrue((new StrongForWeakWithoutNetGain())->fires(VetoDealFactory::strongForWeak()['deal']));
    }

    public function testDropOfTwoInsideTopEightDoesNotFire(): void
    {
        $deal = self::tuned(VetoDealFactory::strongForWeak()['deal'], 3, 27);

        self::assertFalse((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testNoWeakPatchDoesNotFire(): void
    {
        $deal = self::tuned(VetoDealFactory::strongForWeak()['deal'], 10, 28);

        self::assertFalse((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testClearNetGainSuppressesVeto(): void
    {
        $base = self::tuned(VetoDealFactory::strongForWeak()['deal'], 10, 27);
        $veto = new StrongForWeakWithoutNetGain();

        $atBar = VetoDealFactory::withGainTotal($base, $base->user, GainWeights::CLEAR_NET_GAIN);
        $clear = VetoDealFactory::withGainTotal($base, $base->user, GainWeights::CLEAR_NET_GAIN + 0.01);

        self::assertTrue($veto->fires($atBar));
        self::assertFalse($veto->fires($clear));
    }

    public function testPositiveSlotSumSuppressesVeto(): void
    {
        $deal = self::tuned(VetoDealFactory::strongForWeak()['deal'], 5, 10);
        $deal = VetoDealFactory::withGainTotal($deal, $deal->user, 0.0);

        self::assertFalse((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testZeroSlotSumStillFires(): void
    {
        $deal = self::tuned(VetoDealFactory::strongForWeak()['deal'], 5, 24);
        $deal = VetoDealFactory::withGainTotal($deal, $deal->user, 0.0);

        self::assertTrue((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testSlotSumOfOneSuppressesVeto(): void
    {
        // SF 1 -> 4 (-3), C 28 -> 24 (+4): the side gains one place overall.
        $deal = self::tuned(VetoDealFactory::strongForWeak()['deal'], 4, 24);
        $deal = VetoDealFactory::withGainTotal($deal, $deal->user, 0.0);

        self::assertFalse((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testStarterAtTheStrongCutoffCountsAsStrong(): void
    {
        $deal = self::ranked(SlotStrength::STRONG_MAX_RANK, SlotStrength::STRONG_MAX_RANK + 3, 28, 27);

        self::assertTrue((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testStarterAtTheWeakCutoffCountsAsWeak(): void
    {
        $deal = self::ranked(1, 4, SlotStrength::WEAK_MIN_RANK, SlotStrength::WEAK_MIN_RANK - 1);

        self::assertTrue((new StrongForWeakWithoutNetGain())->fires($deal));
    }

    public function testQuietFixtureDoesNotFire(): void
    {
        self::assertFalse((new StrongForWeakWithoutNetGain())->fires(VetoDealFactory::quiet()['deal']));
    }

    public function testOtherVetoesQuietOnFixture(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::strongForWeak()['deal']);

        self::assertSame(['STRONG_FOR_WEAK_WITHOUT_NET_GAIN'], $report->firedIds());
    }

    /**
     * The user's SF and C after-ranks set to the given places, whatever the
     * rebuilt states said. The before-ranks are the fixture's own: SF 1, C 28.
     */
    private static function tuned(DealView $deal, int $sfAfter, int $cAfter): DealView
    {
        $ranks = $deal->user->before[0]->starterRank;
        self::assertSame(1, $ranks['SF']);
        self::assertSame(28, $ranks['C']);
        $ranks['SF'] = $sfAfter;
        $ranks['C'] = $cAfter;

        return VetoDealFactory::withAfterStarterRanks($deal, $deal->user, $ranks);
    }

    /**
     * The strongForWeak deal with the user's SF and C ranks set before and
     * after, every other slot unchanged, and no net gain.
     */
    private static function ranked(int $sfBefore, int $sfAfter, int $cBefore, int $cAfter): DealView
    {
        $deal = VetoDealFactory::strongForWeak()['deal'];
        $before = $deal->user->before[0]->starterRank;
        $before['SF'] = $sfBefore;
        $before['C'] = $cBefore;
        $after = $before;
        $after['SF'] = $sfAfter;
        $after['C'] = $cAfter;
        $deal = VetoDealFactory::withStarterRanks($deal, $deal->user, $before, $after);

        return VetoDealFactory::withGainTotal($deal, $deal->user, 0.0);
    }
}
