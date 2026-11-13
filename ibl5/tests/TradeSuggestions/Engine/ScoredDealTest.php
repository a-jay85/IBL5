<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

final class ScoredDealTest extends TestCase
{
    /**
     * @return array<string, array{float, float, bool}>
     */
    public static function totalsProvider(): array
    {
        return [
            'both positive' => [2.0, 3.0, true],
            'user zero' => [0.0, 3.0, false],
            'partner zero' => [2.0, 0.0, false],
            'both zero' => [0.0, 0.0, false],
            'user negative' => [-1.0, 3.0, false],
            'partner negative' => [2.0, -1.0, false],
            'both negative' => [-1.0, -2.0, false],
        ];
    }

    #[DataProvider('totalsProvider')]
    public function testBothSidesPositiveRequiresStrictlyPositiveTotals(float $user, float $partner, bool $expected): void
    {
        self::assertSame($expected, self::deal($user, $partner)->bothSidesPositive());
    }

    public function testWeakerSideTotalIsTheSmallerTotal(): void
    {
        self::assertSame(-1.0, self::deal(2.0, -1.0)->weakerSideTotal());
        self::assertSame(-1.0, self::deal(-1.0, 2.0)->weakerSideTotal());
    }

    private static function deal(float $user, float $partner): ScoredDeal
    {
        return new ScoredDeal(
            new TradeCandidate(1, 2, [], [], [], []),
            'one_for_one',
            self::side(1, $user),
            self::side(2, $partner),
            [],
            [],
        );
    }

    /** A side whose total() equals `$total`: only offset 0 counts, at weight 1. */
    private static function side(int $tid, float $total): SideGain
    {
        return new SideGain(
            $tid,
            TeamMode::Middle,
            [0 => array_replace(array_fill_keys(SideGain::KEYS, 0.0), ['starter_slots' => $total])],
            [1.0, 0.0, 0.0, 0.0],
            1.0,
        );
    }
}
