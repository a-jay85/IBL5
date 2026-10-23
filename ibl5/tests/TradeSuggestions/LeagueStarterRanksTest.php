<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\LeagueStarterRanks;
use TradeSuggestions\ProductionLine;
use TradeSuggestions\ProductionSource;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamDepth;

final class LeagueStarterRanksTest extends TestCase
{
    public function testRanksStartersWithinSlotByPointsPer36(): void
    {
        $ranks = LeagueStarterRanks::build([
            1 => self::depthWithCenter(self::player(1, 360)),
            2 => self::depthWithCenter(self::player(2, 720)),
            3 => self::depthWithCenter(self::player(3, 540)),
        ]);

        self::assertSame(1, $ranks->rankOf(2, 'C'));
        self::assertSame(2, $ranks->rankOf(3, 'C'));
        self::assertSame(3, $ranks->rankOf(1, 'C'));
        self::assertSame(3, $ranks->countFor('C'));
        self::assertNull($ranks->rankOf(2, 'PG'));
    }

    public function testStarterWithoutProductionIsUnrankedAndNotCounted(): void
    {
        $ranks = LeagueStarterRanks::build([
            1 => self::depthWithCenter(self::player(1, 360)),
            2 => self::depthWithCenter(self::player(2, null)),
        ]);

        self::assertNull($ranks->rankOf(2, 'C'));
        self::assertSame(1, $ranks->rankOf(1, 'C'));
        self::assertSame(1, $ranks->countFor('C'));
    }

    public function testRankIfStartingExcludesThePlayerHimself(): void
    {
        $starter = self::player(1, 720);
        $ranks = LeagueStarterRanks::build([
            1 => self::depthWithCenter($starter),
            2 => self::depthWithCenter(self::player(2, 540)),
        ]);

        self::assertSame(1, $ranks->rankIfStarting($starter, 'C'));
        self::assertSame(2, $ranks->rankIfStarting(self::player(3, 600), 'C'));
        self::assertSame(3, $ranks->rankIfStarting(self::player(4, 100), 'C'));
        self::assertNull($ranks->rankIfStarting(self::player(5, null), 'C'));
    }

    private static function depthWithCenter(SuggestionPlayer $center): TeamDepth
    {
        return new TeamDepth(['PG' => null, 'SG' => null, 'SF' => null, 'PF' => null, 'C' => $center], true);
    }

    /**
     * Points over 720 minutes, so points / 20 is points per 36.
     */
    private static function player(int $pid, ?int $points): SuggestionPlayer
    {
        $production = $points === null
            ? null
            : new ProductionLine(30, 720, $points, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, ProductionSource::CurrentSeason);

        return new SuggestionPlayer(
            pid: $pid,
            name: 'Player ' . $pid,
            pos: 'C',
            age: 26,
            injured: false,
            currentSalary: 1000,
            yearsRemaining: 1,
            futureSalary: 0,
            production: $production,
        );
    }
}
