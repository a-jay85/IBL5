<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesAFixture as F;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\Shape\PickSwap;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

final class PickSwapTest extends TestCase
{
    private static function pick(int $id, int $round, int $yearsAway): PickAsset
    {
        return new PickAsset($id, 0, 0, 2026 + $yearsAway, $round, $yearsAway);
    }

    /**
     * @param list<TradeCandidate> $candidates
     * @return list<array{list<int>, list<int>}>
     */
    private static function flat(array $candidates): array
    {
        return array_map(
            static fn (TradeCandidate $c): array => [$c->userPickIds, $c->partnerPickIds],
            $candidates,
        );
    }

    /**
     * @return list<TradeCandidate>
     */
    private static function swap(AssetPool $pool): array
    {
        $s = F::withModes(F::base(), TeamMode::Middle, TeamMode::WinNow);

        return F::collect(new PickSwap(), $s, $pool);
    }

    public function testRound2SweetenerGoesWithTheNearerPick(): void
    {
        $pool = new AssetPool([], [], [self::pick(31, 1, 0), self::pick(32, 2, 1)], [self::pick(11, 1, 2), self::pick(12, 2, 1)]);

        $candidates = self::swap($pool);

        self::assertSame([[[31], [11]], [[31, 32], [11]]], self::flat($candidates));
        self::assertSame([], $candidates[0]->userPids);
        self::assertSame([], $candidates[0]->partnerPids);
    }

    public function testPartnerHoldingNearerPickAddsItsRound2(): void
    {
        $pool = new AssetPool([], [], [self::pick(31, 1, 2), self::pick(32, 2, 1)], [self::pick(11, 1, 0), self::pick(12, 2, 1)]);

        self::assertSame([[[31], [11]], [[31], [11, 12]]], self::flat(self::swap($pool)));
    }

    public function testSweetenerIsSkippedWhenNearerSideHasNoRound2(): void
    {
        $nearUser = new AssetPool([], [], [self::pick(31, 1, 0)], [self::pick(11, 1, 2), self::pick(12, 2, 1)]);
        self::assertSame([[[31], [11]]], self::flat(self::swap($nearUser)));

        $nearPartner = new AssetPool([], [], [self::pick(31, 1, 2), self::pick(32, 2, 1)], [self::pick(11, 1, 0)]);
        self::assertSame([[[31], [11]]], self::flat(self::swap($nearPartner)));
    }

    public function testRound2PicksAreNotSwappedAndEqualYearsAreSkippedInAnyPosition(): void
    {
        $pool = new AssetPool(
            [],
            [],
            [self::pick(30, 2, 0), self::pick(31, 1, 0)],
            [self::pick(10, 2, 1), self::pick(11, 1, 0), self::pick(12, 1, 1)],
        );

        self::assertSame([[[31], [12]], [[31, 30], [12]]], self::flat(self::swap($pool)));
    }

    public function testNoCandidatesWhenModesMatch(): void
    {
        $s = F::withModes(F::base(), TeamMode::Middle, TeamMode::Middle);
        $pool = new AssetPool([], [], [self::pick(31, 1, 0)], [self::pick(11, 1, 2)]);

        self::assertSame([], F::collect(new PickSwap(), $s, $pool));
    }
}
