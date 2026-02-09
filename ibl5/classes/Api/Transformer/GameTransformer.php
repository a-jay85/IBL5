<?php

declare(strict_types=1);

namespace Api\Transformer;

class GameTransformer
{
    /**
     * Transform a game row from vw_schedule_upcoming for list/detail endpoints.
     *
     * @param array{game_uuid: string, season_year: int, game_date: string, game_status: string, box_score_id: int, visitor_uuid: string, visitor_city: string, visitor_name: string, visitor_full_name: string, visitor_score: int, visitor_team_id: int, home_uuid: string, home_city: string, home_name: string, home_full_name: string, home_score: int, home_team_id: int} $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'uuid' => $row['game_uuid'],
            'season' => $row['season_year'],
            'date' => $row['game_date'],
            'status' => $row['game_status'],
            'box_score_id' => $row['box_score_id'],
            'visitor' => [
                'uuid' => $row['visitor_uuid'],
                'city' => $row['visitor_city'],
                'name' => $row['visitor_name'],
                'full_name' => $row['visitor_full_name'],
                'score' => $row['visitor_score'],
                'team_id' => $row['visitor_team_id'],
            ],
            'home' => [
                'uuid' => $row['home_uuid'],
                'city' => $row['home_city'],
                'name' => $row['home_name'],
                'full_name' => $row['home_full_name'],
                'score' => $row['home_score'],
                'team_id' => $row['home_team_id'],
            ],
        ];
    }
}
