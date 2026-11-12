<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesBFixture;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\NeedPair;
use TradeSuggestions\Engine\Shape\NeedPairGate;

final class NeedPairGateTest extends TestCase
{
    private const int USER = ShapesBFixture::USER;
    private const int PARTNER = ShapesBFixture::PARTNER;
    private const int THIRD = 3;

    /**
     * @param list<NeedPair> $pairs
     */
    private static function mutual(array $pairs): bool
    {
        return NeedPairGate::mutual(new AssetPool([], [], [], [], $pairs), self::USER, self::PARTNER);
    }

    public function testMutualWhenEachSideGivesAtTheSameOffset(): void
    {
        self::assertTrue(self::mutual([
            ShapesBFixture::needPair(self::USER, self::PARTNER, 1),
            ShapesBFixture::needPair(self::PARTNER, self::USER, 1),
        ]));
    }

    public function testNotMutualWhenOffsetsDiffer(): void
    {
        self::assertFalse(self::mutual([
            ShapesBFixture::needPair(self::USER, self::PARTNER, 0),
            ShapesBFixture::needPair(self::PARTNER, self::USER, 1),
        ]));
    }

    public function testUserGiveToAThirdTeamDoesNotCount(): void
    {
        self::assertFalse(self::mutual([
            ShapesBFixture::needPair(self::USER, self::THIRD),
            ShapesBFixture::needPair(self::PARTNER, self::USER),
        ]));
    }

    public function testThirdTeamGivingToTheUserDoesNotCountAsPartnerGive(): void
    {
        self::assertFalse(self::mutual([
            ShapesBFixture::needPair(self::USER, self::PARTNER),
            ShapesBFixture::needPair(self::THIRD, self::USER),
        ]));
    }

    public function testPartnerGivingToAThirdTeamDoesNotCountAsPartnerGive(): void
    {
        self::assertFalse(self::mutual([
            ShapesBFixture::needPair(self::USER, self::PARTNER),
            ShapesBFixture::needPair(self::PARTNER, self::THIRD),
        ]));
    }

    public function testNoPairsIsNotMutual(): void
    {
        self::assertFalse(self::mutual([]));
    }
}
