<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use League\League;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Profile\RankUtility;
use TradeSuggestions\Valuation\CapRoomAsset;

final class CapRoomAssetTest extends TestCase
{
    public function testPayrollSumsSalariesAndCash(): void
    {
        self::assertSame(3150, CapRoomAsset::payroll([1000, 2000], 150));
        self::assertSame(0, CapRoomAsset::payroll([], 0));
    }

    public function testPayrollRanksLowestFirstWithTidTiebreak(): void
    {
        self::assertSame(
            [1 => 1, 3 => 2, 2 => 3],
            CapRoomAsset::payrollRanks([3 => 500, 1 => 500, 2 => 900]),
        );
        self::assertSame([], CapRoomAsset::payrollRanks([]));
    }

    public function testBreachBoundaryAtExactlyHardCap(): void
    {
        self::assertFalse(CapRoomAsset::breachesHardCapNow(League::HARD_CAP_MAX));
        self::assertTrue(CapRoomAsset::breachesHardCapNow(League::HARD_CAP_MAX + 1));
    }

    public function testHardCapComesFromLeagueConstant(): void
    {
        // phpunit-hygiene-allow: the claim is about the literal text of the class file; no runtime seam exists
        $source = file_get_contents(__DIR__ . '/../../../classes/TradeSuggestions/Valuation/CapRoomAsset.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('7000', $source);
        self::assertStringNotContainsString('5000', $source);
        self::assertTrue(CapRoomAsset::breachesHardCapNow(League::HARD_CAP_MAX + 1));
    }

    public function testFixingHardCapNowAddsFixPlaces(): void
    {
        self::assertEqualsWithDelta(
            3.0 + RankUtility::gain(20, 18),
            CapRoomAsset::places(0, League::HARD_CAP_MAX + 200, League::HARD_CAP_MAX - 100, 20, 18),
            1e-9,
        );
        self::assertEqualsWithDelta(
            RankUtility::gain(20, 18),
            CapRoomAsset::places(0, League::HARD_CAP_MAX, League::HARD_CAP_MAX - 100, 20, 18),
            1e-9,
        );
    }

    public function testBreachAfterAtOffsetZeroReturnsSentinel(): void
    {
        self::assertSame(-100.0, CapRoomAsset::places(0, League::HARD_CAP_MAX, League::HARD_CAP_MAX + 1, 20, 18));
        self::assertEqualsWithDelta(
            RankUtility::gain(20, 18),
            CapRoomAsset::places(0, 100, League::HARD_CAP_MAX, 20, 18),
            1e-9,
        );
    }

    public function testLaterSeasonOverageIsMildAndCapped(): void
    {
        $cap = League::HARD_CAP_MAX;

        self::assertEqualsWithDelta(-0.5, CapRoomAsset::places(1, $cap, $cap + 700, 10, 10), 1e-9);
        self::assertEqualsWithDelta(-2.0, CapRoomAsset::places(1, $cap, $cap + 7000, 10, 10), 1e-9);
        self::assertEqualsWithDelta(0.0, CapRoomAsset::places(1, $cap - 500, $cap, 10, 10), 1e-9);
    }

    public function testLaterSeasonBreachIsNeverTheSentinel(): void
    {
        $result = CapRoomAsset::places(1, League::HARD_CAP_MAX, League::HARD_CAP_MAX + 99999, 10, 10);

        self::assertGreaterThan(-100.0, $result);
        self::assertEqualsWithDelta(-0.5, CapRoomAsset::places(3, League::HARD_CAP_MAX, League::HARD_CAP_MAX + 700, 10, 10), 1e-9);
    }

    public function testShrinkingALaterOverageIsAGain(): void
    {
        $cap = League::HARD_CAP_MAX;

        self::assertEqualsWithDelta(0.5, CapRoomAsset::places(2, $cap + 1400, $cap + 700, 10, 10), 1e-9);
    }

    public function testRankMovementUsesRankUtilityTiers(): void
    {
        self::assertEqualsWithDelta(3.6, CapRoomAsset::places(1, 4000, 3000, 22, 18), 1e-9);
        self::assertEqualsWithDelta(-3.6, CapRoomAsset::places(1, 3000, 4000, 18, 22), 1e-9);
        self::assertEqualsWithDelta(3.6, CapRoomAsset::places(0, 4000, 3000, 22, 18), 1e-9);
    }

    public function testNoChangeIsZero(): void
    {
        self::assertSame(0.0, CapRoomAsset::places(0, 4000, 4000, 10, 10));
        self::assertEqualsWithDelta(0.0, CapRoomAsset::places(1, 4000, 4000, 10, 10), 1e-12);
    }

    public function testRoomFiguresAreSignedMoney(): void
    {
        self::assertSame(-200, CapRoomAsset::roomAt(5200));
        self::assertSame(1800, CapRoomAsset::hardRoomAt(5200));
    }
}
