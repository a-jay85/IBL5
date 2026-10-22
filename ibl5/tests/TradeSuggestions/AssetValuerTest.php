<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\AssetValuer;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamMode;

class AssetValuerTest extends TestCase
{
    private AssetValuer $valuer;

    protected function setUp(): void
    {
        $this->valuer = new AssetValuer();
    }

    public function testBaseValueGrowsWithSquareAboveReplacement(): void
    {
        self::assertSame(0.0, AssetValuer::baseValue(40));
        self::assertSame(0.0, AssetValuer::baseValue(45));
        self::assertSame(10.0, AssetValuer::baseValue(55));
        self::assertSame(62.5, AssetValuer::baseValue(70));
    }

    public function testWinNowIgnoresContractAndYouth(): void
    {
        $young = self::player(70, 22, years: 5, futureSalary: 5000);
        $expiring = self::player(70, 28, years: 1, futureSalary: 0);

        self::assertSame(62.5, $this->valuer->playerValue($young, TeamMode::WinNow));
        self::assertSame(62.5, $this->valuer->playerValue($expiring, TeamMode::WinNow));
        self::assertSame(62.5 * 0.7, $this->valuer->playerValue(self::player(70, 34, 1, 0), TeamMode::WinNow));
    }

    /**
     * A young player's long deal is control a rebuilding team wants, so his
     * future salary costs nothing; a veteran's future salary is a cost.
     */
    public function testRebuildChargesFutureSalaryOnlyToVeterans(): void
    {
        $young = self::player(70, 23, years: 5, futureSalary: 4800);
        $veteran = self::player(70, 28, years: 3, futureSalary: 2000);

        self::assertEqualsWithDelta(62.5 * 1.3, $this->valuer->playerValue($young, TeamMode::Rebuild), 1e-9);
        self::assertEqualsWithDelta(62.5 * 0.6 - 2000 * AssetValuer::REBUILD_SALARY_COST, $this->valuer->playerValue($veteran, TeamMode::Rebuild), 1e-9);
    }

    public function testRebuildDiscountsExpiringDeals(): void
    {
        $expiring = self::player(66, 23, years: 1, futureSalary: 0);
        $signed = self::player(66, 23, years: 2, futureSalary: 300);

        $base = 44.1 * 1.3;
        self::assertEqualsWithDelta($base * AssetValuer::REBUILD_EXPIRING_FACTOR, $this->valuer->playerValue($expiring, TeamMode::Rebuild), 1e-9);
        self::assertEqualsWithDelta($base, $this->valuer->playerValue($signed, TeamMode::Rebuild), 1e-9);
    }

    public function testPicksWorthMoreToRebuildingTeams(): void
    {
        $pick = new SuggestionPick(1, 1, 2027, 1, 10.0);

        self::assertEqualsWithDelta(13.0, $this->valuer->pickValue($pick, TeamMode::Rebuild), 1e-9);
        self::assertEqualsWithDelta(6.0, $this->valuer->pickValue($pick, TeamMode::WinNow), 1e-9);
    }

    private static function player(int $ovr, int $age, int $years, int $futureSalary): SuggestionPlayer
    {
        return new SuggestionPlayer(1, 'Player', 'SF', $age, $ovr, false, 1000, $years, $futureSalary);
    }
}
