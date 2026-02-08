<?php

declare(strict_types=1);

namespace Api\Transformer;

class PlayerTransformer
{
    /**
     * Transform a player row from vw_player_current for list endpoints.
     *
     * @param array{player_uuid: string, name: string, position: string, age: int, htft: int, htin: int, experience: int, team_uuid: string|null, team_city: string|null, team_name: string|null, full_team_name: string|null, current_salary: int, year1_salary: int, year2_salary: int, games_played: int, points_per_game: float|null, fg_percentage: float|null, ft_percentage: float|null, three_pt_percentage: float|null} $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'uuid' => $row['player_uuid'],
            'name' => $row['name'],
            'position' => $row['position'],
            'age' => $row['age'],
            'height' => $this->formatHeight($row['htft'], $row['htin']),
            'experience' => $row['experience'],
            'team' => $this->transformTeam($row['team_uuid'], $row['team_city'] ?? '', $row['team_name'] ?? '', $row['full_team_name'] ?? ''),
            'contract' => [
                'current_salary' => $row['current_salary'],
                'year1' => $row['year1_salary'],
                'year2' => $row['year2_salary'],
            ],
            'stats' => [
                'games_played' => $row['games_played'],
                'points_per_game' => $row['points_per_game'],
                'fg_percentage' => $row['fg_percentage'],
                'ft_percentage' => $row['ft_percentage'],
                'three_pt_percentage' => $row['three_pt_percentage'],
            ],
        ];
    }

    /**
     * Transform a player row for detail endpoints (includes full stats).
     *
     * @param array{player_uuid: string, name: string, position: string, age: int, htft: int, htin: int, experience: int, bird_rights: int, team_uuid: string|null, team_city: string|null, team_name: string|null, full_team_name: string|null, current_salary: int, year1_salary: int, year2_salary: int, games_played: int, minutes_played: int, field_goals_made: int, field_goals_attempted: int, free_throws_made: int, free_throws_attempted: int, three_pointers_made: int, three_pointers_attempted: int, offensive_rebounds: int, defensive_rebounds: int, assists: int, steals: int, turnovers: int, blocks: int, personal_fouls: int, points_per_game: float|null, fg_percentage: float|null, ft_percentage: float|null, three_pt_percentage: float|null} $row
     * @return array<string, mixed>
     */
    public function transformDetail(array $row): array
    {
        $base = $this->transform($row);

        $base['bird_rights'] = $row['bird_rights'];
        $base['stats'] = [
            'games_played' => $row['games_played'],
            'minutes_played' => $row['minutes_played'],
            'field_goals_made' => $row['field_goals_made'],
            'field_goals_attempted' => $row['field_goals_attempted'],
            'free_throws_made' => $row['free_throws_made'],
            'free_throws_attempted' => $row['free_throws_attempted'],
            'three_pointers_made' => $row['three_pointers_made'],
            'three_pointers_attempted' => $row['three_pointers_attempted'],
            'offensive_rebounds' => $row['offensive_rebounds'],
            'defensive_rebounds' => $row['defensive_rebounds'],
            'assists' => $row['assists'],
            'steals' => $row['steals'],
            'turnovers' => $row['turnovers'],
            'blocks' => $row['blocks'],
            'personal_fouls' => $row['personal_fouls'],
            'points_per_game' => $row['points_per_game'],
            'fg_percentage' => $row['fg_percentage'],
            'ft_percentage' => $row['ft_percentage'],
            'three_pt_percentage' => $row['three_pt_percentage'],
        ];

        return $base;
    }

    /**
     * Format height from htft/htin fields to "6-6" string.
     */
    private function formatHeight(int $feet, int $inches): string
    {
        if ($feet === 0) {
            return '';
        }

        return $feet . '-' . $inches;
    }

    /**
     * Transform team sub-object.
     *
     * @return array{uuid: string, city: string, name: string, full_name: string}|null
     */
    private function transformTeam(?string $teamUuid, string $teamCity, string $teamName, string $fullTeamName): ?array
    {
        if ($teamUuid === null) {
            return null;
        }

        return [
            'uuid' => $teamUuid,
            'city' => $teamCity,
            'name' => $teamName,
            'full_name' => $fullTeamName,
        ];
    }
}
