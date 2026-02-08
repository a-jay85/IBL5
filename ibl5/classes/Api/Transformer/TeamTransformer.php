<?php

declare(strict_types=1);

namespace Api\Transformer;

class TeamTransformer
{
    /**
     * Transform a team row for list endpoints.
     *
     * @param array{uuid: string, team_city: string, team_name: string, owner_name: string, arena: string, conference: string|null, division: string|null} $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'uuid' => $row['uuid'],
            'city' => $row['team_city'],
            'name' => $row['team_name'],
            'full_name' => $row['team_city'] . ' ' . $row['team_name'],
            'owner' => $row['owner_name'],
            'arena' => $row['arena'],
            'conference' => $row['conference'],
            'division' => $row['division'],
        ];
    }

    /**
     * Transform a team row for detail endpoint (includes standings/power data).
     *
     * @param array{uuid: string, team_city: string, team_name: string, owner_name: string, arena: string, conference: string|null, division: string|null, league_record: string|null, conference_record: string|null, division_record: string|null, home_wins: int|null, home_losses: int|null, away_wins: int|null, away_losses: int|null, win_percentage: float|null, conference_games_back: string|null, division_games_back: string|null, games_remaining: int|null} $row
     * @return array<string, mixed>
     */
    public function transformDetail(array $row): array
    {
        $base = $this->transform($row);

        $base['record'] = [
            'league' => $row['league_record'],
            'conference' => $row['conference_record'],
            'division' => $row['division_record'],
            'home' => $row['home_wins'] !== null && $row['home_losses'] !== null
                ? $row['home_wins'] . '-' . $row['home_losses']
                : null,
            'away' => $row['away_wins'] !== null && $row['away_losses'] !== null
                ? $row['away_wins'] . '-' . $row['away_losses']
                : null,
        ];

        $base['standings'] = [
            'win_percentage' => $row['win_percentage'],
            'conference_games_back' => $row['conference_games_back'],
            'division_games_back' => $row['division_games_back'],
            'games_remaining' => $row['games_remaining'],
        ];

        return $base;
    }
}
