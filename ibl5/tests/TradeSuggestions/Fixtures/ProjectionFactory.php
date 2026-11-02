<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Fixtures;

use TradeSuggestions\ProductionLine;
use TradeSuggestions\Profile\SeasonLineup;
use TradeSuggestions\Projection\PlayerProjection;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\Rating;
use TradeSuggestions\Projection\RatingScale;
use TradeSuggestions\Projection\RatingSet;

/**
 * Builders for the v3 projection and profile tests.
 */
final class ProjectionFactory
{
    /**
     * Every `Rating::ALL` key at `$base`, then the overrides applied.
     *
     * @param array<string, float|int> $overrides
     */
    public static function ratingSet(array $overrides = [], float $base = 50.0): RatingSet
    {
        $row = [];
        foreach (Rating::ALL as $key) {
            $row[$key] = $base;
        }
        foreach ($overrides as $key => $value) {
            $row[$key] = (float) $value;
        }

        return RatingSet::fromRow($row);
    }

    /**
     * A scale whose maximum is `$max` for every key.
     */
    public static function scale(float $max = 99.0): RatingScale
    {
        $row = [];
        foreach (Rating::ALL as $key) {
            $row[$key] = $max;
        }

        return RatingScale::fromRows([$row]);
    }

    /**
     * @param array<string, int> $overrides keys of a current-season ibl_plr row (stats_*)
     */
    public static function productionLine(array $overrides = []): ProductionLine
    {
        return ProductionLine::fromCurrentRow(array_merge([
            'stats_gm' => 40,
            'stats_min' => 1000,
            'stats_fgm' => 200,
            'stats_fga' => 420,
            'stats_ftm' => 80,
            'stats_fta' => 100,
            'stats_3gm' => 40,
            'stats_3ga' => 110,
            'stats_orb' => 40,
            'stats_drb' => 160,
            'stats_ast' => 120,
            'stats_stl' => 40,
            'stats_blk' => 20,
            'stats_tvr' => 60,
        ], $overrides));
    }

    /**
     * @param array<int, int>|null $salaryByYear defaults to 500 in years 1-3, 0 in 4-6
     * @param array<string, int> $dcDepth PG..C depth-chart slots; missing slots are 0
     */
    public static function player(
        int $pid = 1,
        int $tid = 1,
        string $pos = 'PG',
        int $age = 26,
        int $talent = 50,
        int $effectiveCy = 1,
        ?array $salaryByYear = null,
        ?RatingSet $ratings = null,
        bool $noProduction = false,
        ?ProductionLine $production = null,
        int $injuredDays = 0,
        array $dcDepth = [],
        int $dcMinutes = 0,
        int $productionFouls = 50,
        bool $tradeable = true,
    ): ProjectionPlayer {
        $slots = ['PG' => 0, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0];

        return new ProjectionPlayer(
            pid: $pid,
            name: 'Player ' . $pid,
            pos: $pos,
            tid: $tid,
            age: $age,
            injuredDays: $injuredDays,
            exp: 4,
            talent: $talent,
            bird: 1,
            draftRound: 1,
            draftYear: 2020,
            draftPickNo: 10,
            prefs: ['loyalty' => 0, 'winner' => 0, 'tradition' => 0, 'security' => 0, 'playing_time' => 0],
            ratings: $ratings ?? self::ratingSet(),
            depth: $slots,
            dcDepth: array_merge($slots, $dcDepth),
            dcMinutes: $dcMinutes,
            effectiveCy: $effectiveCy,
            salaryByYear: $salaryByYear ?? [1 => 500, 2 => 500, 3 => 500, 4 => 0, 5 => 0, 6 => 0],
            production: $noProduction ? null : ($production ?? self::productionLine()),
            tradeable: $tradeable,
            productionFouls: $productionFouls,
        );
    }

    /**
     * Projects each player against `scale()` and builds the lineup at `$offset`.
     *
     * @param list<ProjectionPlayer> $players
     */
    public static function lineupFor(int $tid, int $offset, array $players): SeasonLineup
    {
        $projected = [];
        foreach ($players as $player) {
            $projected[$player->pid] = [
                'player' => $player,
                'season' => PlayerProjection::project($player, self::scale())[$offset],
            ];
        }

        return SeasonLineup::build($tid, $offset, $projected);
    }

    /**
     * Offset-0 lineups for tids 1..$teams. `$rowBuilder(int $tid)` returns that team's players.
     *
     * @param callable(int): list<ProjectionPlayer> $rowBuilder
     * @return array<int, SeasonLineup>
     */
    public static function leagueOf(int $teams, callable $rowBuilder): array
    {
        $lineups = [];
        for ($tid = 1; $tid <= $teams; $tid++) {
            $lineups[$tid] = self::lineupFor($tid, 0, $rowBuilder($tid));
        }

        return $lineups;
    }

    /**
     * Every rostered player's base ratings, keyed by pid.
     *
     * @param array<int, SeasonLineup> $lineups
     * @return array<int, RatingSet>
     */
    public static function ratingsOf(array $lineups): array
    {
        $ratings = [];
        foreach ($lineups as $lineup) {
            foreach ($lineup->players as $pid => $player) {
                $ratings[$pid] = $player->ratings;
            }
        }

        return $ratings;
    }
}
