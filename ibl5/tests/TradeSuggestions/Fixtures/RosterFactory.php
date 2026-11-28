<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Fixtures;

use League\League;
use TradeSuggestions\ProductionLine;
use TradeSuggestions\ProductionSource;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TeamDepth;

/**
 * Hand-built rosters for the needs engine tests.
 *
 * Production lines cover 720 minutes, so points / 20 is points per 36.
 */
final class RosterFactory
{
    /**
     * @param array<string, int> $depth
     * @param list<int> $laterSalaries
     */
    public static function player(
        int $pid,
        string $pos = 'SF',
        int $age = 26,
        int $salary = 500,
        int $years = 1,
        int $dcMinutes = 0,
        array $depth = [],
        ?int $points = null,
        array $laterSalaries = [],
        int $futureSalary = 0,
        ?string $name = null,
    ): SuggestionPlayer {
        $production = $points === null
            ? null
            : new ProductionLine(30, 720, $points, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, ProductionSource::CurrentSeason);

        return new SuggestionPlayer(
            pid: $pid,
            name: $name ?? 'Player ' . $pid,
            pos: $pos,
            age: $age,
            injured: false,
            currentSalary: $salary,
            yearsRemaining: $years,
            futureSalary: $futureSalary,
            depth: $depth,
            dcMinutes: $dcMinutes,
            production: $production,
            laterSalaries: $laterSalaries,
        );
    }

    /**
     * @param list<SuggestionPlayer> $players
     */
    public static function team(int $tid, array $players, int $payroll = 6000): TeamAssets
    {
        return new TeamAssets($tid, $players, [], null, League::HARD_CAP_MAX - $payroll);
    }

    /**
     * A reliable chart with the given starters and every other slot empty.
     *
     * @param array<string, SuggestionPlayer> $starters
     */
    public static function depth(array $starters): TeamDepth
    {
        $full = [];
        foreach (SlotMapper::SLOTS as $slot) {
            $full[$slot] = $starters[$slot] ?? null;
        }

        return new TeamDepth($full, true);
    }
}
