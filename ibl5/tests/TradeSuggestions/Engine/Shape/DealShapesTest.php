<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\NeedPair;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\Shape\ChangeOfScenery;
use TradeSuggestions\Engine\Shape\Contracts\DealShapeInterface;
use TradeSuggestions\Engine\Shape\DumpPaidWithPick;
use TradeSuggestions\Engine\Shape\ExpiringForLong;
use TradeSuggestions\Engine\Shape\OneForOne;
use TradeSuggestions\Engine\Shape\OneForTwo;
use TradeSuggestions\Engine\Shape\PickSwap;
use TradeSuggestions\Engine\Shape\StarForYoungAndPicks;
use TradeSuggestions\Engine\Shape\TwoForOne;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\Projection\ProjectedSeason;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\ContractValue;

/**
 * Deal shapes read only the asset pool and the snapshot. The rich scenario has
 * user team 3 (Rebuild) against partner team 1 (WinNow), with a pool that
 * makes every shape's pattern present.
 */
final class DealShapesTest extends TestCase
{
    private const int USER = 3;
    private const int PARTNER = 1;
    private const int USER_EXPIRING = 301;
    private const int USER_NEGATIVE = 302;
    private const int PARTNER_YOUNG = 102;
    private const int PARTNER_LONG = 103;
    private const int PARTNER_EXPIRING = 104;
    private const int USER_BURIED = 304;
    private const int PARTNER_BURIED = 105;

    /**
     * @return iterable<string, array{DealShapeInterface}>
     */
    public static function shapeProvider(): iterable
    {
        foreach (self::idProvider() as $name => [$shape]) {
            yield $name => [$shape];
        }
    }

    /**
     * @return iterable<string, array{DealShapeInterface, string}>
     */
    public static function idProvider(): iterable
    {
        yield 'one for one' => [new OneForOne(), 'ONE_FOR_ONE'];
        yield 'two for one' => [new TwoForOne(), 'TWO_FOR_ONE'];
        yield 'one for two' => [new OneForTwo(), 'ONE_FOR_TWO'];
        yield 'dump paid with pick' => [new DumpPaidWithPick(), 'DUMP_PAID_WITH_PICK'];
        yield 'star for young and picks' => [new StarForYoungAndPicks(), 'STAR_FOR_YOUNG_AND_PICKS'];
        yield 'expiring for long' => [new ExpiringForLong(), 'EXPIRING_FOR_LONG'];
        yield 'pick swap' => [new PickSwap(), 'PICK_SWAP'];
        yield 'change of scenery' => [new ChangeOfScenery(), 'CHANGE_OF_SCENERY'];
    }

    #[DataProvider('idProvider')]
    public function testEveryShapeHasAStableUpperSnakeId(DealShapeInterface $shape, string $expectedId): void
    {
        self::assertSame($expectedId, $shape->id());
        self::assertMatchesRegularExpression('/^[A-Z]+(_[A-Z]+)*$/', $shape->id());
    }

    #[DataProvider('shapeProvider')]
    public function testEveryShapeYieldsAtMostTwoAssetsPerSide(DealShapeInterface $shape): void
    {
        [$s, $pool] = self::rich();
        $candidates = self::collect($shape, $s, $pool);

        self::assertNotSame([], $candidates, 'the rich scenario must fire ' . $shape->id());
        foreach ($candidates as $c) {
            self::assertLessThanOrEqual(2, count($c->userPids) + count($c->userPickIds));
            self::assertLessThanOrEqual(2, count($c->partnerPids) + count($c->partnerPickIds));
        }
    }

