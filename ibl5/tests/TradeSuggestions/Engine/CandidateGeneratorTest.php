<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\CandidateBudget;
use TradeSuggestions\Engine\CandidateGenerator;
use TradeSuggestions\Engine\CandidateKey;
use TradeSuggestions\Engine\ExposureFilter;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Engine\LegalityPreFilter;
use TradeSuggestions\Engine\Shape\ChangeOfScenery;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Engine\Shape\DumpPaidWithPick;
use TradeSuggestions\Engine\Shape\ExpiringForLong;
use TradeSuggestions\Engine\Shape\OneForOne;
use TradeSuggestions\Engine\Shape\OneForTwo;
use TradeSuggestions\Engine\Shape\PickSwap;
use TradeSuggestions\Engine\Shape\StarForYoungAndPicks;
use TradeSuggestions\Engine\Shape\TwoForOne;
use TradeSuggestions\Engine\SurplusDeficitMatcher;
use TradeSuggestions\TradeCandidate;

final class CandidateGeneratorTest extends TestCase
{
    private const string SIM_KEY = 'sim-key-1';

    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        return self::$league ??= SnapshotFactory::league();
    }

    public function testNoEligiblePartnerYieldsEmptyResult(): void
    {
        $generator = new CandidateGenerator([self::fake('A', static fn (): array => [])]);

        $result = $generator->generate(self::league(), 99, self::SIM_KEY);

        self::assertSame([], $result->candidates);
        self::assertSame([], $result->partners);
        self::assertSame(0, $result->rawCount);
        self::assertFalse($result->capped);
    }

    public function testPartnerWithNoNeedPairIsSkipped(): void
    {
        $rosters = [];
        foreach ([1, 2] as $tid) {
            foreach (['PG', 'SG', 'SF', 'PF', 'C', 'PG', 'SG', 'SF', 'PF', 'C'] as $i => $pos) {
                $rosters[$tid][] = ProjectionFactory::player(pid: $tid * 100 + $i + 1, tid: $tid, pos: $pos, dcMinutes: $i < 5 ? 30 : 10);
            }
        }
        $s = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(2, 13, $rosters));
        self::assertSame([2], ExposureFilter::eligiblePartners(self::SIM_KEY, 1, $s->teamIds));
        self::assertSame([], SurplusDeficitMatcher::needPairs($s, 1, 2));
        $calls = 0;
        $generator = new CandidateGenerator([self::fake('A', static function () use (&$calls): array {
            $calls++;

            return [];
        })]);

        $result = $generator->generate($s, 1, self::SIM_KEY);

        self::assertSame([], $result->partners);
        self::assertSame(0, $calls);
    }

    public function testPartnerWithNoNeedPairDoesNotStopLaterPartners(): void
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C', 'PG', 'SG', 'SF', 'PF', 'C'];
        $rosters = [];
        foreach ([1, 2] as $tid) {
            foreach ($positions as $i => $pos) {
                $rosters[$tid][] = ProjectionFactory::player(pid: $tid * 100 + $i + 1, tid: $tid, pos: $pos, dcMinutes: $i < 5 ? 30 : 10);
            }
        }
        foreach ($positions as $i => $pos) {
            $rosters[3][] = ProjectionFactory::player(pid: 301 + $i, tid: 3, pos: 'C', talent: 90, age: 22, dcMinutes: $i < 5 ? 30 : 10);
        }
        $s = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(3, 13, $rosters));
        self::assertSame([2, 3], ExposureFilter::eligiblePartners(self::SIM_KEY, 1, $s->teamIds));
        self::assertSame([], SurplusDeficitMatcher::needPairs($s, 1, 2));
        self::assertNotSame([], SurplusDeficitMatcher::needPairs($s, 1, 3));
        $pools = [];
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c, AssetPool $pool) use (&$pools): array {
            $pools[] = $pool;

            return [];
        })]);

        $result = $generator->generate($s, 1, self::SIM_KEY);

        self::assertSame([3], $result->partners);
        self::assertCount(1, $pools);
        foreach ([$pools[0]->userPids, $pools[0]->partnerPids] as $pids) {
            self::assertLessThan(CandidateBudget::PLAYERS_PER_PARTNER, count($pids));
            self::assertTrue(array_is_list($pids));
        }
        self::assertNotSame([], array_merge($pools[0]->userPids, $pools[0]->partnerPids));
    }

    public function testAssetPoolHoldsAtMostSixPlayersPerSide(): void
    {
        $sizes = [];
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c, AssetPool $pool) use (&$sizes): array {
            $sizes[] = count($pool->userPids);
            $sizes[] = count($pool->partnerPids);
            self::assertSame(array_values(array_unique($pool->userPids)), $pool->userPids);

            return [];
        })]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertNotSame([], $result->partners);
        self::assertSame(CandidateBudget::PLAYERS_PER_PARTNER, max($sizes));
    }

    public function testOnlyRoundOneAndTwoPicksEnterPool(): void
    {
        $base = SnapshotFactory::inputs();
        $pickRows = $base->pickRows;
        $pickRows[1][] = ['pickid' => 13, 'owner_teamid' => 1, 'teampick_teamid' => 1, 'year' => SnapshotFactory::SEASON_YEAR, 'round' => 3];
        $s = LeagueSnapshotBuilder::build(self::withPickRows($base, $pickRows));
        $seen = [];
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c, AssetPool $pool) use (&$seen): array {
            foreach ($pool->userPicks as $pick) {
                $seen[$pick->pickId] = $pick->round;
            }

            return [];
        })]);

        $generator->generate($s, 1, self::SIM_KEY);

        self::assertArrayHasKey(11, $seen);
        self::assertArrayHasKey(12, $seen);
        self::assertArrayNotHasKey(13, $seen);
    }

    public function testRawCapStopsAtFourThousand(): void
    {
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c): array {
            $out = [];
            for ($i = 1; $i <= 5000; $i++) {
                $out[] = new TradeCandidate($c->userTid, $c->partnerTid, [$i], [], [], []);
            }

            return $out;
        })]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertSame(CandidateBudget::MAX_RAW_CANDIDATES, $result->rawCount);
        self::assertTrue($result->capped);
        self::assertSame(CandidateBudget::MAX_RAW_CANDIDATES, $result->prefilterRejected + count($result->candidates));
    }

    public function testDuplicateCandidatesAreCountedOnce(): void
    {
        $deal = self::pickSwapFactory();
        $generator = new CandidateGenerator([self::fake('A', $deal), self::fake('B', $deal)]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertCount(1, $result->candidates);
        self::assertSame(1, $result->rawCount);
    }

    public function testShapeIdByKeyHoldsFirstYieldingShapePerKeptCandidate(): void
    {
        $fired = false;
        $first = static function (TradeCandidate $c) use (&$fired): array {
            if ($fired) {
                return [];
            }
            $fired = true;

            return [
                new TradeCandidate($c->userTid, $c->partnerTid, [], [$c->userTid * 10 + 1], [], [$c->partnerTid * 10 + 1]),
                new TradeCandidate($c->userTid, $c->partnerTid, [], [$c->partnerTid * 10 + 2], [], []),
            ];
        };
        $generator = new CandidateGenerator([self::fake('A', $first), self::fake('B', self::pickSwapFactory())]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertCount(1, $result->candidates);
        $kept = CandidateKey::of($result->candidates[0]);
        self::assertSame([$kept => 'A'], $result->shapeIdByKey);
        self::assertSame(2, $result->rawCount);
        self::assertSame(['OWNERSHIP' => 1], $result->rejectedByReason);
    }

    public function testPartnersAreEveryEligiblePartnerWithANeedPair(): void
    {
        $s = self::league();
        $expected = [];
        foreach (ExposureFilter::eligiblePartners(self::SIM_KEY, 1, $s->teamIds) as $tid) {
            if (SurplusDeficitMatcher::needPairs($s, 1, $tid) !== []) {
                $expected[] = $tid;
            }
        }
        $generator = new CandidateGenerator([self::fake('A', static fn (): array => [])]);

        $result = $generator->generate($s, 1, self::SIM_KEY);

        self::assertGreaterThan(1, count($expected));
        self::assertSame($expected, $result->partners);
    }

    public function testAssetPoolsTakeTheGiversPairsInNeedPairOrderAsAList(): void
    {
        $s = self::league();
        $pools = [];
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c, AssetPool $pool) use (&$pools): array {
            $pools[$c->partnerTid] = $pool;

            return [];
        })]);

        $generator->generate($s, 1, self::SIM_KEY);

        self::assertNotSame([], $pools);
        foreach ($pools as $partnerTid => $pool) {
            $pairs = SurplusDeficitMatcher::needPairs($s, 1, $partnerTid);
            self::assertSame(self::expectedPool($s, 1, $pairs), $pool->userPids, "user pool vs {$partnerTid}");
            self::assertSame(self::expectedPool($s, $partnerTid, $pairs), $pool->partnerPids, "partner pool vs {$partnerTid}");
        }
    }

    public function testDuplicateDoesNotStopTheShapesRemainingCandidates(): void
    {
        $first = self::pickSwapFactory();
        $second = static function (TradeCandidate $c) use ($first): array {
            $swap = $first($c);
            if ($swap === []) {
                return [];
            }

            return [
                $swap[0],
                new TradeCandidate($c->userTid, $c->partnerTid, [], [$c->userTid * 10 + 2], [], [$c->partnerTid * 10 + 2]),
            ];
        };
        $generator = new CandidateGenerator([self::fake('A', $first), self::fake('B', $second)]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertSame(2, $result->rawCount);
        self::assertCount(2, $result->candidates);
    }

    public function testOversizedCandidateDoesNotStopTheShapesRemainingCandidates(): void
    {
        $swap = self::pickSwapFactory();
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c) use ($swap): array {
            return [new TradeCandidate($c->userTid, $c->partnerTid, [101, 102, 103], [], [], []), ...$swap($c)];
        })]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertSame(1, $result->rawCount);
        self::assertCount(1, $result->candidates);
    }

    public function testPartnerSideAssetCountBoundary(): void
    {
        $partner = self::firstPartner();
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c) use ($partner): array {
            if ($c->partnerTid !== $partner) {
                return [];
            }
            $p = $c->partnerTid * 10;

            return [
                new TradeCandidate($c->userTid, $c->partnerTid, [], [], [$p + 5], [$p + 1, $p + 2]),
                new TradeCandidate($c->userTid, $c->partnerTid, [], [], [], [$p + 1, $p + 2]),
            ];
        })]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertSame(1, $result->rawCount);
    }

    public function testRejectionsOfOneReasonAreTallied(): void
    {
        $partner = self::firstPartner();
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c) use ($partner): array {
            if ($c->partnerTid !== $partner) {
                return [];
            }

            return [
                new TradeCandidate($c->userTid, $c->partnerTid, [], [$c->partnerTid * 10 + 1], [], []),
                new TradeCandidate($c->userTid, $c->partnerTid, [], [$c->partnerTid * 10 + 2], [], []),
            ];
        })]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertSame(['OWNERSHIP' => 2], $result->rejectedByReason);
        self::assertSame(2, $result->prefilterRejected);
    }

    public function testCandidateKeyIgnoresPidOrder(): void
    {
        self::assertSame(
            CandidateKey::of(new TradeCandidate(1, 2, [3, 1], [12, 11], [205, 201], [22])),
            CandidateKey::of(new TradeCandidate(1, 2, [1, 3], [11, 12], [201, 205], [22])),
        );
        self::assertNotSame(
            CandidateKey::of(new TradeCandidate(1, 2, [1], [], [3], [])),
            CandidateKey::of(new TradeCandidate(1, 2, [3], [], [1], [])),
        );
    }

    public function testPrefilterRejectionsAreCountedByReason(): void
    {
        $roster = [];
        for ($i = 1; $i <= 10; $i++) {
            $roster[] = ProjectionFactory::player(
                pid: 100 + $i,
                tid: 1,
                pos: ['PG', 'SG', 'SF', 'PF', 'C'][($i - 1) % 5],
                salaryByYear: [1 => 680, 2 => 680, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                dcMinutes: 20,
            );
        }
        $s = LeagueSnapshotBuilder::build(SnapshotFactory::inputs(28, 14, [1 => $roster]));
        self::assertSame(6800, $s->payrollByOffset[0][1]);
        $fired = false;
        $generator = new CandidateGenerator([self::fake('A', static function (TradeCandidate $c) use (&$fired, $s): array {
            if ($fired) {
                return [];
            }
            $fired = true;
            $partnerPids = array_keys($s->lineups[0][$c->partnerTid]->players);
            usort($partnerPids, static fn (int $a, int $b): int => $s->players[$b]->salaryAt(0) <=> $s->players[$a]->salaryAt(0));
            self::assertCount(14, $partnerPids);
            self::assertGreaterThan(200, $s->players[$partnerPids[0]]->salaryAt(0));

            return [
                new TradeCandidate($c->userTid, $c->partnerTid, [101, 102], [], [], []),
                new TradeCandidate($c->userTid, $c->partnerTid, [], [], [$partnerPids[0]], []),
            ];
        })]);

        $result = $generator->generate($s, 1, self::SIM_KEY);

        self::assertSame(['ROSTER' => 1, 'CAP' => 1], $result->rejectedByReason);
        self::assertSame(2, $result->prefilterRejected);
        self::assertSame([], $result->candidates);
    }

    public function testThreeAssetSideIsDropped(): void
    {
        $generator = new CandidateGenerator([self::fake('A', static fn (TradeCandidate $c): array => [
            new TradeCandidate($c->userTid, $c->partnerTid, [101, 102, 103], [], [], []),
            new TradeCandidate($c->userTid, $c->partnerTid, [101, 102], [11], [], []),
        ])]);

        $result = $generator->generate(self::league(), 1, self::SIM_KEY);

        self::assertSame(0, $result->rawCount);
        self::assertSame([], $result->candidates);
    }

    public function testEndToEndWithRealShapesProducesOnlyLegalTwoTeamCandidates(): void
    {
        $s = self::league();
        $generator = new CandidateGenerator([
            new OneForOne(),
            new TwoForOne(),
            new OneForTwo(),
            new DumpPaidWithPick(),
            new StarForYoungAndPicks(),
            new ExpiringForLong(),
            new PickSwap(),
            new ChangeOfScenery(),
        ]);

        $result = $generator->generate($s, 1, self::SIM_KEY);

        self::assertNotSame([], $result->candidates);
        self::assertLessThanOrEqual(CandidateBudget::MAX_RAW_CANDIDATES, $result->rawCount);
        self::assertCount(count($result->candidates), $result->shapeIdByKey);
        foreach ($result->candidates as $c) {
            self::assertTrue(LegalityPreFilter::passes($s, $c));
            self::assertSame(1, $c->userTid);
            self::assertContains($c->partnerTid, $result->partners);
            self::assertContains($c->partnerTid, $s->teamIds);
        }
    }

    /**
     * Yields one legal pick-for-pick swap per partner: the user's round-1 pick
     * for the partner's round-1 pick moves no salary and no roster spot.
     *
     * @return \Closure(TradeCandidate): list<TradeCandidate>
     */
    private static function pickSwapFactory(): \Closure
    {
        return static function (TradeCandidate $c): array {
            if ($c->partnerTid !== self::firstPartner()) {
                return [];
            }

            return [new TradeCandidate($c->userTid, $c->partnerTid, [], [$c->userTid * 10 + 1], [], [$c->partnerTid * 10 + 1])];
        };
    }

    /**
     * The side's players: up to six distinct, taking only the pairs it gives, in pair order.
     *
     * @param list<\TradeSuggestions\Engine\NeedPair> $pairs
     * @return list<int>
     */
    private static function expectedPool(LeagueSnapshot $s, int $tid, array $pairs): array
    {
        $pool = [];
        foreach ($pairs as $pair) {
            if ($pair->giverTid !== $tid) {
                continue;
            }
            foreach (SurplusDeficitMatcher::assetsFor($s, $tid, $pair, CandidateBudget::PLAYERS_PER_PARTNER) as $pid) {
                if (!in_array($pid, $pool, true)) {
                    $pool[] = $pid;
                }
                if (count($pool) === CandidateBudget::PLAYERS_PER_PARTNER) {
                    return $pool;
                }
            }
        }

        return $pool;
    }

    private static function firstPartner(): int
    {
        $s = self::league();
        foreach (\TradeSuggestions\Engine\ExposureFilter::eligiblePartners(self::SIM_KEY, 1, $s->teamIds) as $tid) {
            if (SurplusDeficitMatcher::needPairs($s, 1, $tid) !== []) {
                return $tid;
            }
        }
        self::fail('no partner with need pairs');
    }

    /**
     * A scripted shape. The script receives an empty candidate carrying the
     * two tids, and the pool.
     *
     * @param \Closure(TradeCandidate, AssetPool): list<TradeCandidate> $script
     */
    private static function fake(string $id, \Closure $script): DealShapeInterface
    {
        return new class ($id, $script) implements DealShapeInterface {
            /**
             * @param \Closure(TradeCandidate, AssetPool): list<TradeCandidate> $script
             */
            public function __construct(private readonly string $id, private readonly \Closure $script)
            {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function generate(LeagueSnapshot $s, int $userTid, int $partnerTid, AssetPool $pool): iterable
            {
                yield from ($this->script)(new TradeCandidate($userTid, $partnerTid, [], [], [], []), $pool);
            }
        };
    }

    /**
     * @param array<int, list<array{pickid: int, owner_teamid: int, teampick_teamid: int, year: int, round: int}>> $pickRows
     */
    private static function withPickRows(LeagueSnapshotInputs $in, array $pickRows): LeagueSnapshotInputs
    {
        return new LeagueSnapshotInputs(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            playersByTid: $in->playersByTid,
            scale: $in->scale,
            standings: $in->standings,
            streaks: $in->streaks,
            teamFactors: $in->teamFactors,
            cashByTeamAndOffset: $in->cashByTeamAndOffset,
            pickRows: $pickRows,
            draftOrder: $in->draftOrder,
            modes: $in->modes,
            teamNames: $in->teamNames,
        );
    }
}
