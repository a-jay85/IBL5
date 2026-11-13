<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesAFixture as F;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\Shape\StarForYoungAndPicks;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\TeamDepth;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

final class StarForYoungAndPicksTest extends TestCase
{
    private const int YOUNG = 9001;
    private const array STARTERS_USER = ['PG' => 301, 'SG' => 302, 'SF' => 303, 'PF' => 304, 'C' => 305];
    private const array STARTERS_PARTNER = ['PG' => 101, 'SG' => 102, 'SF' => 103, 'PF' => 104, 'C' => 105];

    /**
     * @param array<string, int> $ranks slot => starter rank, in iteration order
     * @return array<string, SlotStrength>
     */
    private static function strengths(array $ranks): array
    {
        $out = [];
        foreach ($ranks as $slot => $rank) {
            $out[$slot] = new SlotStrength($slot, $rank, null, 3, false, []);
        }

        return $out;
    }

    /**
     * Only offset 0 is populated, so a read of offset 1 finds nothing.
     *
     * @param array<string, int> $ranks
     */
    private static function snapshot(TeamMode $user, TeamMode $partner, int $giverTid, array $ranks): LeagueSnapshot
    {
        $s = F::withModes(F::base(), $user, $partner);

        return F::with($s, [
            'positionStrength' => [0 => [$giverTid => self::strengths($ranks)]],
            'lineups' => [0 => $s->lineups[0]],
        ]);
    }

    private static function pick(int $id, int $round = 1): PickAsset
    {
        return new PickAsset($id, 0, 0, 2027, $round, 1);
    }

    /**
     * @param list<TradeCandidate> $candidates
     * @return list<array{list<int>, list<int>, list<int>, list<int>}>
     */
    private static function flat(array $candidates): array
    {
        return array_map(
            static fn (TradeCandidate $c): array => [$c->userPids, $c->userPickIds, $c->partnerPids, $c->partnerPickIds],
            $candidates,
        );
    }

    public function testRebuildUserGivesEachStrongStarterForYoungestAndFirstPick(): void
    {
        $s = F::withAges(
            self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, ['SG' => 12, 'PG' => 1, 'SF' => 8, 'PF' => 9]),
            [self::YOUNG => 22, 9002 => 24],
        );
        $pool = new AssetPool(array_values(self::STARTERS_USER), [9002, self::YOUNG], [self::pick(31)], [self::pick(11), self::pick(12)]);

        $candidates = F::collect(new StarForYoungAndPicks(), $s, $pool);