    #[DataProvider('shapeProvider')]
    public function testEveryShapeYieldsTwoTeamCandidatesOnly(DealShapeInterface $shape): void
    {
        [$s, $pool] = self::rich();
        $userPickIds = array_map(static fn (PickAsset $p): int => $p->pickId, $pool->userPicks);
        $partnerPickIds = array_map(static fn (PickAsset $p): int => $p->pickId, $pool->partnerPicks);

        $candidates = self::collect($shape, $s, $pool);
        self::assertNotSame([], $candidates);
        foreach ($candidates as $c) {
            self::assertSame(self::USER, $c->userTid);
            self::assertSame(self::PARTNER, $c->partnerTid);
            self::assertSame([], array_diff($c->userPids, $pool->userPids));
            self::assertSame([], array_diff($c->partnerPids, $pool->partnerPids));
            self::assertSame([], array_diff($c->userPickIds, $userPickIds));
            self::assertSame([], array_diff($c->partnerPickIds, $partnerPickIds));
        }
    }

    #[DataProvider('shapeProvider')]
    public function testPidListsAreSortedAscending(DealShapeInterface $shape): void
    {
        [$s, $pool] = self::rich();
        $candidates = self::collect($shape, $s, $pool);

        self::assertNotSame([], $candidates);
        foreach ($candidates as $c) {
            $user = $c->userPids;
            $partner = $c->partnerPids;
            sort($user);
            sort($partner);
            self::assertSame($user, $c->userPids);
            self::assertSame($partner, $c->partnerPids);
        }
    }

    #[DataProvider('shapeProvider')]
    public function testShapeWithAbsentPatternYieldsNothing(DealShapeInterface $shape): void
    {
        [$s] = self::rich();

        self::assertSame([], self::collect($shape, $s, new AssetPool()));
    }

    /**
     * @return iterable<string, array{DealShapeInterface, list<NeedPair>}>
     */
    public static function missingPatternProvider(): iterable
    {
        $userGives = new NeedPair(0, NeedPair::KIND_SLOT, 'PG', self::USER, self::PARTNER, 3, 20, 1.0);
        $otherOffset = new NeedPair(1, NeedPair::KIND_SLOT, 'SF', self::PARTNER, self::USER, 3, 20, 1.0);
        yield 'one for one, one-sided need' => [new OneForOne(), [$userGives]];
        yield 'one for one, offsets differ' => [new OneForOne(), [$userGives, $otherOffset]];
    }

    /**
     * @param list<NeedPair> $pairs
     */
    #[DataProvider('missingPatternProvider')]
    public function testNeedPairShapeYieldsNothingWhenItsPatternIsMissing(DealShapeInterface $shape, array $pairs): void
    {
        [$s, $pool] = self::rich();
        $thin = new AssetPool($pool->userPids, $pool->partnerPids, $pool->userPicks, $pool->partnerPicks, $pairs);

        self::assertSame([], self::collect($shape, $s, $thin));
    }

    /**
     * Giver and receiver slot ranks at offset 0. A rank of 12 is neither
     * strong nor weak; 1 and 2 are strong; 25 is weak.
     *
     * @return iterable<string, array{DealShapeInterface, bool, array<string, int>, array<string, list<int>>, array<string, int>, bool}>
     */
    public static function buriedOrDepthProvider(): iterable
    {
        $mid = ['PG' => 12, 'SG' => 12, 'SF' => 12, 'PF' => 12, 'C' => 12];
        $oneStrong = ['PG' => 1] + $mid;
        $twoStrong = ['PG' => 1, 'SF' => 2] + $mid;
        $weakSg = ['SG' => 25] + $mid;
        foreach ([[new TwoForOne(), true, 'two for one'], [new OneForTwo(), false, 'one for two']] as [$shape, $userGives, $name]) {
            yield "{$name}, weak slot, one surplus slot, no buried player" => [$shape, $userGives, $oneStrong, [], $weakSg, false];
            yield "{$name}, buried player at a slot the receiver is not weak at" => [$shape, $userGives, $oneStrong, ['PF' => [1]], $weakSg, false];
            yield "{$name}, buried player but the receiver has no weak slot" => [$shape, $userGives, $twoStrong, ['SG' => [1]], $mid, false];
            yield "{$name}, buried player at the weak slot with a mid starter" => [$shape, $userGives, $mid, ['SG' => [1]], $weakSg, true];
            yield "{$name}, two surplus slots and a weak slot" => [$shape, $userGives, $twoStrong, [], $weakSg, true];
        }
    }

