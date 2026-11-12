<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine\Shape;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Shape\Fixtures\ShapesBFixture;
use TradeSuggestions\Engine\AssetPool;
use TradeSuggestions\Engine\Shape\TwoForOne;

final class TwoForOneTest extends TestCase
{
    public function testYieldsPairsFromTheFirstSixUserPidsForTheFirstTwoPartnerPids(): void
    {
        $mid = [12];
        $s = ShapesBFixture::snapshot();
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::PARTNER, ['PG' => $mid, 'SG' => [25], 'SF' => $mid, 'PF' => $mid, 'C' => $mid]);
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::USER, ['PG' => [1], 'SG' => $mid, 'SF' => [2], 'PF' => $mid, 'C' => $mid]);
        $pool = new AssetPool([201, 202, 203, 204, 205, 206, 207], [101, 102, 103], [], [], []);

        $flat = ShapesBFixture::flat(ShapesBFixture::collect(new TwoForOne(), $s, $pool));

        self::assertCount(30, $flat);
        self::assertSame([[201, 202], [], [101], []], $flat[0]);
        self::assertSame([[201, 202], [], [102], []], $flat[1]);
        self::assertSame([[201, 203], [], [101], []], $flat[2]);
        self::assertSame([[205, 206], [], [102], []], $flat[29]);
        foreach ($flat as [$user, , $partner]) {
            self::assertNotContains(207, $user);
            self::assertContains($partner[0], [101, 102]);
        }
    }

    public function testYieldsNothingWhenTheGateFails(): void
    {
        $s = ShapesBFixture::snapshot();
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::USER, ['PG' => [12], 'SG' => [12]]);
        $s = ShapesBFixture::withStrengths($s, ShapesBFixture::PARTNER, ['PG' => [12], 'SG' => [12]]);

        self::assertSame([], ShapesBFixture::collect(new TwoForOne(), $s, new AssetPool([201, 202], [101, 102, 103], [], [], [])));
    }
}
