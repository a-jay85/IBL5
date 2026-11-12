<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesBFixture;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\Shape\OneForOne;

final class OneForOneTest extends TestCase
{
    public function testYieldsTheFirstFourPerSideInPoolOrder(): void
    {
        $pool = new AssetPool(
            [201, 202, 203, 204, 205, 206],
            [101, 102, 103, 104, 105, 106],
            [],
            [],
            [ShapesBFixture::needPair(ShapesBFixture::USER, ShapesBFixture::PARTNER), ShapesBFixture::needPair(ShapesBFixture::PARTNER, ShapesBFixture::USER)],
        );

        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new OneForOne(), ShapesBFixture::snapshot(), $pool));

        $expected = [];
        foreach ([201, 202, 203, 204] as $user) {
            foreach ([101, 102, 103, 104] as $partner) {
                $expected[] = [[$user], [], [$partner], []];
            }
        }
        self::assertSame($expected, $flat);
    }

    public function testYieldsNothingWithoutAMutualNeed(): void
    {
        $pool = new AssetPool([201], [101], [], [], [ShapesBFixture::needPair(ShapesBFixture::USER, ShapesBFixture::PARTNER)]);

        self::assertSame([], ShapesBFixture::collect(new OneForOne(), ShapesBFixture::snapshot(), $pool));
    }
}