    /**
     * @param array<string, int> $giverRanks
     * @param array<string, list<int>> $giverBuried slot => buried pids (the pids are placeholders)
     * @param array<string, int> $receiverRanks
     */
    #[DataProvider('buriedOrDepthProvider')]
    public function testTwoForOneShapesFireOnlyOnBuriedPlayerOrTwoSurplusSlots(
        DealShapeInterface $shape,
        bool $userGives,
        array $giverRanks,
        array $giverBuried,
        array $receiverRanks,
        bool $fires,
    ): void {
        [$s, $pool] = self::rich();
        $giver = $userGives ? self::USER : self::PARTNER;
        $receiver = $userGives ? self::PARTNER : self::USER;
        $positionStrength = $s->positionStrength;
        $positionStrength[0][$giver] = self::strengths($giverRanks, $giverBuried);
        $positionStrength[0][$receiver] = self::strengths($receiverRanks, []);
        $s = self::with($s, ['positionStrength' => $positionStrength]);

        self::assertSame($fires, self::collect($shape, $s, $pool) !== []);
    }

    public function testChangeOfSceneryYieldsNothingWhenPartnerSlotIsNotWeak(): void
    {
        [$s, $pool] = self::rich();
        $positionStrength = $s->positionStrength;
        $positionStrength[0][self::PARTNER]['SG'] = new SlotStrength('SG', 1, null, 3, false, []);
        $s = self::with($s, ['positionStrength' => $positionStrength]);

        self::assertSame([], self::collect(new ChangeOfScenery(), $s, $pool));
    }

    public function testOneForOneYieldsSixteenFromFourByFour(): void
    {
        [$s, $pool] = self::rich();
        $candidates = self::collect(new OneForOne(), $s, $pool);

        self::assertCount(16, $candidates);
        self::assertCount(4, array_unique(array_map(static fn (TradeCandidate $c): int => $c->userPids[0], $candidates)));
        self::assertCount(4, array_unique(array_map(static fn (TradeCandidate $c): int => $c->partnerPids[0], $candidates)));
    }

    public function testTwoForOneAndOneForTwoYieldThirtyEach(): void
    {
        [$s, $pool] = self::rich();

        $twoForOne = self::collect(new TwoForOne(), $s, $pool);
        self::assertCount(30, $twoForOne);
        foreach ($twoForOne as $c) {
            self::assertCount(2, $c->userPids);
            self::assertCount(1, $c->partnerPids);
        }

        $oneForTwo = self::collect(new OneForTwo(), $s, $pool);
        self::assertCount(30, $oneForTwo);
        foreach ($oneForTwo as $c) {
            self::assertCount(1, $c->userPids);
            self::assertCount(2, $c->partnerPids);
        }
    }

    public function testExpiringForLongRefusedWhenLongContractNotTrulyBad(): void
    {
        [$s, $pool] = self::rich(longOverpay: ContractValue::BAD_CONTRACT_FLOOR);

        self::assertSame(
            ContractValue::BAD_CONTRACT_FLOOR,
            ContractValue::negativeSurplusBeyondCurrent($s->surplusBySeason(self::PARTNER_LONG)),
        );
        self::assertSame([], self::collect(new ExpiringForLong(), $s, $pool));
    }

