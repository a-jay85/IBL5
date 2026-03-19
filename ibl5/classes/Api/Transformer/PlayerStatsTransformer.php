<?php

declare(strict_types=1);

namespace Api\Transformer;

use BasketballStats\StatsFormatter;

/**
 * @phpstan-import-type CareerStatsRow from \Api\Repository\ApiPlayerStatsRepository
 * @phpstan-import-type SeasonHistoryRow from \Api\Repository\ApiPlayerStatsRepository
 */
class PlayerStatsTransformer
{
    /**
     * Transform a career stats row from vw_player_career_stats.
     *
     * @param CareerStatsRow $row
     * @return array<string, mixed>
     */
    public function transformCareer(array $row): array
    {
        return [
            'uuid' => $row['player_uuid'],
            'pid' => $row['pid'],
            'name' => $row['name'],
            'career_totals' => [
                'games' => $row['career_games'],
                'minutes' => $row['career_minutes'],
                'points' => (int) $row['career_points'],
                'rebounds' => $row['career_rebounds'],
                'assists' => $row['career_assists'],
                'steals' => $row['career_steals'],
                'blocks' => $row['career_blocks'],
            ],
            'career_averages' => [
                'points_per_game' => $row['ppg_career'],
                'rebounds_per_game' => $row['rpg_career'],
                'assists_per_game' => $row['apg_career'],
            ],
            'career_percentages' => [
                'fg_percentage' => $row['fg_pct_career'],
                'ft_percentage' => $row['ft_pct_career'],
                'three_pt_percentage' => $row['three_pt_pct_career'],
            ],
            'playoff_minutes' => $row['playoff_minutes'],
            'draft' => [
                'year' => $row['draft_year'],
                'round' => $row['draft_round'],
                'pick' => $row['draft_pick'],
                'team' => $row['drafted_by_team'],
                'team_id' => $row['draft_team_id'],
            ],
        ];
    }

    /**
     * Transform a season history row from ibl_hist.
     *
     * @param SeasonHistoryRow $row
     * @return array<string, mixed>
     */
    public function transformSeason(array $row): array
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
            'year' => $row['year'],
            'pid' => $row['pid'],
            'player_name' => $row['name'],
            'team' => [
                'uuid' => $row['team_uuid'],
                'city' => $row['team_city'] ?? '',
                'name' => $row['team_name'] ?? $row['team'],
                'team_id' => $row['teamid'],
            ],
            'games' => $games,
            'minutes' => $row['minutes'],
            'stats' => [
                'points' => $points,
                'rebounds' => $row['reb'],
                'offensive_rebounds' => $row['orb'],
                'assists' => $row['ast'],
                'steals' => $row['stl'],
                'blocks' => $row['blk'],
                'turnovers' => $row['tvr'],
                'personal_fouls' => $row['pf'],
                'fg_made' => $fgm,
                'fg_attempted' => $fga,
                'ft_made' => $ftm,
                'ft_attempted' => $fta,
                'three_pt_made' => $tgm,
                'three_pt_attempted' => $tga,
            ],
            'per_game' => [
                'points' => StatsFormatter::formatPerGameAverage($points, $games),
                'rebounds' => StatsFormatter::formatPerGameAverage($row['reb'], $games),
                'assists' => StatsFormatter::formatPerGameAverage($row['ast'], $games),
                'steals' => StatsFormatter::formatPerGameAverage($row['stl'], $games),
                'blocks' => StatsFormatter::formatPerGameAverage($row['blk'], $games),
                'turnovers' => StatsFormatter::formatPerGameAverage($row['tvr'], $games),
                'minutes' => StatsFormatter::formatPerGameAverage($row['minutes'], $games),
            ],
            'percentages' => [
                'fg' => StatsFormatter::formatPercentage($fgm, $fga),
                'ft' => StatsFormatter::formatPercentage($ftm, $fta),
                'three_pt' => StatsFormatter::formatPercentage($tgm, $tga),
            ],
            'salary' => $row['salary'],
        ];
    }
}
