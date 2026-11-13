<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesBFixture;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\Shape\OneForTwo;

final class OneForTwoTest extends TestCase
{
    public function testYieldsPairsFromTheFirstSixPartnerPidsForTheFirstTwoUserPids(): void
    {
        $mid = [12];
        $s = ShapesBFixture::snapshot();
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::USER, ['PG' => $mid, 'SG' => [25], 'SF' => $mid, 'PF' => $mid, 'C' => $mid]);
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::PARTNER, ['PG' => [1], 'SG' => $mid, 'SF' => [2], 'PF' => $mid, 'C' => $mid]);
        $pool = new AssetPool([201, 202, 203], [101, 102, 103, 104, 105, 106, 107], [], [], []);

        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new OneForTwo(), $s, $pool));

        self::assertCount(30, $flat);
        self::assertSame([[201], [], [101, 102], []], $flat[0]);
        self::assertSame([[202], [], [101, 102], []], $flat[1]);
        self::assertSame([[201], [], [101, 103], []], $flat[2]);
        self::assertSame([[202], [], [105, 106], []], $flat[29]);
        foreach ($flat as [$user, , $partner]) {
            self::assertContains($user[0], [201, 202]);
            self::assertNotContains(107, $partner);
        }
    }

    public function testYieldsNothingWhenTheGateFails(): void
    {
        $s = ShapesBFixture::snapshot();
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::USER, ['PG' => [12], 'SG' => [12]]);
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::PARTNER, ['PG' => [12], 'SG' => [12]]);

        self::assertSame([], ShapesBFixture::collect(new OneForTwo(), $s, new AssetPool([201, 202], [101, 102, 103], [], [], [])));
    }
}
