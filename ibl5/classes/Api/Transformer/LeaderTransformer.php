<?php

declare(strict_types=1);

namespace Api\Transformer;

use BasketballStats\StatsFormatter;

/**
 * @phpstan-import-type LeaderRow from \Api\Repository\ApiLeadersRepository
 */
class LeaderTransformer
{
    /**
     * Transform a leader row from ibl_hist joined with player and team tables.
     *
     * @param LeaderRow $row
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
