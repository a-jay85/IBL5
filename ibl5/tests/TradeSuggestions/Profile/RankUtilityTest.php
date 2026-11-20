<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Profile;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Profile\RankUtility;

final class RankUtilityTest extends TestCase
{
    public function testMarginalTiersAtNineTenNineteenTwenty(): void
    {
        self::assertSame(0.3, RankUtility::marginal(1));
        self::assertSame(0.3, RankUtility::marginal(9));
        self::assertSame(0.6, RankUtility::marginal(10));
        self::assertSame(0.6, RankUtility::marginal(19));
        self::assertSame(1.0, RankUtility::marginal(20));
        self::assertSame(1.0, RankUtility::marginal(28));
    }

    /** 22->18 passes ranks 22, 21, 20, 19 = 1.0 * 3 + 0.6; marginal(after) times places would give 2.4. */
    public function testGainSumsMarginalsAcrossTierBoundary(): void
    {
        self::assertEqualsWithDelta(3.6, RankUtility::gain(22, 18), 1e-9);
        self::assertEqualsWithDelta(0.6 + 0.3, RankUtility::gain(10, 8), 1e-9);
    }

    public function testGainIsAntisymmetric(): void
    {
        self::assertEqualsWithDelta(-RankUtility::gain(22, 18), RankUtility::gain(18, 22), 1e-9);
        self::assertLessThan(0.0, RankUtility::gain(5, 12));
    }

    public function testGainZeroWhenUnchanged(): void
    {
        self::assertSame(0.0, RankUtility::gain(14, 14));
    }

    public function testRankOutOfRangeThrows(): void
    {
        foreach ([[0, 5], [5, 29], [29, 1]] as [$before, $after]) {
            try {
                RankUtility::gain($before, $after);
                self::fail("gain({$before}, {$after}) should throw");
            } catch (\InvalidArgumentException $e) {
                self::assertStringContainsString('outside 1..28', $e->getMessage());
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        RankUtility::marginal(0);
    }
}
