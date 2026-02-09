<?php

declare(strict_types=1);

namespace Api\Transformer;

use BasketballStats\StatsFormatter;

class LeaderTransformer
{
    /**
     * Transform a leader row from ibl_hist joined with player and team tables.
     *
     * @param array{player_uuid: string, pid: int, name: string, teamid: int, team_uuid: string|null, team_city: string|null, team_name: string|null, year: int, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, blk: int, tvr: int, pf: int, pts: int} $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        $games = $row['games'];
        $fgm = $row['fgm'];
        $fga = $row['fga'];
        $ftm = $row['ftm'];
        $fta = $row['fta'];
        $tgm = $row['tgm'];
        $tga = $row['tga'];
        $points = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);

        return [
            'player' => [
                'uuid' => $row['player_uuid'],
                'pid' => $row['pid'],
                'name' => $row['name'],
            ],
            'team' => [
                'uuid' => $row['team_uuid'],
                'city' => $row['team_city'] ?? '',
                'name' => $row['team_name'] ?? '',
                'team_id' => $row['teamid'],
            ],
            'season' => $row['year'],
            'stats' => [
                'games' => $games,
                'minutes_per_game' => StatsFormatter::formatPerGameAverage($row['minutes'], $games),
                'points_per_game' => StatsFormatter::formatPerGameAverage($points, $games),
                'rebounds_per_game' => StatsFormatter::formatPerGameAverage($row['reb'], $games),
                'assists_per_game' => StatsFormatter::formatPerGameAverage($row['ast'], $games),
                'steals_per_game' => StatsFormatter::formatPerGameAverage($row['stl'], $games),
                'blocks_per_game' => StatsFormatter::formatPerGameAverage($row['blk'], $games),
                'turnovers_per_game' => StatsFormatter::formatPerGameAverage($row['tvr'], $games),
                'fg_percentage' => StatsFormatter::formatPercentage($fgm, $fga),
                'ft_percentage' => StatsFormatter::formatPercentage($ftm, $fta),
                'three_pt_percentage' => StatsFormatter::formatPercentage($tgm, $tga),
            ],
        ];
    }
}
