<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\CandidateKey;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\GenerationResult;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\ProfileDelta;
use TradeSuggestions\Engine\ResignOdds;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamStateRebuilder;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Profile\ProfileDimension;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\CapRoomAsset;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Valuation\ResignLikelihood;

final class DealScorerTest extends TestCase
{
    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testScoreIsDeterministic(): void
    {
        $c = self::swap(self::league());

        self::assertEquals(DealScorer::score(self::league(), $c, 'one_for_one'), DealScorer::score(self::league(), $c, 'one_for_one'));
    }

    public function testBothSidesAreScoredFromTheirOwnMode(): void
    {
        $s = self::leagueWith([1 => TeamMode::Rebuild, 2 => TeamMode::WinNow], null);

        $deal = DealScorer::score($s, self::swap($s), 'one_for_one');

        self::assertSame(GainWeights::seasonWeights(TeamMode::Rebuild), $deal->user->seasonWeights());
        self::assertSame(GainWeights::seasonWeights(TeamMode::WinNow), $deal->partner->seasonWeights());
        self::assertSame(TeamMode::Rebuild, $deal->user->mode);
    }

    public function testRentalScaleOnlyForWinNowLateSeason(): void
    {
        $s = self::leagueWith([1 => TeamMode::WinNow, 2 => TeamMode::Rebuild], 10);

        $deal = DealScorer::score($s, self::swap($s), 'one_for_one');

        self::assertLessThan(1.0, $deal->user->rentalScale());
        self::assertEqualsWithDelta(GainWeights::rentalScale(10), $deal->user->rentalScale(), 1e-12);
        self::assertSame(1.0, $deal->partner->rentalScale());
    }

    public function testOneForOneUpgradeAtWeakSlotGivesPositiveUserStarterComponent(): void
    {
        $s = self::league();
        $found = false;
        foreach (array_keys($s->lineups[0][2]->players) as $pid) {
            $deal = DealScorer::score($s, new TradeCandidate(1, 2, [], [], [$pid], []), 'one_for_one');
            if ($deal->user->components()[0]['starter_slots'] > 0.0) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found, 'some team-2 player upgrades a team-1 starter slot');
    }

    public function testMirrorDealGivesMirroredSideGains(): void
    {
        $s = self::league();
        $c = self::swap($s);
        $mirror = new TradeCandidate($c->partnerTid, $c->userTid, $c->partnerPids, $c->partnerPickIds, $c->userPids, $c->userPickIds);

        $deal = DealScorer::score($s, $c, 'one_for_one');
        $mirrored = DealScorer::score($s, $mirror, 'one_for_one');

        self::assertEquals($deal->user->components(), $mirrored->partner->components());
        self::assertEquals($deal->partner->components(), $mirrored->user->components());
        self::assertNotEquals($deal->user->components(), $deal->partner->components());
    }

    public function testEmptySideCandidateScoresWithoutException(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 2, [], [11], [], []);

        $deal = DealScorer::score($s, $c, 'pick_swap');

