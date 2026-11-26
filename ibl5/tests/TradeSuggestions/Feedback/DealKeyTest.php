<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\TradeCandidate;

final class DealKeyTest extends TestCase
{
    public function testSameAssetsAnyOrderGiveOneDealKey(): void
    {
        $a = new TradeCandidate(1, 2, [10, 11], [500, 501], [20, 21], [600, 601]);
        $b = new TradeCandidate(1, 2, [11, 10], [501, 500], [21, 20], [601, 600]);

        self::assertEquals(DealKey::forCandidate($a), DealKey::forCandidate($b));
    }

    public function testSideSwapChangesDealKeyNotCoreKey(): void
    {
        // Same player pair, but pid 10 moves from the GM's side to the partner's.
        $a = new TradeCandidate(1, 2, [10], [], [20], []);
        $b = new TradeCandidate(1, 2, [], [], [10, 20], []);

        $keyA = DealKey::forCandidate($a);
        $keyB = DealKey::forCandidate($b);

        self::assertNotSame($keyA->deal, $keyB->deal);
        // The side tag is part of the player keys too, so the core changes as well.
        self::assertNotSame($keyA->core, $keyB->core);
    }

    public function testPickChangeChangesDealKeyNotCoreKey(): void
    {
        $a = new TradeCandidate(1, 2, [10], [], [20], []);
        $b = new TradeCandidate(1, 2, [10], [500], [20], []);

        $keyA = DealKey::forCandidate($a);
        $keyB = DealKey::forCandidate($b);

        self::assertNotSame($keyA->deal, $keyB->deal);
        self::assertSame($keyA->core, $keyB->core);
    }

    public function testPlayerChangeChangesBothKeys(): void
    {
        $a = new TradeCandidate(1, 2, [10], [], [20], []);
        $b = new TradeCandidate(1, 2, [10], [], [21], []);

        $keyA = DealKey::forCandidate($a);
        $keyB = DealKey::forCandidate($b);

        self::assertNotSame($keyA->deal, $keyB->deal);
        self::assertNotSame($keyA->core, $keyB->core);
    }

    public function testKeysAreFortyHex(): void
    {
        $key = DealKey::forCandidate(new TradeCandidate(1, 2, [10], [500], [20], [600]));

        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $key->deal);
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $key->core);
    }

    public function testDifferentPartnerChangesBothKeys(): void
    {
        $a = new TradeCandidate(1, 2, [10], [], [20], []);
        $b = new TradeCandidate(1, 3, [10], [], [20], []);

        $keyA = DealKey::forCandidate($a);
        $keyB = DealKey::forCandidate($b);

        self::assertNotSame($keyA->deal, $keyB->deal);
        self::assertNotSame($keyA->core, $keyB->core);
    }

    public function testDifferentUserTeamChangesBothKeys(): void
    {
        $a = new TradeCandidate(1, 2, [10], [], [20], []);
        $b = new TradeCandidate(5, 2, [10], [], [20], []);

        self::assertNotSame(DealKey::forCandidate($a)->deal, DealKey::forCandidate($b)->deal);
        self::assertNotSame(DealKey::forCandidate($a)->core, DealKey::forCandidate($b)->core);
    }

    public function testPicksDoNotEnterCore(): void
    {
        $noPicks = new TradeCandidate(1, 2, [10], [], [20], []);
        $manyPicks = new TradeCandidate(1, 2, [10], [500, 501], [20], [600]);

        self::assertSame(DealKey::forCandidate($noPicks)->core, DealKey::forCandidate($manyPicks)->core);
        self::assertNotSame(DealKey::forCandidate($noPicks)->deal, DealKey::forCandidate($manyPicks)->deal);
    }

    public function testPickAndPlayerWithSameIdDiffer(): void
    {
        $player = new TradeCandidate(1, 2, [10], [], [20], []);
        $pick = new TradeCandidate(1, 2, [], [10], [20], []);

        self::assertNotSame(DealKey::forCandidate($player)->deal, DealKey::forCandidate($pick)->deal);
    }

    public function testAssetKeysAreSideTaggedAndSorted(): void
    {
        $c = new TradeCandidate(1, 2, [10], [500], [20], [600]);

        self::assertSame(['P:0:600', 'P:1:20', 'U:0:500', 'U:1:10'], DealKey::assetKeys($c));
        self::assertSame(['P:1:20', 'U:1:10'], DealKey::playerKeys($c));
    }

    public function testKeyFormatIsPinned(): void
    {
        // Stored hides are keyed by these hashes, so the format must not drift.
        $key = DealKey::forCandidate(new TradeCandidate(1, 2, [10], [500], [20], [600]));

        self::assertSame(sha1('1|2|P:0:600,P:1:20,U:0:500,U:1:10'), $key->deal);
        self::assertSame(sha1('1|2|P:1:20,U:1:10'), $key->core);
    }
}
