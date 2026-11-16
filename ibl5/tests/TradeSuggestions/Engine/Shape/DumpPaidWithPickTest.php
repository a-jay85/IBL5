<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesAFixture as F;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\Shape\DumpPaidWithPick;
use TradeSuggestions\TradeCandidate;

final class DumpPaidWithPickTest extends TestCase
{
    private static function pick(int $id, int $round): PickAsset
    {
        return new PickAsset($id, F::USER, F::USER, 2027, $round, 1);
    }

    /**
     */
    private static function ranked(?int $user, ?int $partner, ?LeagueSnapshot $s = null): LeagueSnapshot
    {
        $ranks = [];
        if ($user !== null) {
            $ranks[F::USER] = $user;
        }
        if ($partner !== null) {
            $ranks[F::PARTNER] = $partner;
        }

        return F::with($s ?? F::base(), ['payrollRanks' => [1 => $ranks]]);
    }

    private static function dumpable(LeagueSnapshot $s): LeagueSnapshot
    {
        return F::withSurplus($s, 301, [1 => -10, 2 => 0, 3 => 0]);
    }

    /**
     * @param list<TradeCandidate> $candidates
     * @return list<array{list<int>, list<int>, list<int>}>
     */
    private static function flat(array $candidates): array
    {
        return array_map(
            static fn (TradeCandidate $c): array => [$c->userPids, $c->userPickIds, $c->partnerPids],
            $candidates,
        );
    }

    private static function pool(): AssetPool
    {
        return new AssetPool([301], [], [self::pick(31, 2)], []);
    }

    /**
     * @return iterable<string, array{?int, ?int, bool}>
     */
    public static function rankProvider(): iterable
    {
        yield 'user rank 20, partner rank 9 fires' => [20, 9, true];
        yield 'user rank 19 refused' => [19, 9, false];
        yield 'user rank 21 fires' => [21, 9, true];
        yield 'partner rank 10 refused' => [20, 10, false];
        yield 'partner rank 1 fires' => [28, 1, true];
        yield 'partner rank missing refused' => [25, null, false];
        yield 'user rank missing refused' => [null, 5, false];
        yield 'both missing refused' => [null, null, false];
    }

    #[DataProvider('rankProvider')]
    public function testPayrollTierBoundaries(?int $userRank, ?int $partnerRank, bool $fires): void
    {
        $s = self::ranked($userRank, $partnerRank, self::dumpable(F::base()));

        $candidates = F::collect(new DumpPaidWithPick(), $s, self::pool());

        self::assertSame($fires ? [[[301], [31], []]] : [], self::flat($candidates));
    }

    /**
     * @return iterable<string, array{array<int, int>, bool}>
     */
    public static function surplusProvider(): iterable
    {
        yield 'negative at offset 1' => [[1 => -10, 2 => 0, 3 => 0], true];
        yield 'negative at offset 2' => [[1 => 0, 2 => -10, 3 => 0], true];
        yield 'negative at offset 3' => [[1 => 0, 2 => 0, 3 => -10], true];
        yield 'sum exactly zero' => [[1 => -5, 2 => 5, 3 => 0], false];
        yield 'sum exactly zero, offset 3 cancels' => [[1 => 0, 2 => 5, 3 => -5], false];
        yield 'positive offset 1 outweighs' => [[1 => 20, 2 => -10, 3 => 0], false];
        yield 'negative total across offsets' => [[1 => 5, 2 => -10, 3 => 2], true];
        yield 'positive' => [[1 => 5, 2 => 0, 3 => 0], false];
        yield 'zero' => [[1 => 0, 2 => 0, 3 => 0], false];
    }

    /**
     * @param array<int, int> $targets
     */
    #[DataProvider('surplusProvider')]
    public function testFiresOnlyForNegativeSurplusSumOverOffsetsOneToThree(array $targets, bool $fires): void
    {
        $s = self::ranked(25, 2, F::withSurplus(F::base(), 301, $targets));

        $candidates = F::collect(new DumpPaidWithPick(), $s, self::pool());

        self::assertSame($fires ? [[[301], [31], []]] : [], self::flat($candidates));
    }

    public function testPrefersRoundTwoPickAndFallsBackToRoundOne(): void
    {
        $s = self::ranked(25, 2, self::dumpable(F::base()));
        $shape = new DumpPaidWithPick();

        $both = new AssetPool([301], [], [self::pick(30, 1), self::pick(31, 2)], []);
        self::assertSame([[[301], [31], []]], self::flat(F::collect($shape, $s, $both)));

        $roundOne = new AssetPool([301], [], [self::pick(30, 1)], []);
        self::assertSame([[[301], [30], []]], self::flat(F::collect($shape, $s, $roundOne)));

        $none = new AssetPool([301], [], [], []);
        self::assertSame([], F::collect($shape, $s, $none));
    }

    public function testPairsEachDumpWithEmptySideAndExpiringPartnerPlayersOnly(): void
    {
        $partnerPlayers = [
            // salaryAt(1) is 0 but salaryAt(2) is not: offered.
            9101 => ProjectionFactory::player(pid: 9101, tid: F::PARTNER, salaryByYear: [1 => 400, 2 => 0, 3 => 400]),
            // salaryAt(1) is 400 but salaryAt(2) is 0: not offered.
            9102 => ProjectionFactory::player(pid: 9102, tid: F::PARTNER, salaryByYear: [1 => 400, 2 => 400, 3 => 0]),
        ];
        $base = self::dumpable(F::base());
        $s = self::ranked(25, 2, F::with($base, ['players' => $partnerPlayers + $base->players]));
        // 9103 is in the pool but unknown to the snapshot.
        $pool = new AssetPool([301], [9102, 9103, 9101], [self::pick(31, 2)], []);

        $candidates = F::collect(new DumpPaidWithPick(), $s, $pool);

        self::assertSame([[[301], [31], []], [[301], [31], [9101]]], self::flat($candidates));
        self::assertSame([], $candidates[0]->partnerPickIds);
        self::assertSame(F::USER, $candidates[0]->userTid);
        self::assertSame(F::PARTNER, $candidates[0]->partnerTid);
    }
}
