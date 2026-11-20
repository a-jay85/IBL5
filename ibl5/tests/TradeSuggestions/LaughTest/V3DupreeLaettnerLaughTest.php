<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\LaughTest;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Fixtures\LaughTestRows;
use Tests\TradeSuggestions\LaughTest\Fixtures\LaughTestFactory;
use TradeSuggestions\Engine\DealRanker;
use TradeSuggestions\Engine\ExposureFilter;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\RankGate;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\VetoSet;

/**
 * v3 must veto the Dupree-for-Laettner deal by rule id, whatever it scores.
 */
final class V3DupreeLaettnerLaughTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    public function testV3VetoesDupreeForLaettnerByIdNotByScore(): void
    {
        $fired = VetoSet::standard()->evaluate(LaughTestFactory::dupreeForLaettner(self::league()))->firedIds();

        self::assertContains('YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE', $fired);
        self::assertContains('WIN_NOW_WORSE_THIS_SEASON', $fired);
    }

    public function testDupreeDealIsRejectedBeforeRankingRegardlessOfGain(): void
    {
        $s = self::league();
        $real = LaughTestFactory::scored($s);
        // Give both sides a clear, balanced gain so only the vetoes can stop it.
        $deal = new ScoredDeal(
            $real->candidate,
            $real->shapeId,
            new SideGain($real->user->tid, $real->user->mode, [0 => ['starter_slots' => 10.0]], [1.0], 1.0),
            new SideGain($real->partner->tid, $real->partner->mode, [0 => ['starter_slots' => 12.0]], [1.0], 1.0),
            $real->userDelta,
            $real->partnerDelta,
        );
        $calls = 0;

        $result = (new DealRanker())->select(
            $s,
            self::simKey($s),
            LaughTestRows::USER_TID,
            [$deal],
            VetoSet::standard(),
            static function (ScoredDeal $d, DealView $v) use (&$calls): array {
                $calls++;

                return [RankGate::Pass, '', 'user', 'partner'];
            },
        );

        self::assertSame([], $result->selected);
        self::assertSame(1, $result->diagnostics->vetoed);
        self::assertSame(0, $calls);
    }

    public function testMirrorDupreeIsAlsoVetoed(): void
    {
        $s = self::league();
        $mirror = new TradeCandidate(
            LaughTestRows::PARTNER_TID,
            LaughTestRows::USER_TID,
            [LaughTestRows::LAETTNER_PID],
            [],
            [LaughTestRows::DUPREE_PID],
            LaughTestFactory::pickIds(),
        );

        $view = DealView::fromScoredDeal($s, LaughTestFactory::scored($s, $mirror));

        self::assertContains('YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE', VetoSet::standard()->evaluate($view)->firedIds());
    }

    public function testLaettnerForDupreeWithNoPicksStillFiresVetoOne(): void
    {
        $s = self::league();
        $noPicks = new TradeCandidate(
            LaughTestRows::USER_TID,
            LaughTestRows::PARTNER_TID,
            [LaughTestRows::DUPREE_PID],
            [],
            [LaughTestRows::LAETTNER_PID],
            [],
        );

        $view = DealView::fromScoredDeal($s, LaughTestFactory::scored($s, $noPicks));

        self::assertContains('YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE', VetoSet::standard()->evaluate($view)->firedIds());
    }

    public function testFixtureRowsCarryNoBannedColumns(): void
    {
        $specs = [LaughTestRows::dupree(), LaughTestRows::laettner(), ...LaughTestRows::fillers(LaughTestRows::USER_TID)];
        $keySets = [];
        foreach ($specs as $spec) {
            $keySets[] = array_keys($spec);
            $keySets[] = array_keys(LaughTestRows::asPlrRow($spec, LaughTestRows::USER_TID));
        }

        foreach ($keySets as $keys) {
            foreach ($keys as $key) {
                self::assertNotContains($key, ['ovr', 'peak', 'stamina']);
                if ($key !== 'injured') {
                    self::assertStringStartsNotWith('inj_', $key);
                    self::assertStringStartsNotWith('injury', $key);
                }
            }
        }
    }

    public function testFixtureSalariesWithinTwentyFivePercent(): void
    {
        $dupree = LaughTestRows::dupree()['salaries'][0];
        $laettner = LaughTestRows::laettner()['salaries'][0];

        self::assertLessThanOrEqual(0.25, abs($dupree - $laettner) / $laettner);
    }

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= LaughTestFactory::league();
    }

    /**
     * The first sim key under which team 1 may target team 2.
     */
    private static function simKey(LeagueSnapshot $s): string
    {
        for ($i = 0; $i < 1000; $i++) {
            $key = 'sim-' . $i;
            if (in_array(LaughTestRows::PARTNER_TID, ExposureFilter::eligiblePartners($key, LaughTestRows::USER_TID, $s->teamIds), true)) {
                return $key;
            }
        }
        self::fail('no sim key lets team 1 target team 2');
    }
}