        self::assertSame([
            [[301], [], [self::YOUNG], [11]],
            [[303], [], [self::YOUNG], [11]],
        ], self::flat($candidates));
        self::assertSame(F::USER, $candidates[0]->userTid);
        self::assertSame(F::PARTNER, $candidates[0]->partnerTid);
    }

    public function testWinNowUserGivesYoungestAndFirstPickForPartnersStrongStarters(): void
    {
        $s = F::withAges(
            self::snapshot(TeamMode::WinNow, TeamMode::Rebuild, F::PARTNER, ['SG' => 12, 'PG' => 1, 'SF' => 8]),
            [self::YOUNG => 22, 9002 => 24],
        );
        $pool = new AssetPool([9002, self::YOUNG], array_values(self::STARTERS_PARTNER), [self::pick(31), self::pick(32)], [self::pick(11)]);

        $candidates = F::collect(new StarForYoungAndPicks(), $s, $pool);

        self::assertSame([
            [[self::YOUNG], [31], [101], []],
            [[self::YOUNG], [31], [103], []],
        ], self::flat($candidates));
    }

    public function testNoYoungPlayerOrNoPickYieldsNothing(): void
    {
        $ranks = ['PG' => 1];
        $old = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, $ranks), [9002 => 26]);
        $young = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, $ranks), [self::YOUNG => 22]);
        $shape = new StarForYoungAndPicks();

        self::assertSame([], F::collect($shape, $old, new AssetPool([301], [9002], [], [self::pick(11)])));
        self::assertSame([], F::collect($shape, $young, new AssetPool([301], [self::YOUNG], [], [])));

        $oldMirror = F::withAges(self::snapshot(TeamMode::WinNow, TeamMode::Rebuild, F::PARTNER, $ranks), [9002 => 26]);
        $youngMirror = F::withAges(self::snapshot(TeamMode::WinNow, TeamMode::Rebuild, F::PARTNER, $ranks), [self::YOUNG => 22]);
        self::assertSame([], F::collect($shape, $oldMirror, new AssetPool([9002], [101], [self::pick(31)], [])));
        self::assertSame([], F::collect($shape, $youngMirror, new AssetPool([self::YOUNG], [101], [], [])));
    }

    public function testStrongSlotWithoutPoolStarterOrWithoutStarterYieldsNothing(): void
    {
        $s = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, ['PG' => 1, 'XX' => 1]), [self::YOUNG => 22]);
        $shape = new StarForYoungAndPicks();

        // 301 starts at PG but is not in the pool; slot XX has no starter at all.
        self::assertSame([], F::collect($shape, $s, new AssetPool([302], [self::YOUNG], [], [self::pick(11)])));
        $pool = new AssetPool([302, 301], [self::YOUNG], [], [self::pick(11)]);
        self::assertSame([[[301], [], [self::YOUNG], [11]]], self::flat(F::collect($shape, $s, $pool)));
    }

    public function testGiverWithoutStrengthOrLineupYieldsNothing(): void
    {
        $shape = new StarForYoungAndPicks();
        $pool = new AssetPool([301], [self::YOUNG], [], [self::pick(11)]);

        $noStrength = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::PARTNER, ['PG' => 1]), [self::YOUNG => 22]);
        self::assertSame([], F::collect($shape, $noStrength, $pool));

        $s = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, ['PG' => 1]), [self::YOUNG => 22]);
        $noLineup = F::with($s, ['lineups' => [0 => [F::PARTNER => $s->lineups[0][F::PARTNER]]]]);
        self::assertSame([], F::collect($shape, $noLineup, $pool));
    }

    public function testStarterHoldingTwoStrongSlotsIsOfferedOnce(): void
    {
        $s = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, ['PG' => 1, 'SG' => 2]), [self::YOUNG => 22]);
        $starter = $s->lineups[0][F::USER]->depth->starterAt('PG');
        self::assertNotNull($starter);
        $lineup = new SeasonLineup(F::USER, 0, new TeamDepth(['PG' => $starter, 'SG' => $starter], true), [], [], true, []);
        $s = F::with($s, ['lineups' => [0 => [F::USER => $lineup]]]);

        $candidates = F::collect(new StarForYoungAndPicks(), $s, new AssetPool([301], [self::YOUNG], [], [self::pick(11)]));

        self::assertSame([[[301], [], [self::YOUNG], [11]]], self::flat($candidates));
    }

    /**
     * @return iterable<string, array{array<int, int>, ?int}>
     */
    public static function youngestProvider(): iterable
    {
        yield 'age 25 qualifies' => [[10 => 25], 10];
        yield 'age 26 does not' => [[10 => 26], null];
        yield 'only old players' => [[10 => 26, 20 => 30], null];
        yield 'unknown pid skipped' => [[99 => -1, 10 => 24], 10];
        yield 'same age, higher pid first' => [[50 => 24, 40 => 24], 40];
        yield 'same age, lower pid first' => [[40 => 24, 50 => 24], 40];
        yield 'younger with higher pid first' => [[50 => 22, 10 => 24], 50];
        yield 'younger with higher pid last' => [[10 => 24, 50 => 22], 50];
        yield 'younger tie, lower pid last' => [[60 => 22, 50 => 22, 10 => 23], 50];
        yield 'an old player does not block' => [[5 => 30, 10 => 25], 10];
    }

    /**
     * @param array<int, int> $ages pid => age; -1 means the pid is absent from the snapshot
     */
    #[DataProvider('youngestProvider')]
    public function testYoungestPicksLowestAgeThenLowestPid(array $ages, ?int $expected): void
    {
        $known = array_filter($ages, static fn (int $age): bool => $age >= 0);
        $s = F::withAges(self::snapshot(TeamMode::Rebuild, TeamMode::WinNow, F::USER, ['PG' => 1]), $known);
        $pool = new AssetPool([301], array_keys($ages), [], [self::pick(11)]);

        $candidates = F::collect(new StarForYoungAndPicks(), $s, $pool);

        self::assertSame($expected === null ? [] : [[[301], [], [$expected], [11]]], self::flat($candidates));
    }
}