    public function testExpiringForLongFiresWhenLongContractTrulyBad(): void
    {
        [$s, $pool] = self::rich(longOverpay: ContractValue::BAD_CONTRACT_FLOOR + 1);

        $candidates = self::collect(new ExpiringForLong(), $s, $pool);

        self::assertCount(1, $candidates);
        self::assertSame([self::USER_EXPIRING], $candidates[0]->userPids);
        self::assertSame([self::PARTNER_LONG], $candidates[0]->partnerPids);
        self::assertSame([], $candidates[0]->userPickIds);
        self::assertSame([11], $candidates[0]->partnerPickIds);
    }

    public function testPickSwapYieldsNothingWhenModesMatch(): void
    {
        [$s, $pool] = self::rich();
        $s = self::with($s, ['modes' => [self::USER => TeamMode::WinNow, self::PARTNER => TeamMode::WinNow] + $s->modes]);

        self::assertSame([], self::collect(new PickSwap(), $s, $pool));
    }

    public function testPickSwapFiresForMiddleAgainstWinNow(): void
    {
        [$s, $pool] = self::rich();
        $s = self::with($s, ['modes' => [self::USER => TeamMode::Middle, self::PARTNER => TeamMode::WinNow] + $s->modes]);

        $candidates = self::collect(new PickSwap(), $s, $pool);

        self::assertCount(2, $candidates);
        self::assertSame([31], $candidates[0]->userPickIds);
        self::assertSame([11], $candidates[0]->partnerPickIds);
        // The user moves the nearer pick (yearsAway 0), so the round-2 sweetener is his.
        self::assertSame([31, 32], $candidates[1]->userPickIds);
        self::assertSame([11], $candidates[1]->partnerPickIds);
    }

    public function testDumpPaidWithPickAllowsEmptyPartnerSide(): void
    {
        [$s, $pool] = self::rich();
        $noPartner = new AssetPool($pool->userPids, [], $pool->userPicks, $pool->partnerPicks, []);

        $candidates = self::collect(new DumpPaidWithPick(), $s, $noPartner);

        $dumps = array_values(array_filter($candidates, static fn (TradeCandidate $c): bool => $c->userPids === [self::USER_NEGATIVE]));
        self::assertCount(1, $dumps);
        self::assertSame([32], $dumps[0]->userPickIds);
        self::assertSame([], $dumps[0]->partnerPids);
        self::assertSame([], $dumps[0]->partnerPickIds);
        foreach ($candidates as $c) {
            self::assertSame([], $c->partnerPids);
        }

        $withPartner = array_values(array_filter(
            self::collect(new DumpPaidWithPick(), $s, $pool),
            static fn (TradeCandidate $c): bool => $c->userPids === [self::USER_NEGATIVE],
        ));
        self::assertCount(2, $withPartner);
        self::assertSame([self::PARTNER_EXPIRING], $withPartner[1]->partnerPids);
    }

    public function testDumpPaidWithPickRefusedWhenGiverPayrollNotInBottomTier(): void
    {
        [$s, $pool] = self::rich();
        $s = self::with($s, ['payrollRanks' => [1 => [self::USER => 10, self::PARTNER => 2]] + $s->payrollRanks]);

        self::assertSame([], self::collect(new DumpPaidWithPick(), $s, $pool));
    }

    public function testStarForYoungRequiresRebuildAgainstWinNow(): void
    {
        [$s, $pool] = self::rich();

        $forward = self::collect(new StarForYoungAndPicks(), $s, $pool);
        self::assertCount(1, $forward);
        self::assertSame([self::PARTNER_YOUNG], $forward[0]->partnerPids);
        self::assertSame([11], $forward[0]->partnerPickIds);
        self::assertSame([], $forward[0]->userPickIds);

        $mirror = self::with($s, ['modes' => [self::USER => TeamMode::WinNow, self::PARTNER => TeamMode::Rebuild] + $s->modes]);
        $mirrored = self::collect(new StarForYoungAndPicks(), $mirror, $pool);
        self::assertCount(1, $mirrored);
        self::assertSame([31], $mirrored[0]->userPickIds);

        $same = self::with($s, ['modes' => [self::USER => TeamMode::Rebuild, self::PARTNER => TeamMode::Rebuild] + $s->modes]);
        self::assertSame([], self::collect(new StarForYoungAndPicks(), $same, $pool));
        $winners = self::with($s, ['modes' => [self::USER => TeamMode::WinNow, self::PARTNER => TeamMode::WinNow] + $s->modes]);
        self::assertSame([], self::collect(new StarForYoungAndPicks(), $winners, $pool));
    }

