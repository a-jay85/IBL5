<?php

declare(strict_types=1);

namespace Api\Transformer;

/**
 * @phpstan-import-type BoxscoreTeamRow from \Api\Repository\ApiGameRepository
 * @phpstan-import-type BoxscorePlayerRow from \Api\Repository\ApiGameRepository
 */
class BoxscoreTransformer
{
    /**
     * Transform a team box score row from ibl_box_scores_teams.
     *
     * @param BoxscoreTeamRow $row
     * @return array<string, mixed>
     */
    public function transformTeamStats(array $row): array
    {
        return [
            'name' => $row['name'],
            'quarter_scoring' => [
                'q1' => ['visitor' => $row['visitor_q1_points'], 'home' => $row['home_q1_points']],
                'q2' => ['visitor' => $row['visitor_q2_points'], 'home' => $row['home_q2_points']],
                'q3' => ['visitor' => $row['visitor_q3_points'], 'home' => $row['home_q3_points']],
                'q4' => ['visitor' => $row['visitor_q4_points'], 'home' => $row['home_q4_points']],
                'ot' => ['visitor' => $row['visitor_ot_points'], 'home' => $row['home_ot_points']],
            ],
            'totals' => [
                'fg_made' => $row['calc_fg_made'],
                'fg_attempted' => $row['game_2ga'] + $row['game_3ga'],
                'two_pt_made' => $row['game_2gm'],
                'two_pt_attempted' => $row['game_2ga'],
                'ft_made' => $row['game_ftm'],
                'ft_attempted' => $row['game_fta'],
                'three_pt_made' => $row['game_3gm'],
                'three_pt_attempted' => $row['game_3ga'],
                'offensive_rebounds' => $row['game_orb'],
                'defensive_rebounds' => $row['game_drb'],
                'rebounds' => $row['calc_rebounds'],
                'assists' => $row['game_ast'],
                'steals' => $row['game_stl'],
                'turnovers' => $row['game_tov'],
                'blocks' => $row['game_blk'],
                'personal_fouls' => $row['game_pf'],
                'points' => $row['calc_points'],
            ],
            'attendance' => $row['attendance'],
            'capacity' => $row['capacity'],
            'records' => [
                'visitor' => $row['visitor_wins'] . '-' . $row['visitor_losses'],
                'home' => $row['home_wins'] . '-' . $row['home_losses'],
            ],
        ];
    }

    /**
     * Transform a player box score line from ibl_box_scores.
     *
     * @param BoxscorePlayerRow $row
     * @return array<string, mixed>
     */
    public function transformPlayerLine(array $row): array
    {
        return [
            'uuid' => $row['player_uuid'],
            'name' => $row['name'],
            'position' => $row['pos'],
            'minutes' => $row['game_min'],
            'two_pt_made' => $row['game_2gm'],
            'two_pt_attempted' => $row['game_2ga'],
            'ft_made' => $row['game_ftm'],
            'ft_attempted' => $row['game_fta'],
            'three_pt_made' => $row['game_3gm'],
            'three_pt_attempted' => $row['game_3ga'],
            'fg_made' => $row['calc_fg_made'],
            'fg_attempted' => $row['game_2ga'] + $row['game_3ga'],
            'offensive_rebounds' => $row['game_orb'],
            'defensive_rebounds' => $row['game_drb'],
            'rebounds' => $row['calc_rebounds'],
            'assists' => $row['game_ast'],
            'steals' => $row['game_stl'],
            'turnovers' => $row['game_tov'],
            'blocks' => $row['game_blk'],
            'personal_fouls' => $row['game_pf'],
            'points' => $row['calc_points'],
        ];
    }
}
