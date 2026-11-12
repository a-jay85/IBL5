<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\MoneyPlaces;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\Valuation\PickWorth;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\CapRoomAsset;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Valuation\RookieControl;

final class SideGainTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testTotalWeightsSeasonsByMode(): void
    {
        $components = [];
        foreach ([0, 1, 2, 3] as $offset) {
            $components[$offset] = array_fill_keys(SideGain::KEYS, 0.0);
            $components[$offset]['starter_slots'] = (float) ($offset + 1);
        }
        $winNow = new SideGain(1, TeamMode::WinNow, $components, GainWeights::seasonWeights(TeamMode::WinNow), 1.0);
        $middle = new SideGain(1, TeamMode::Middle, $components, GainWeights::seasonWeights(TeamMode::Middle), 1.0);

        self::assertEqualsWithDelta(0.55 * 1 + 0.25 * 2 + 0.12 * 3 + 0.08 * 4, $winNow->total(), 1e-9);
        self::assertEqualsWithDelta(0.30 * 1 + 0.30 * 2 + 0.22 * 3 + 0.18 * 4, $middle->total(), 1e-9);
    }

    public function testRentalScaleAppliesToOffsetZeroOnly(): void
    {
        $components = [];
        foreach ([0, 1, 2, 3] as $offset) {
            $components[$offset] = array_fill_keys(SideGain::KEYS, 0.0);
            $components[$offset]['bench_slots'] = 2.0;
        }
        $gain = new SideGain(1, TeamMode::Middle, $components, GainWeights::seasonWeights(TeamMode::Middle), 0.5);

        self::assertEqualsWithDelta(1.0, $gain->seasonTotal(0), 1e-12);
        self::assertEqualsWithDelta(2.0, $gain->seasonTotal(1), 1e-12);
        self::assertSame($components, $gain->components());
    }

    public function testStarterGainUsesStarterWeightThree(): void
    {
        self::assertEqualsWithDelta(
            3.0 * RankUtility::gain(20, 19),
            SideGain::slots(['PG' => 20], ['PG' => 19], GainWeights::STARTER_WEIGHT, []),
            1e-12,
        );
    }

    public function testNullStarterRankCountsAsTwentyEight(): void
    {
        self::assertEqualsWithDelta(RankUtility::gain(28, 10), SideGain::slots(['PG' => null], ['PG' => 10], 1.0, []), 1e-12);
        self::assertEqualsWithDelta(RankUtility::gain(10, 28), SideGain::slots(['PG' => 10], ['PG' => null], 1.0, []), 1e-12);
    }

    public function testInjuredNewStarterScalesOffsetZeroAvailability(): void
    {
        self::assertSame(0.0, SideGain::slots(['PG' => 20], ['PG' => 5], 3.0, ['PG' => 0.0]));
        self::assertEqualsWithDelta(
            3.0 * RankUtility::gain(20, 5) * 0.5 + 3.0 * RankUtility::gain(20, 5),
            SideGain::slots(['PG' => 20, 'SG' => 20], ['PG' => 5, 'SG' => 5], 3.0, ['PG' => 0.5]),
            1e-12,
        );
    }

    public function testCapRoomZeroAtOffsetZero(): void
    {
        self::assertSame(0.0, SideGain::capRoom(0, 20, 5));
        self::assertEqualsWithDelta(CapRoomAsset::FUTURE_ROOM_WEIGHT * RankUtility::gain(20, 5), SideGain::capRoom(1, 20, 5), 1e-12);
    }

    public function testPicksAreNetReceivedMinusSent(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 2, [], [11], [], [11]);
        $state = TeamStateRebuilder::before($s, 1, 0);

        $components = SideGain::fromStates($state, $state, $s, $c, 1);

        self::assertSame(0.0, $components['picks']);
        $oneWay = SideGain::fromStates($state, $state, $s, new TradeCandidate(1, 2, [], [], [], [21]), 1);
        self::assertGreaterThan(0.0, $oneWay['picks']);
    }

    public function testRosterSpotCreditOnlyWhenFullBefore(): void
    {
        self::assertSame(0.0, SideGain::rosterSpots(14, 13));
        self::assertEqualsWithDelta(0.5, SideGain::rosterSpots(15, 14), 1e-12);
    }

    public function testContractsComponentConvertsSurplusThroughMoneyPlaces(): void
    {
        $s = self::league();
        [$pid, $c] = self::receivedNonRookie($s);
        $after = TeamStateRebuilder::after($s, $c, 1, new PairRecordMemo())['user'];
        $before = TeamStateRebuilder::before($s, 1, 1);

        $components = SideGain::fromStates($before, $after, $s, $c, 1, [$pid => 1.0]);

        self::assertEqualsWithDelta(MoneyPlaces::fromSalary($s->surplusBySeason($pid)[1]), $components['contracts'], 1e-9);
    }

    public function testLikelyWalkerSurplusIsDiscountedByLikelihood(): void
    {
        $s = self::league();
        [$pid, $c] = self::receivedNonRookie($s);
        $after = TeamStateRebuilder::after($s, $c, 1, new PairRecordMemo())['user'];
        $before = TeamStateRebuilder::before($s, 1, 1);

        $components = SideGain::fromStates($before, $after, $s, $c, 1, [$pid => 0.25]);

        self::assertEqualsWithDelta(0.25 * MoneyPlaces::fromSalary($s->surplusBySeason($pid)[1]), $components['contracts'], 1e-9);
    }

    public function testRookieControlPerOffsetSumsToAssetPlaces(): void
    {
        $surplus = [0 => 300, 1 => 300, 2 => 300, 3 => 300];
        self::assertTrue(RookieControl::isRookieScale(1, 1));
        $sum = 0.0;
        foreach ([0, 1, 2, 3] as $offset) {
            $sum += SideGain::rookieAt(1, 1, $offset, $surplus);
        }

        self::assertGreaterThan(0.0, $sum);
        self::assertEqualsWithDelta(RookieControl::assetPlaces(RookieControl::controlYearsLeft(1, 1), $surplus), $sum, 1e-12);
    }

    public function testNonRookieGetsNoControlPlaces(): void
    {
        self::assertFalse(RookieControl::isRookieScale(1, 8));
        self::assertSame(0.0, SideGain::rookieAt(1, 8, 0, [0 => 300, 1 => 300, 2 => 300, 3 => 300]));
    }

    public function testRookieAtTreatsAnOffsetMissingFromSurplusAsNoSurplus(): void
    {
        self::assertTrue(RookieControl::isRookieScale(1, 1));

        self::assertSame(0.0, SideGain::rookieAt(1, 1, 0, [1 => 300, 2 => 300, 3 => 300]));
    }

    public function testReceivedPicksAreOrdinalledInOrderAndSummed(): void
    {
        $s = self::league();
        $state = self::withRosterCount(TeamStateRebuilder::before($s, 1, 0), 14);
        $c = new TradeCandidate(1, 2, [], [], [], [21, 22]);

        $picks = SideGain::fromStates($state, $state, $s, $c, 1)['picks'];

        // One open spot: the first pick fits, the second is a glut.
        self::assertEqualsWithDelta(self::worth($s, 21, 1, 1) + self::worth($s, 22, 1, 2), $picks, 1e-12);
        self::assertNotEqualsWithDelta(self::worth($s, 21, 1, 1) + self::worth($s, 22, 1, 1), $picks, 1e-9);
    }

    public function testSentPickIsWorthItsFirstOrdinalAgainstOpenSpots(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 2, [], [11], [], []);
        $oneOpen = self::withRosterCount(TeamStateRebuilder::before($s, 1, 0), 14);
        $full = self::withRosterCount(TeamStateRebuilder::before($s, 1, 0), 15);

        self::assertEqualsWithDelta(-self::worth($s, 11, 1, 1), SideGain::fromStates($oneOpen, $oneOpen, $s, $c, 1)['picks'], 1e-12);
        self::assertEqualsWithDelta(-self::worth($s, 11, 0, 1), SideGain::fromStates($full, $full, $s, $c, 1)['picks'], 1e-12);
        self::assertNotEqualsWithDelta(self::worth($s, 11, 1, 1), self::worth($s, 11, 0, 1), 1e-9);
    }

    private static function worth(LeagueSnapshot $s, int $pickId, int $open, int $ordinal): float
    {
        $pick = $s->picks[$pickId];

        return PickWorth::worth($pick->round, $pick->yearsAway, $pick->originalTid, $s->draftIndex, $s->mode(1), $open, $ordinal);
    }

    private static function withRosterCount(TeamState $base, int $count): TeamState
    {
        return new TeamState(
            $base->tid,
            $base->offset,
            $base->lineup,
            $base->starterRank,
            $base->bestBenchRank,
            $base->profile,
            $base->positionStrength,
            $base->payroll,
            $base->payrollRank,
            $count,
        );
    }

    public function testComponentsExposesEveryKeyPerSeason(): void
    {
        $s = self::league();
        [, $c] = self::receivedNonRookie($s);
        $memo = new PairRecordMemo();
        foreach ([0, 1, 2, 3] as $offset) {
            $components = SideGain::fromStates(
                TeamStateRebuilder::before($s, 1, $offset),
                TeamStateRebuilder::after($s, $c, $offset, $memo)['user'],
                $s,
                $c,
                1,
            );
            self::assertSame(SideGain::KEYS, array_keys($components));
        }
        self::assertSame('contracts', SideGain::KEYS[count(SideGain::KEYS) - 1]);
    }

    public function testNoMethodAcceptsAPlayerOrIsNamedLikeAScore(): void
    {
        $class = new \ReflectionClass(SideGain::class);
        foreach ($class->getMethods() as $method) {
            $name = strtolower($method->getName());
            foreach (['score', 'value', 'rating', 'overall'] as $banned) {
                self::assertStringNotContainsString($banned, $name, $method->getName());
            }
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                self::assertFalse(
                    $type instanceof \ReflectionNamedType && $type->getName() === ProjectionPlayer::class,
                    $method->getName() . ' takes a player',
                );
            }
        }
    }

    /**
     * A team-2 player with a contract at offset 1 and no rookie scale, sent to
     * team 1 for nothing.
     *
     * @return array{int, TradeCandidate}
     */
    private static function receivedNonRookie(LeagueSnapshot $s): array
    {
        foreach (array_keys($s->lineups[0][2]->players) as $pid) {
            $p = $s->players[$pid];
            if ($p->salaryAt(1) > 0 && !RookieControl::isRookieScale($p->draftRound, $p->exp) && $s->surplusBySeason($pid)[1] !== 0) {
                return [$pid, new TradeCandidate(1, 2, [], [], [$pid], [])];
            }
        }
        self::fail('fixture has no suitable team-2 player');
    }
}