    public function testStarForYoungNeverFiresForMiddle(): void
    {
        [$s, $pool] = self::rich();
        $combos = [
            [TeamMode::Middle, TeamMode::WinNow],
            [TeamMode::Middle, TeamMode::Rebuild],
            [TeamMode::WinNow, TeamMode::Middle],
            [TeamMode::Rebuild, TeamMode::Middle],
            [TeamMode::Middle, TeamMode::Middle],
        ];
        foreach ($combos as [$userMode, $partnerMode]) {
            $variant = self::with($s, ['modes' => [self::USER => $userMode, self::PARTNER => $partnerMode] + $s->modes]);
            self::assertSame([], self::collect(new StarForYoungAndPicks(), $variant, $pool), $userMode->name . ' vs ' . $partnerMode->name);
        }
    }

    public function testChangeOfSceneryPairsBuriedPlayersAtDifferentSlots(): void
    {
        [$s, $pool] = self::rich();

        $candidates = self::collect(new ChangeOfScenery(), $s, $pool);

        $pairs = array_map(static fn (TradeCandidate $c): array => [$c->userPids[0], $c->partnerPids[0]], $candidates);
        self::assertContains([self::USER_BURIED, self::PARTNER_BURIED], $pairs);
        foreach ($candidates as $c) {
            self::assertSame([self::USER_BURIED], $c->userPids);
        }
    }

    /**
     * @param array<string, int> $ranks slot => starter rank
     * @param array<string, list<int>> $buried slot => buried pids
     * @return array<string, SlotStrength>
     */
    private static function strengths(array $ranks, array $buried): array
    {
        $out = [];
        foreach ($ranks as $slot => $rank) {
            $out[$slot] = new SlotStrength($slot, $rank, null, 3, false, $buried[$slot] ?? []);
        }

        return $out;
    }

    /**
     * @return list<TradeCandidate>
     */
    private static function collect(DealShapeInterface $shape, LeagueSnapshot $s, AssetPool $pool): array
    {
        return iterator_to_array($shape->generate($s, self::USER, self::PARTNER, $pool), false);
    }

