<?php

declare(strict_types=1);

namespace Api\Transformer;

class InjuryTransformer
{
    /**
     * Transform an injured player row.
     *
     * @param array{player_uuid: string, pid: int, name: string, pos: string, injured: int, teamid: int|null, team_uuid: string|null, team_city: string|null, team_name: string|null} $row
     * @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        return [
            'player' => [
                'uuid' => $row['player_uuid'],
                'pid' => $row['pid'],
                'name' => $row['name'],
                'position' => $row['pos'],
            ],
            'team' => [
                'uuid' => $row['team_uuid'],
                'city' => $row['team_city'] ?? '',
                'name' => $row['team_name'] ?? '',
                'team_id' => $row['teamid'] ?? 0,
            ],
            'injury' => [
                'days_remaining' => $row['injured'],
            ],
        ];
    }
}
