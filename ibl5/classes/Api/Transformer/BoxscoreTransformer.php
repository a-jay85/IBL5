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
                'q1' => ['visitor' => $row['visitorQ1points'], 'home' => $row['homeQ1points']],
                'q2' => ['visitor' => $row['visitorQ2points'], 'home' => $row['homeQ2points']],
                'q3' => ['visitor' => $row['visitorQ3points'], 'home' => $row['homeQ3points']],
                'q4' => ['visitor' => $row['visitorQ4points'], 'home' => $row['homeQ4points']],
                'ot' => ['visitor' => $row['visitorOTpoints'], 'home' => $row['homeOTpoints']],
            ],
            'totals' => [
                'fg_made' => $row['calc_fg_made'],
                'fg_attempted' => $row['game2GA'] + $row['game3GA'],
                'two_pt_made' => $row['game2GM'],
                'two_pt_attempted' => $row['game2GA'],
                'ft_made' => $row['gameFTM'],
                'ft_attempted' => $row['gameFTA'],
                'three_pt_made' => $row['game3GM'],
                'three_pt_attempted' => $row['game3GA'],
                'offensive_rebounds' => $row['gameORB'],
                'defensive_rebounds' => $row['gameDRB'],
                'rebounds' => $row['calc_rebounds'],
                'assists' => $row['gameAST'],
                'steals' => $row['gameSTL'],
                'turnovers' => $row['gameTOV'],
                'blocks' => $row['gameBLK'],
                'personal_fouls' => $row['gamePF'],
                'points' => $row['calc_points'],
            ],
            'attendance' => $row['attendance'],
            'capacity' => $row['capacity'],
            'records' => [
                'visitor' => $row['visitorWins'] . '-' . $row['visitorLosses'],
                'home' => $row['homeWins'] . '-' . $row['homeLosses'],
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
            'minutes' => $row['gameMIN'],
            'two_pt_made' => $row['game2GM'],
            'two_pt_attempted' => $row['game2GA'],
            'ft_made' => $row['gameFTM'],
            'ft_attempted' => $row['gameFTA'],
            'three_pt_made' => $row['game3GM'],
            'three_pt_attempted' => $row['game3GA'],
            'fg_made' => $row['calc_fg_made'],
            'fg_attempted' => $row['game2GA'] + $row['game3GA'],
            'offensive_rebounds' => $row['gameORB'],
            'defensive_rebounds' => $row['gameDRB'],
            'rebounds' => $row['calc_rebounds'],
            'assists' => $row['gameAST'],
            'steals' => $row['gameSTL'],
            'turnovers' => $row['gameTOV'],
            'blocks' => $row['gameBLK'],
            'personal_fouls' => $row['gamePF'],
            'points' => $row['calc_points'],
        ];
    }
}