    /**
     * @return array{LeagueSnapshot, AssetPool}
     */
    private static function rich(int $longOverpay = ContractValue::BAD_CONTRACT_FLOOR + 1): array
    {
        $inputs = SnapshotFactory::inputs(
            28,
            13,
            [
                self::PARTNER => self::roster(self::PARTNER, [self::PARTNER_YOUNG => 22], [self::PARTNER_EXPIRING]),
                self::USER => self::roster(self::USER, [306 => 23], [self::USER_EXPIRING]),
            ],
        );
        $s = LeagueSnapshotBuilder::build($inputs);

        $userStarter = $s->lineup(0, self::USER)->starterPid('PG');
        $partnerStarter = $s->lineup(0, self::PARTNER)->starterPid('PG');
        self::assertNotNull($userStarter);
        self::assertNotNull($partnerStarter);

        $strong = new SlotStrength('PG', 1, null, 3, false, []);
        $positionStrength = $s->positionStrength;
        $positionStrength[0][self::USER]['PG'] = $strong;
        $positionStrength[0][self::PARTNER]['PG'] = $strong;
        $positionStrength[0][self::USER]['SG'] = new SlotStrength('SG', 12, null, 3, false, [self::USER_BURIED]);
        $positionStrength[0][self::PARTNER]['SG'] = new SlotStrength('SG', 25, null, 3, false, []);
        $positionStrength[0][self::PARTNER]['SF'] = new SlotStrength('SF', 25, null, 3, false, [self::PARTNER_BURIED]);

        $s = self::with($s, [
            'positionStrength' => $positionStrength,
            'payrollRanks' => [1 => [self::USER => 25, self::PARTNER => 2]] + $s->payrollRanks,
        ]);
        $s = self::withOverpay($s, self::USER_NEGATIVE, 100);
        if ($longOverpay > 0) {
            $s = self::withOverpay($s, self::PARTNER_LONG, $longOverpay);
        }

        $pool = new AssetPool(
            array_slice(array_values(array_unique([$userStarter, 305, 304, 302, 301, 306, 303])), 0, 6),
            array_slice(array_values(array_unique([$partnerStarter, 106, 105, 103, 102, 104, 107])), 0, 6),
            [new PickAsset(31, self::USER, self::USER, 2026, 1, 0), new PickAsset(32, self::USER, self::USER, 2027, 2, 1)],
            [new PickAsset(11, self::PARTNER, self::PARTNER, 2027, 1, 1), new PickAsset(12, self::PARTNER, self::PARTNER, 2027, 2, 1)],
            [
                new NeedPair(0, NeedPair::KIND_SLOT, 'PG', self::USER, self::PARTNER, 3, 20, 1.0),
                new NeedPair(0, NeedPair::KIND_SLOT, 'SF', self::PARTNER, self::USER, 3, 20, 1.0),
            ],
        );

        return [$s, $pool];
    }

    /**
     * @param array<int, int> $ages pid => age
     * @param list<int> $expiring pids with no salary past the current season
     * @return list<ProjectionPlayer>
     */
    private static function roster(int $tid, array $ages, array $expiring): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        $roster = [];
        for ($i = 1; $i <= 13; $i++) {
            $pid = $tid * 100 + $i;
            $pos = $positions[($i - 1) % 5];
            $salary = in_array($pid, $expiring, true)
                ? [1 => 400, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]
                : [1 => 400, 2 => 400, 3 => 400, 4 => 400, 5 => 400, 6 => 400];
            $roster[] = ProjectionFactory::player(
                pid: $pid,
                tid: $tid,
                pos: $pos,
                age: $ages[$pid] ?? 28,
                salaryByYear: $salary,
                ratings: ProjectionFactory::ratingSet([], 30.0 + $i * 4 + ($tid === self::USER ? 0 : 2)),
                dcDepth: [$pos => intdiv($i - 1, 5) + 1],
                dcMinutes: $i <= 5 ? 32 : ($i <= 10 ? 16 : 0),
            );
        }

        return $roster;
    }

    /**
     * Reprices a pid so his negative surplus at offset 1 equals `$overpay` and
     * his surplus at offsets 2 and 3 is zero.
     */
    private static function withOverpay(LeagueSnapshot $s, int $pid, int $overpay): LeagueSnapshot
    {
        $surplus = $s->surplusBySeason($pid);
        $projections = $s->projections;
        foreach ([1, 2, 3] as $offset) {
            $season = $projections[$pid][$offset];
            $fair = ($surplus[$offset] ?? 0) + $season->salary;
            $salary = $fair + ($offset === 1 ? $overpay : 0);
            $projections[$pid][$offset] = new ProjectedSeason($season->offset, $season->age, $salary, true, $season->ratings, $season->per36);
        }
        $repriced = self::with($s, ['projections' => $projections]);
        self::assertSame(-$overpay, $repriced->surplusBySeason($pid)[1]);

        return $repriced;
    }

    /**
     * @param array<string, mixed> $changes constructor fields to replace
     */
    private static function with(LeagueSnapshot $s, array $changes): LeagueSnapshot
    {
        return new LeagueSnapshot(...array_merge(get_object_vars($s), $changes));
    }
}
