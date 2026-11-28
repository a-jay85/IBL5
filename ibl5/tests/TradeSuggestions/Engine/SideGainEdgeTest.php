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
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\InjuryStatus;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Valuation\RookieControl;

final class SideGainEdgeTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testProfileDimensionsSumsEveryDimensionGain(): void
    {
        $before = [];
        $after = [];
        foreach (ProfileDimension::KEYS as $i => $key) {
            $before[$key] = 20;
            $after[$key] = $i < 3 ? 10 : 20;
        }

        self::assertEqualsWithDelta(3 * RankUtility::gain(20, 10), SideGain::profileDimensions($before, $after), 1e-9);
    }

    public function testProfileDimensionsSkipsKeysMissingFromEitherSideAndStartsAtZero(): void
    {
        self::assertSame(0.0, SideGain::profileDimensions([], []));
        self::assertSame(0.0, SideGain::profileDimensions(['pts' => 20], ['reb' => 10]));
        self::assertEqualsWithDelta(
            RankUtility::gain(20, 10),
            SideGain::profileDimensions(['pts' => 20, 'reb' => 20], ['pts' => 10]),
            1e-12,
        );
    }

    public function testProfileDimensionsNetsAWorseMoveAgainstABetterOne(): void
    {
        $expected = RankUtility::gain(20, 10) + RankUtility::gain(5, 15);

        self::assertEqualsWithDelta($expected, SideGain::profileDimensions(['pts' => 20, 'reb' => 5], ['pts' => 10, 'reb' => 15]), 1e-12);
    }

    public function testBalanceIsRankGainOfOnePlusAbsoluteBalance(): void
    {
        // 21 -> 11: places 21, 20 weigh 1.0 each and 19..12 weigh 0.6 each.
        self::assertEqualsWithDelta(2.0 + 8 * 0.6, SideGain::balance(20, 10), 1e-9);
        self::assertEqualsWithDelta(2.0 + 8 * 0.6, SideGain::balance(-20, -10), 1e-9);
        self::assertEqualsWithDelta(-(2.0 + 8 * 0.6), SideGain::balance(10, 20), 1e-9);
    }

    public function testBalanceZeroIsRankOneAndBalanceClampsAtTwentyEight(): void
    {
        // 6 -> 1 crosses places 6..2, all in the top band.
        self::assertEqualsWithDelta(5 * 0.3, SideGain::balance(5, 0), 1e-9);
        self::assertSame(0.0, SideGain::balance(0, 0));
        self::assertSame(0.0, SideGain::balance(40, 99));
        self::assertEqualsWithDelta(RankUtility::gain(28, 10), SideGain::balance(500, 9), 1e-9);
    }

    public function testRookieAtUsesOnlyTheRequestedOffsetOfTheSurplus(): void
    {
        $surplus = [0 => 300, 1 => 300, 2 => 300, 3 => 300];

        // Round 1, exp 1: three control years; offset 2 is the option year.
        self::assertSame(0.5, SideGain::rookieAt(1, 1, 1, $surplus));
        self::assertSame(0.25, SideGain::rookieAt(1, 1, 2, $surplus));
        self::assertSame(0.0, SideGain::rookieAt(1, 1, 3, $surplus));
        self::assertSame(0.0, SideGain::rookieAt(1, 1, 1, [0 => 300, 1 => 0, 2 => 300, 3 => 300]));
    }

    public function testInjuredReceivedStarterIsWeightedByTeamModeAtOffsetZero(): void
    {
        $s = self::league();
        $lineup = $s->lineups[0][2];
        $pids = self::startersOf($lineup);
        // PG back for playoffs, SG out for the year; SF healthy; PF out for the
        // year but not part of the deal; C not injured.
        $injured = self::snapshotWith($s, injury: [
            $pids['PG'] => InjuryStatus::BackForPlayoffs,
            $pids['SG'] => InjuryStatus::OutForYear,
            $pids['PF'] => InjuryStatus::OutForYear,
        ]);
        $received = [$pids['PG'], $pids['SG'], $pids['SF']];
        $g = RankUtility::gain(20, 10);
        $base = TeamStateRebuilder::before($s, 1, 0);
        $before = self::stateOf($base, 0, starterRank: array_fill_keys(SlotMapper::SLOTS, 20));
        $after = self::stateOf($base, 0, lineup: $lineup, starterRank: array_fill_keys(SlotMapper::SLOTS, 10));

        // Team 1 is WinNow: the playoff weight counts a playoff-back starter in full.
        $winNow = SideGain::fromStates($before, $after, $injured, new TradeCandidate(1, 2, [], [], $received, []), 1);
        self::assertEqualsWithDelta(GainWeights::STARTER_WEIGHT * $g * (1.0 + 0.0 + 1.0 + 1.0 + 1.0), $winNow['starter_slots'], 1e-9);

        // Team 2 is Middle: the regular-season weight applies.
        $middle = SideGain::fromStates($before, $after, $injured, new TradeCandidate(1, 2, $received, [], [], []), 2);
        self::assertEqualsWithDelta(GainWeights::STARTER_WEIGHT * $g * (0.6 + 0.0 + 1.0 + 1.0 + 1.0), $middle['starter_slots'], 1e-9);
    }

    public function testInjuryIsIgnoredAfterOffsetZero(): void
    {
        $s = self::league();
        $lineup = $s->lineups[1][2];
        $pids = self::startersOf($lineup);
        $injured = self::snapshotWith($s, injury: [$pids['PG'] => InjuryStatus::OutForYear]);
        $base = TeamStateRebuilder::before($s, 1, 1);
        $before = self::stateOf($base, 1, starterRank: array_fill_keys(SlotMapper::SLOTS, 20));
        $after = self::stateOf($base, 1, lineup: $lineup, starterRank: array_fill_keys(SlotMapper::SLOTS, 10));

        $components = SideGain::fromStates($before, $after, $injured, new TradeCandidate(1, 2, [], [], [$pids['PG']], []), 1);

        self::assertEqualsWithDelta(GainWeights::STARTER_WEIGHT * 5 * RankUtility::gain(20, 10), $components['starter_slots'], 1e-9);
    }

    public function testPayrollUsesTheAfterRankAtOffsetZeroAndTheBeforeRankLater(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 2, [], [], [], []);
        $g = RankUtility::gain(20, 10);

        $base0 = TeamStateRebuilder::before($s, 1, 0);
        $now = SideGain::fromStates(
            self::stateOf($base0, 0, payrollRank: 20),
            self::stateOf($base0, 0, payrollRank: 10),
            $s,
            $c,
            1,
        );
        self::assertEqualsWithDelta($g, $now['payroll'], 1e-9);
        self::assertSame(0.0, $now['cap_room']);

        $base1 = TeamStateRebuilder::before($s, 1, 1);
        $later = SideGain::fromStates(
            self::stateOf($base1, 1, payrollRank: 20),
            self::stateOf($base1, 1, payrollRank: 10),
            $s,
            $c,
            1,
        );
        self::assertEqualsWithDelta(0.0, $later['payroll'], 1e-9);
        self::assertEqualsWithDelta($g, $later['cap_room'], 1e-9);
    }

    public function testPicksComponentIsZeroAfterOffsetZero(): void
    {
        $s = self::league();
        $state = self::stateOf(TeamStateRebuilder::before($s, 1, 1), 1);

        $components = SideGain::fromStates($state, $state, $s, new TradeCandidate(1, 2, [], [], [], [21]), 1);

        self::assertSame(0.0, $components['picks']);
    }

    public function testFutureValueFactorFollowsTheSnapshotWalkAndLikelihoodMaps(): void
    {
        $s = self::league();
        [$pid, $c] = self::receivedNonRookie($s, 1);
        $after = TeamStateRebuilder::after($s, $c, 1, new PairRecordMemo())['user'];
        $before = TeamStateRebuilder::before($s, 1, 1);
        $full = MoneyPlaces::fromSalary($s->surplusBySeason($pid)[1]);

        $walker = self::snapshotWith($s, likelyToWalk: [$pid => true], resignLikelihood: [$pid => 0.3]);
        self::assertEqualsWithDelta(0.3 * $full, SideGain::fromStates($before, $after, $walker, $c, 1)['contracts'], 1e-9);

        $noFlag = self::snapshotWith($s, likelyToWalk: [], resignLikelihood: [$pid => 0.3]);
        self::assertEqualsWithDelta($full, SideGain::fromStates($before, $after, $noFlag, $c, 1)['contracts'], 1e-9);

        $noLikelihood = self::snapshotWith($s, likelyToWalk: [$pid => true], resignLikelihood: []);
        self::assertEqualsWithDelta($full, SideGain::fromStates($before, $after, $noLikelihood, $c, 1)['contracts'], 1e-9);
    }

    public function testContractsSubtractsSentSurplusAndSentRookieControl(): void
    {
        $s = self::league();
        $offset = 1;
        $sent = self::pidsWithPositiveSurplus($s, 1, $offset, 2);
        $received = self::pidsWithPositiveSurplus($s, 2, $offset, 1)[0];
        $players = $s->players;
        foreach ($sent as $pid) {
            $players[$pid] = self::withDraft($players[$pid], 1, 1);
        }
        $players[$received] = self::withDraft($players[$received], 0, 8);
        $snap = self::snapshotWith($s, players: $players);

        $c = new TradeCandidate(1, 2, $sent, [], [$received], []);
        $before = TeamStateRebuilder::before($s, 1, $offset);
        $after = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo())['user'];

        $money = $s->surplusBySeason($received)[$offset]
            - $s->surplusBySeason($sent[0])[$offset]
            - $s->surplusBySeason($sent[1])[$offset];

        $contracts = SideGain::fromStates($before, $after, $snap, $c, 1)['contracts'];

        self::assertEqualsWithDelta($money * MoneyPlaces::placesPerSalaryUnit() - 1.0, $contracts, 1e-9);
    }

    public function testContractsAddsReceivedRookieControlAndSumsSeveralPlayers(): void
    {
        $s = self::league();
        $offset = 1;
        $received = self::pidsWithPositiveSurplus($s, 2, $offset, 2);
        $players = $s->players;
        foreach ($received as $pid) {
            $players[$pid] = self::withDraft($players[$pid], 1, 1);
        }
        $snap = self::snapshotWith($s, players: $players);

        $c = new TradeCandidate(1, 2, [], [], $received, []);
        $before = TeamStateRebuilder::before($s, 1, $offset);
        $after = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo())['user'];

        $money = $s->surplusBySeason($received[0])[$offset] + $s->surplusBySeason($received[1])[$offset];

        $contracts = SideGain::fromStates($before, $after, $snap, $c, 1)['contracts'];

        self::assertEqualsWithDelta($money * MoneyPlaces::placesPerSalaryUnit() + 1.0, $contracts, 1e-9);
    }

    /**
     * @return array<string, int>
     */
    private static function startersOf(SeasonLineup $lineup): array
    {
        $pids = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $pid = $lineup->starterPid($slot);
            self::assertNotNull($pid);
            $pids[$slot] = $pid;
        }

        return $pids;
    }

    /**
     * @param array<string, ?int>|null $starterRank
     */
    private static function stateOf(
        TeamState $base,
        int $offset,
        ?SeasonLineup $lineup = null,
        ?array $starterRank = null,
        ?int $payrollRank = null,
    ): TeamState {
        return new TeamState(
            $base->tid,
            $offset,
            $lineup ?? $base->lineup,
            $starterRank ?? $base->starterRank,
            $base->bestBenchRank,
            $base->profile,
            $base->positionStrength,
            $base->payroll,
            $payrollRank ?? $base->payrollRank,
            $base->rosterCount,
        );
    }

    private static function withDraft(ProjectionPlayer $p, int $round, int $exp): ProjectionPlayer
    {
        return new ProjectionPlayer(
            $p->pid,
            $p->name,
            $p->pos,
            $p->tid,
            $p->age,
            $p->injuredDays,
            $exp,
            $p->talent,
            $p->bird,
            $round,
            $p->draftYear,
            $p->draftPickNo,
            $p->prefs,
            $p->ratings,
            $p->depth,
            $p->dcDepth,
            $p->dcMinutes,
            $p->effectiveCy,
            $p->salaryByYear,
            $p->production,
            $p->tradeable,
            $p->productionFouls,
        );
    }

    /**
     * @param array<int, ProjectionPlayer>|null $players
     * @param array<int, InjuryStatus>|null $injury
     * @param array<int, bool>|null $likelyToWalk
     * @param array<int, float>|null $resignLikelihood
     */
    private static function snapshotWith(
        LeagueSnapshot $s,
        ?array $players = null,
        ?array $injury = null,
        ?array $likelyToWalk = null,
        ?array $resignLikelihood = null,
    ): LeagueSnapshot {
        return new LeagueSnapshot(
            $s->seasonYear,
            $s->advancesContractYears,
            $s->regularSeason,
            $s->teamIds,
            $players ?? $s->players,
            $s->teamOfPid,
            $s->projections,
            $s->lineups,
            $s->slotRanks,
            $s->profiles,
            $s->positionStrength,
            $s->situations,
            $s->standings,
            $s->streaks,
            $s->fairSalary,
            $s->cashByTeamAndOffset,
            $s->payrollByOffset,
            $s->payrollRanks,
            $s->picks,
            $s->draftOrder,
            $s->draftIndex,
            $s->modes,
            $injury ?? $s->injury,
            $s->teamFactors,
            $s->rivalFactors,
            $resignLikelihood ?? $s->resignLikelihood,
            $likelyToWalk ?? $s->likelyToWalk,
            $s->teamNames,
        );
    }

    /**
     * @return list<int>
     */
    private static function pidsWithPositiveSurplus(LeagueSnapshot $s, int $tid, int $offset, int $count): array
    {
        $found = [];
        foreach (array_keys($s->lineups[$offset][$tid]->players) as $pid) {
            if ($s->surplusBySeason($pid)[$offset] > 0) {
                $found[] = $pid;
                if (count($found) === $count) {
                    return $found;
                }
            }
        }
        self::fail("fixture has fewer than {$count} players with positive surplus");
    }

    /**
     * @return array{int, TradeCandidate}
     */
    private static function receivedNonRookie(LeagueSnapshot $s, int $offset): array
    {
        foreach (array_keys($s->lineups[0][2]->players) as $pid) {
            $p = $s->players[$pid];
            if ($p->salaryAt($offset) > 0 && !RookieControl::isRookieScale($p->draftRound, $p->exp) && $s->surplusBySeason($pid)[$offset] !== 0) {
                return [$pid, new TradeCandidate(1, 2, [], [], [$pid], [])];
            }
        }
        self::fail('fixture has no suitable team-2 player');
    }
}
