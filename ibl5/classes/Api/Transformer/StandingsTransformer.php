<?php

declare(strict_types=1);

namespace Api\Transformer;

class StandingsTransformer
{
    /**
     * Transform a standings row from vw_team_standings.
     *
     * @param array{team_uuid: string, team_city: string, team_name: string, full_team_name: string, conference: string, division: string, league_record: string, conference_record: string, division_record: string, home_record: string, away_record: string, win_percentage: float|null, conference_games_back: string|null, division_games_back: string|null, games_remaining: int, clinched_conference: int, clinched_division: int, clinched_playoffs: int} $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'team' => [
                'uuid' => $row['team_uuid'],
                'city' => $row['team_city'],
                'name' => $row['team_name'],
                'full_name' => $row['full_team_name'],
            ],
            'conference' => $row['conference'],
            'division' => $row['division'],
            'record' => [
                'league' => $row['league_record'],
                'conference' => $row['conference_record'],
                'division' => $row['division_record'],
                'home' => $row['home_record'],
                'away' => $row['away_record'],
            ],
            'win_percentage' => $row['win_percentage'],
            'games_back' => [
                'conference' => $row['conference_games_back'],
                'division' => $row['division_games_back'],
            ],
            'games_remaining' => $row['games_remaining'],
            'clinched' => [
                'conference' => $row['clinched_conference'] === 1,
                'division' => $row['clinched_division'] === 1,
                'playoffs' => $row['clinched_playoffs'] === 1,
            ],
        ];
    }
}
