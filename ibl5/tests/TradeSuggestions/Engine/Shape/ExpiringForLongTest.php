<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesBFixture;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PickAsset;
use TradeSuggestions\Engine\Shape\ExpiringForLong;
use TradeSuggestions\Valuation\ContractValue;

/**
 * User 201 and partner 101 expire after this season; 203 and 103 expire a
 * year later; 202 (user) and 104 (partner) are the truly bad long contracts.
 * Pid 999 is in no roster.
 */
final class ExpiringForLongTest extends TestCase
{
    private const int USER = ShapesBFixture::USER;
    private const int PARTNER = ShapesBFixture::PARTNER;

    private static function snapshot(int $badOverpay = ContractValue::BAD_CONTRACT_FLOOR + 1): LeagueSnapshot
    {
        $s = ShapesBFixture::snapshot([101], [201], [103], [203]);
        foreach ([101, 102, 103, 104, 201, 202, 203, 204] as $pid) {
            $s = ShapesBFixture::withOverpay($s, $pid, in_array($pid, [104, 202], true) ? $badOverpay : 0);
        }

        return $s;
    }

    /**
     * @param list<PickAsset> $userPicks
     * @param list<PickAsset> $partnerPicks
     */
    private static function pool(array $userPicks, array $partnerPicks): AssetPool
    {
        return new AssetPool([202, 203, 999, 201, 204], [102, 103, 999, 101, 104], $userPicks, $partnerPicks, []);
    }

    private static function userPick(int $id): PickAsset
    {
        return new PickAsset($id, self::USER, self::USER, 2027, 1, 1);
    }

    private static function partnerPick(int $id): PickAsset
    {
        return new PickAsset($id, self::PARTNER, self::PARTNER, 2027, 1, 1);
    }

    public function testBothDirectionsYieldTheExpiringForTheBadLongContractPlusTheFirstPick(): void
    {
        $pool = self::pool(
            [self::userPick(31), self::userPick(32)],
            [self::partnerPick(11), self::partnerPick(12)],
        );

        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new ExpiringForLong(), self::snapshot(), $pool));

        self::assertSame([
            [[201], [], [104], [11]],
            [[202], [31], [101], []],
        ], $flat);
    }

    public function testOnlyTheUserSideFiresWithoutPartnerPicks(): void
    {
        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new ExpiringForLong(), self::snapshot(), self::pool([self::userPick(31)], [])));

        self::assertSame([[[202], [31], [101], []]], $flat);
    }

    public function testOnlyThePartnerSideFiresWithoutUserPicks(): void
    {
        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new ExpiringForLong(), self::snapshot(), self::pool([], [self::partnerPick(11)])));

        self::assertSame([[[201], [], [104], [11]]], $flat);
    }

    public function testYieldsNothingWithoutPicks(): void
    {
        self::assertSame([], ShapesBFixture::collect(new ExpiringForLong(), self::snapshot(), self::pool([], [])));
    }

    public function testYieldsNothingWhenTheLongContractIsNotTrulyBad(): void
    {
        $pool = self::pool([self::userPick(31)], [self::partnerPick(11)]);

        self::assertSame([], ShapesBFixture::collect(new ExpiringForLong(), self::snapshot(ContractValue::BAD_CONTRACT_FLOOR), $pool));
    }
}