        self::assertTrue(is_finite($deal->user->total()));
        self::assertTrue(is_finite($deal->partner->total()));
        foreach ($deal->userDelta as $delta) {
            self::assertSame($delta->payroll['before'], $delta->payroll['after']);
        }
        self::assertSame(0.0, $deal->user->components()[0]['roster_spots']);
    }

    public function testWeakerSideTotalIsTheMinimum(): void
    {
        $deal = DealScorer::score(self::league(), self::swap(self::league()), 'one_for_one');

        self::assertNotSame($deal->user->total(), $deal->partner->total());
        self::assertSame(min($deal->user->total(), $deal->partner->total()), $deal->weakerSideTotal());
        self::assertSame($deal->user->total() > 0.0 && $deal->partner->total() > 0.0, $deal->bothSidesPositive());
    }

    public function testProfileDeltaRecordsBeforeAndAfterRanks(): void
    {
        [$a, $b, $c] = ProfileDimension::KEYS;
        $delta = new ProfileDelta(1, 0, [
            $b => ['before' => 10, 'after' => 9],
            $a => ['before' => 24, 'after' => 20],
            $c => ['before' => 5, 'after' => 5],
        ], [], ['before' => 1, 'after' => 1], ['before' => 0, 'after' => 0]);
        self::assertSame([$a, $b], $delta->movedDimensions());
        self::assertSame([$a], $delta->movedDimensions(2));

        $s = self::league();
        $deal = DealScorer::score($s, self::swap($s), 'one_for_one');
        foreach ($deal->userDelta[0]->dimensionRanks as $key => $pair) {
            self::assertSame($s->profiles[0][1]->ranks[$key], $pair['before']);
        }
        foreach (SlotMapper::SLOTS as $slot) {
            self::assertSame($s->slotRanks[0]->starterRank($slot, 1), $deal->userDelta[0]->starterRanks[$slot]['before']);
        }
    }

    public function testHardCapBreachLeakedPastPrefilterRanksAtSentinel(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 2, [], [], array_keys($s->lineups[0][2]->players), []);
        self::assertTrue(CapRoomAsset::breachesHardCapNow($s->payrollByOffset[0][1] + $s->payrollByOffset[0][2]));

        $deal = DealScorer::score($s, $c, 'dump');

        self::assertSame(CapRoomAsset::HARD_CAP_BREACH_PLACES, $deal->user->components()[0]['payroll']);
    }

    public function testScoreAllReturnsOnePerCandidateInInputOrder(): void
    {
        $s = self::league();
        $pids = array_keys($s->lineups[0][2]->players);
        $candidates = [];
        $shapes = [];
        foreach ([$pids[3], $pids[0], $pids[5], $pids[1]] as $pid) {
            $c = new TradeCandidate(1, 2, [], [], [$pid], []);
            $candidates[] = $c;
            $shapes[CandidateKey::of($c)] = 'shape-' . $pid;
        }
        $g = new GenerationResult($candidates, 4, 0, [], [2], false, $shapes);

        $deals = DealScorer::scoreAll($s, $g, $g->shapeIdByKey);

        self::assertCount(4, $deals);
        foreach ($deals as $i => $deal) {
            self::assertSame($candidates[$i], $deal->candidate);
            self::assertSame($shapes[CandidateKey::of($candidates[$i])], $deal->shapeId);
        }
    }

    public function testScoreAllThrowsOnMissingShapeId(): void
    {
        $s = self::league();
        $g = new GenerationResult([self::swap($s)], 1, 0, [], [2], false, []);

        $this->expectException(\OutOfBoundsException::class);
        DealScorer::scoreAll($s, $g, []);
    }

    public function testMiddleModeUsesMiddleWeightsAndNoRentalScale(): void
    {
        $s = self::leagueWith([1 => TeamMode::Middle, 2 => TeamMode::WinNow], 10);

        $deal = DealScorer::score($s, self::swap($s), 'one_for_one');

        self::assertSame(GainWeights::seasonWeights(TeamMode::Middle), $deal->user->seasonWeights());
        self::assertSame(1.0, $deal->user->rentalScale());
        self::assertLessThan(1.0, $deal->partner->rentalScale());
    }

    public function testScoreAllFillsTheSharedMemoItWasGiven(): void
    {
        $s = self::smallLeague();
        $c = new TradeCandidate(1, 2, [101], [], [207], []);
        $g = new GenerationResult([$c], 1, 0, [], [2], false, [CandidateKey::of($c) => 'one_for_one']);
        $memo = new PairRecordMemo();

        DealScorer::scoreAll($s, $g, $g->shapeIdByKey, $memo);

        self::assertGreaterThan(0, $memo->size());
    }

    public function testReceivedPlayerWhoWillNotWalkAgainstTheReceiverScoresAsPlain(): void
    {
        // 202 is not a walker for team 1 on offset-0 slot ranks (he would be on offset-1 ranks)
        // and he is not a snapshot walker for team 2, so neither side mixes outcomes.
        $s = self::smallLeague();
        $c = new TradeCandidate(1, 2, [], [], [202], []);
        $odds = ResignOdds::forTeam($s->players[202], 1, $s->lineups[0], $s->slotRanks[0], $s->teamFactors);
        self::assertFalse($odds['walks']);
        self::assertFalse($odds['walks'] === ResignOdds::forTeam($s->players[202], 1, $s->lineups[0], $s->slotRanks[1], $s->teamFactors)['walks']);
        self::assertFalse($s->likelyToWalk[202]);

        $deal = DealScorer::score($s, $c, 'one_for_one');

        foreach ([1 => [$deal->user, [202 => 1.0]], 2 => [$deal->partner, []]] as $tid => [$side, $factors]) {
            foreach ([1, 2, 3] as $offset) {
                $after = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo());
                $plain = SideGain::fromStates(
                    TeamStateRebuilder::before($s, $tid, $offset),
                    $tid === 1 ? $after['user'] : $after['partner'],
                    $s,
                    $c,
                    $tid,
                    $factors,
                );
                self::assertEqualsWithDelta($plain, $side->components()[$offset], 1e-9, "tid $tid offset $offset");
            }
        }
    }

    public function testReceivedAndSentWalkersMixEveryOutcomeByTheirFactors(): void
    {
        // Team 1 receives two walkers (re-sign odds against team 1, not their own team).
        // Team 2 sends the same two, which are walkers in the snapshot.
        $s = self::smallLeague();
        $c = new TradeCandidate(1, 2, [], [], [207, 208], []);
        $offset = 1;
        $received = [];
        foreach ([207, 208] as $pid) {
            $odds = ResignOdds::forTeam($s->players[$pid], 1, $s->lineups[0], $s->slotRanks[0], $s->teamFactors);
            self::assertTrue($odds['walks']);
            self::assertNotSame($odds['likelihood'], $s->resignLikelihood[$pid]);
            $received[$pid] = ResignLikelihood::futureValueFactor($offset, true, true, $odds['likelihood']);
        }
        $sent = [];
        foreach ([207, 208] as $pid) {
            self::assertTrue($s->likelyToWalk[$pid]);
            $sent[$pid] = ResignLikelihood::futureValueFactor($offset, true, true, $s->resignLikelihood[$pid]);
        }
        $deal = DealScorer::score($s, $c, 'one_for_one');

        $user = self::mixOutcomes($s, $c, 1, $offset, $received, [207, 208], [], true);
        $partner = self::mixOutcomes($s, $c, 2, $offset, [], [207, 208], $sent, false);

        self::assertMixedComponents($user['base'], $user['mixed'], $deal->user->components()[$offset]);
        self::assertMixedComponents($partner['base'], $partner['mixed'], $deal->partner->components()[$offset]);
    }

    public function testSentWalkerWithoutRecordedLikelihoodStaysOnTheRoster(): void
    {
        $s = self::smallLeague();
        $likelihood = $s->resignLikelihood;
        unset($likelihood[202]);
        $walk = $s->likelyToWalk;
        $walk[202] = true;
        $s = new LeagueSnapshot(...[...get_object_vars($s), 'resignLikelihood' => $likelihood, 'likelyToWalk' => $walk]);
        $c = new TradeCandidate(1, 2, [], [], [202], []);

        $deal = DealScorer::score($s, $c, 'one_for_one');

        $after = TeamStateRebuilder::after($s, $c, 1, new PairRecordMemo());
        $plain = SideGain::fromStates(TeamStateRebuilder::before($s, 2, 1), $after['partner'], $s, $c, 2);
        self::assertEqualsWithDelta($plain, $deal->partner->components()[1], 1e-9);
    }

    public function testReceivedPlayerWithNoOffsetZeroSalaryIsNotAWalker(): void
    {
        $in = SnapshotFactory::inputs(4, 13);
        $byTid = $in->playersByTid;
        $byTid[2][] = ProjectionFactory::player(pid: 250, tid: 2, pos: 'PG', salaryByYear: [1 => 0, 2 => 500, 3 => 500, 4 => 0, 5 => 0, 6 => 0]);
        $s = LeagueSnapshotBuilder::build(new LeagueSnapshotInputs(...[...get_object_vars($in), 'playersByTid' => $byTid]));
        self::assertTrue($s->players[250]->onRosterAt(1));
        $c = new TradeCandidate(1, 2, [], [], [250], []);

        $deal = DealScorer::score($s, $c, 'one_for_one');

        $after = TeamStateRebuilder::after($s, $c, 1, new PairRecordMemo());
        $plain = SideGain::fromStates(TeamStateRebuilder::before($s, 1, 1), $after['user'], $s, $c, 1);
        self::assertEqualsWithDelta($plain, $deal->user->components()[1], 1e-9);
    }

    /**
     * Expected mixed components for one side at one offset. Walker `$i` is gone
     * in bit `$i` of the outcome. Received walkers drop out of the after roster,
     * sent walkers out of the before roster.
     *
     * @param array<int, float> $walkFactor received pid => factor, as passed to every outcome
     * @param list<int> $walkers in the scorer's order: received first, then sent
     * @param array<int, float> $sentFactor sent pid => factor
     * @return array{base: array<string, float>, mixed: array<string, float>}
     */
    private static function mixOutcomes(LeagueSnapshot $s, TradeCandidate $c, int $tid, int $offset, array $walkFactor, array $walkers, array $sentFactor, bool $walkersAreReceived): array
    {
        $factors = $walkersAreReceived ? $walkFactor : $sentFactor;
        $mixed = array_fill_keys(DealScorer::MIXED_KEYS, 0.0);
        $count = count($walkers);
        for ($mask = 0; $mask < (1 << $count); $mask++) {
            $weight = 1.0;
            $gone = [];
            foreach ($walkers as $i => $pid) {
                if (($mask & (1 << $i)) === 0) {
                    $weight *= $factors[$pid];
                } else {
                    $weight *= 1.0 - $factors[$pid];
                    $gone[] = $pid;
                }
            }
            $before = $walkersAreReceived || $gone === []
                ? TeamStateRebuilder::before($s, $tid, $offset)
                : TeamStateRebuilder::beforeWithout($s, $tid, $offset, new PairRecordMemo(), $gone);
            $afterPair = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo(), $walkersAreReceived ? $gone : []);
            $outcome = SideGain::fromStates($before, $tid === $c->userTid ? $afterPair['user'] : $afterPair['partner'], $s, $c, $tid, $walkFactor);
            foreach (DealScorer::MIXED_KEYS as $key) {
                $mixed[$key] += $weight * $outcome[$key];
            }
        }
        $afterPair = TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo());
        $base = SideGain::fromStates(
            TeamStateRebuilder::before($s, $tid, $offset),
            $tid === $c->userTid ? $afterPair['user'] : $afterPair['partner'],
            $s,
            $c,
            $tid,
            $walkFactor,
        );

        return ['base' => $base, 'mixed' => $mixed];
    }

    /**
     * @param array<string, float> $base
     * @param array<string, float> $mixed
     * @param array<string, float> $actual
     */
    private static function assertMixedComponents(array $base, array $mixed, array $actual): void
    {
        foreach (SideGain::KEYS as $key) {
            $expected = $mixed[$key] ?? $base[$key];
            self::assertEqualsWithDelta($expected, $actual[$key], 1e-9, $key);
        }
        // At least one mixed component must differ from the unmixed base, or the test proves nothing.
        $differs = false;
        foreach (DealScorer::MIXED_KEYS as $key) {
            $differs = $differs || abs($base[$key] - $mixed[$key]) > 1e-9;
        }
        self::assertTrue($differs);
    }

    private static ?LeagueSnapshot $small = null;

    private static function smallLeague(): LeagueSnapshot
    {
        return self::$small ??= SnapshotFactory::league(4, 13);
    }

    private static function swap(LeagueSnapshot $s): TradeCandidate
    {
        $userPid = array_keys($s->lineups[0][1]->players)[0];
        $partnerPid = array_keys($s->lineups[0][2]->players)[0];

        return new TradeCandidate(1, 2, [$userPid], [], [$partnerPid], []);
    }

    /**
     * @param array<int, TeamMode> $modes
     */
    private static function leagueWith(array $modes, ?int $gamesLeft): LeagueSnapshot
    {
        $in = SnapshotFactory::inputs();
        $standings = $in->standings;
        if ($gamesLeft !== null) {
            foreach ($standings as $tid => $row) {
                $row['games_unplayed'] = $gamesLeft;
                $standings[$tid] = $row;
            }
        }

        return LeagueSnapshotBuilder::build(new LeagueSnapshotInputs(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            playersByTid: $in->playersByTid,
            scale: $in->scale,
            standings: $standings,
            streaks: $in->streaks,
            teamFactors: $in->teamFactors,
            cashByTeamAndOffset: $in->cashByTeamAndOffset,
            pickRows: $in->pickRows,
            draftOrder: $in->draftOrder,
            modes: array_replace($in->modes, $modes),
            teamNames: $in->teamNames,
        ));
    }
}
